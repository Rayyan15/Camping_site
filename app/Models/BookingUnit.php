<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function nights(): HasMany
    {
        return $this->hasMany(BookingUnitNight::class);
    }
}
