<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'code', 'customer_id', 'check_in', 'check_out', 'guests', 
        'status', 'hold_expires_at', 'subtotal', 'tax', 'total', 
        'paid_amount', 'notes'
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'hold_expires_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function units()
    {
        return $this->belongsToMany(Unit::class, 'booking_units');
    }
}
