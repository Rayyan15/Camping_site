<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAddon extends Model
{
    protected $fillable = ['booking_id', 'addon_id', 'qty', 'price', 'subtotal'];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Addon, $this> */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
