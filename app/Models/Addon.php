<?php

namespace App\Models;

use App\Enums\AddonUnit;
use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = ['name', 'price', 'unit', 'extra_guests', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price' => 'integer',
            'extra_guests' => 'integer',
            'unit' => AddonUnit::class,
        ];
    }

    public function isPerNight(): bool
    {
        return $this->unit === AddonUnit::PerNight;
    }
}
