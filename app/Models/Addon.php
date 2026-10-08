<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    public const UNIT_PER_NIGHT = 'per malam';

    public const UNIT_PER_ITEM = 'per item';

    protected $fillable = ['name', 'price', 'unit', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price' => 'integer',
        ];
    }

    public function isPerNight(): bool
    {
        return $this->unit === self::UNIT_PER_NIGHT;
    }
}
