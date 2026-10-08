<?php

namespace App\Models;

use App\Enums\OrderStatus;
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
        'scheduled_at', 'status', 'total', 'payment_status',
        'bill_to_booking',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'total' => 'integer',
            'bill_to_booking' => 'boolean',
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

    public function statusEnum(): OrderStatus
    {
        return OrderStatus::from($this->status);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
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
