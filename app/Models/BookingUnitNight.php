<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingUnitNight extends Model
{
    public $timestamps = false;

    protected $fillable = ['booking_unit_id', 'unit_id', 'night'];

    protected function casts(): array
    {
        return ['night' => 'date'];
    }

    public function bookingUnit(): BelongsTo
    {
        return $this->belongsTo(BookingUnit::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
