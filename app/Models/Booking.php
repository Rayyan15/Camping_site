<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Booking extends Model
{
    public const ACCESS_TOKEN_LENGTH = 40;

    protected $fillable = [
        'code', 'invoice_number', 'customer_id', 'check_in', 'check_out', 'guests',
        'status', 'hold_expires_at', 'subtotal', 'tax', 'total',
        'paid_amount', 'notes', 'review_started_at', 'cancelled_at', 'cancellation_note',
    ];

    /** The access token is a credential for public pages, so it never leaves the model in serialized form. */
    protected $hidden = ['access_token'];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->access_token ??= Str::random(self::ACCESS_TOKEN_LENGTH);
        });
    }

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'hold_expires_at' => 'datetime',
            'review_started_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => BookingStatus::class,
            'guests' => 'integer',
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
        ];
    }

    /**
     * Bookings that currently occupy their units: paid ones, and pending ones whose hold is still running.
     */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', BookingStatus::occupying())
                ->orWhere(function (Builder $pending) {
                    $pending->where('status', BookingStatus::PendingPayment)
                        ->where('hold_expires_at', '>', now());
                });
        });
    }

    public function scopeByAccessToken(Builder $query, string $token): Builder
    {
        return $query->where('access_token', $token);
    }

    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('status', BookingStatus::NeedsReview);
    }

    public function isInvoiceable(): bool
    {
        return in_array($this->status, [
            BookingStatus::Paid,
            BookingStatus::CheckedIn,
            BookingStatus::CheckedOut,
        ], true);
    }

    public function isHoldActive(): bool
    {
        return $this->status === BookingStatus::PendingPayment
            && $this->hold_expires_at !== null
            && $this->hold_expires_at->isFuture();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'booking_units');
    }

    public function bookingUnits(): HasMany
    {
        return $this->hasMany(BookingUnit::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(BookingAddon::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
