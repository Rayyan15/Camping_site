<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'payable_type', 'payable_id', 'method', 'gateway_ref', 
        'amount', 'status', 'paid_at', 'proof_path', 'recorded_by'
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }
}
