<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'code', 'source', 'booking_id', 'dining_spot_id', 
        'scheduled_at', 'status', 'total', 'payment_status', 
        'bill_to_booking'
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'bill_to_booking' => 'boolean',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function diningSpot()
    {
        return $this->belongsTo(DiningSpot::class);
    }
}
