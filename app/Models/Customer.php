<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'email'];

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Food orders always hang off a booking of the customer; walk-in orders without a booking have no customer.
     *
     * @return HasManyThrough<Order, Booking, $this>
     */
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Booking::class);
    }
}
