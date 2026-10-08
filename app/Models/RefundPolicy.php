<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundPolicy extends Model
{
    protected $fillable = ['min_days_before', 'percent'];

    protected function casts(): array
    {
        return [
            'min_days_before' => 'integer',
            'percent' => 'integer',
        ];
    }
}
