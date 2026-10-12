<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $check_in
 * @property Carbon $check_out
 */
class BookingUnit extends Model
{
    protected $fillable = [
        'booking_id', 'unit_id', 'check_in', 'check_out',
        'price_per_night', 'nights', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'price_per_night' => 'integer',
            'nights' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<BookingUnitNight, $this> */
    public function nights(): HasMany
    {
        return $this->hasMany(BookingUnitNight::class);
    }
}
