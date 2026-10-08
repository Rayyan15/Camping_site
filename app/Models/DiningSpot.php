<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningSpot extends Model
{
    public const TYPE_TABLE = 'table';

    public const TYPE_TENT = 'tent';

    protected $fillable = ['name', 'type', 'unit_id', 'qr_token'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function orderUrl(): string
    {
        return route('qr.show', $this->qr_token);
    }
}
