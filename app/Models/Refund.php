<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = ['booking_id', 'amount', 'reason', 'status', 'requested_by', 'approved_by'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
