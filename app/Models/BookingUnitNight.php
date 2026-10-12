<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $night
 */
class BookingUnitNight extends Model
{
    public $timestamps = false;

    protected $fillable = ['booking_unit_id', 'unit_id', 'night'];

    protected function casts(): array
    {
        return ['night' => 'date'];
    }

    /** @return BelongsTo<BookingUnit, $this> */
    public function bookingUnit(): BelongsTo
    {
        return $this->belongsTo(BookingUnit::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
