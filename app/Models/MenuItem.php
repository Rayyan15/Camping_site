<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = [
        'category_id', 'name', 'description', 
        'price', 'photo', 'is_available'
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }
}
