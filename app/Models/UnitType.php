<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitType extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'capacity',
        'facilities', 'base_price_weekday', 'base_price_weekend',
    ];

    protected function casts(): array
    {
        return [
            'facilities' => 'array',
            'capacity' => 'integer',
            'base_price_weekday' => 'integer',
            'base_price_weekend' => 'integer',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(UnitTypePhoto::class);
    }

    public function specialPrices(): HasMany
    {
        return $this->hasMany(SpecialPrice::class);
    }
}
