<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    public const STATUS_ACTIVE = 'active';

    protected $fillable = ['unit_type_id', 'code', 'status'];

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(UnitBlock::class);
    }

    public function bookingUnits(): HasMany
    {
        return $this->hasMany(BookingUnit::class);
    }

    /**
     * Units with no occupying booking overlapping the stay and no maintenance block on it.
     * The checkout day is free for the next guest; a block's end_date is inclusive.
     */
    public function scopeFreeBetween(Builder $query, string $checkIn, string $checkOut, ?int $ignoreBookingId = null): Builder
    {
        return $query
            ->whereDoesntHave('bookingUnits', function (Builder $lines) use ($checkIn, $checkOut, $ignoreBookingId) {
                $lines->whereDate('check_in', '<', $checkOut)
                    ->whereDate('check_out', '>', $checkIn)
                    ->whereHas('booking', fn (Builder $booking) => $booking->occupying())
                    ->when($ignoreBookingId, fn (Builder $q) => $q->where('booking_id', '!=', $ignoreBookingId));
            })
            ->whereDoesntHave('blocks', fn (Builder $blocks) => $blocks
                ->whereDate('start_date', '<', $checkOut)
                ->whereDate('end_date', '>=', $checkIn));
    }

    public function isFreeBetween(string $checkIn, string $checkOut, ?int $ignoreBookingId = null): bool
    {
        return static::whereKey($this->getKey())->freeBetween($checkIn, $checkOut, $ignoreBookingId)->exists();
    }
}
