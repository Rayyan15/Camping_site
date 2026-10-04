<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitType extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'capacity', 
        'facilities', 'base_price_weekday', 'base_price_weekend'
    ];

    protected function casts(): array
    {
        return [
            'facilities' => 'array',
        ];
    }
}
