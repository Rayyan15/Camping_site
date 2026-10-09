<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'user_id'];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Ties every unclaimed guest record with the account's email to it. Only call this for a
     * verified email, since the email is the proof of ownership.
     */
    public static function claimByVerifiedEmail(User $user): int
    {
        return static::whereNull('user_id')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])
            ->update(['user_id' => $user->id]);
    }

    /**
     * Links a booking's guest record to the signed-in account when the booking email is the
     * account email and the record is still unclaimed.
     */
    public function linkToUser(User $user): bool
    {
        if ($this->user_id !== null
            || ! $user->hasVerifiedEmail()
            || mb_strtolower((string) $this->email) !== mb_strtolower($user->email)) {
            return false;
        }

        return $this->update(['user_id' => $user->id]);
    }

    /** Food orders always hang off a booking of the customer; walk-in orders without a booking have no customer. */
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Booking::class);
    }
}
