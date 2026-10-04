<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingUnit extends Model
{
    protected $fillable = [
        'booking_id', 'unit_id', 'check_in', 'check_out', 
        'price_per_night', 'nights', 'subtotal'
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
        ];
    }
}
