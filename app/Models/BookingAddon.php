<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingAddon extends Model
{
    protected $fillable = ['booking_id', 'addon_id', 'qty', 'price', 'subtotal'];
}
