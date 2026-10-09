<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\QrPaymentChoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    public const SOURCE_PREORDER = 'preorder';

    public const SOURCE_QR = 'qr';

    public const SOURCE_WALKIN = 'walkin';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PAID = 'paid';

    protected $fillable = [
        'code', 'source', 'booking_id', 'customer_name', 'customer_phone', 'dining_spot_id',
        'scheduled_at', 'status', 'total', 'tax', 'payment_status', 'payment_choice',
        'bill_to_booking',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'total' => 'integer',
            'tax' => 'integer',
            'bill_to_booking' => 'boolean',
            'payment_choice' => QrPaymentChoice::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function diningSpot(): BelongsTo
    {
        return $this->belongsTo(DiningSpot::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Orders the kitchen should act on. QR and walk-in orders count, except a QR order the guest
     * chose to pay online and has not paid yet. A pre-order only counts while its booking is paid or
     * checked in. Pending payment is hidden because the stay is not confirmed, and needs_review is
     * hidden until staff settle the doubtful payment.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeKitchenRelevant(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->where(function (Builder $direct) {
                $direct->whereIn('source', [self::SOURCE_QR, self::SOURCE_WALKIN])
                    ->where(fn (Builder $paid) => $paid
                        ->whereNull('payment_choice')
                        ->orWhere('payment_choice', '!=', QrPaymentChoice::Online->value)
                        ->orWhere('payment_status', self::PAYMENT_PAID));
            })
                ->orWhere(function (Builder $preorder) {
                    $preorder->where('source', self::SOURCE_PREORDER)
                        ->whereHas('booking', fn (Builder $booking) => $booking
                            ->whereIn('status', [BookingStatus::Paid, BookingStatus::CheckedIn]));
                });
        });
    }

    /**
     * Pre-orders whose booking is still waiting for payment inside its hold window. These are the
     * orders scopeKitchenRelevant hides, so staff can count them without the kitchen acting on them.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeAwaitingBookingPayment(Builder $query): void
    {
        $query->where('source', self::SOURCE_PREORDER)
            ->whereHas('booking', fn (Builder $booking) => $booking
                ->where('status', BookingStatus::PendingPayment)
                ->where('hold_expires_at', '>', now()));
    }

    public function statusEnum(): OrderStatus
    {
        return OrderStatus::from($this->status);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function awaitsOnlinePayment(): bool
    {
        return $this->payment_choice === QrPaymentChoice::Online && ! $this->isPaid();
    }

    /**
     * Name shown to staff: the person who ordered, falling back to the booking's customer.
     */
    public function displayName(): string
    {
        return $this->customer_name
            ?? $this->booking?->customer?->name
            ?? 'Tanpa nama';
    }
}
