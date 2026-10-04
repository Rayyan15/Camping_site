<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'subject_type', 'subject_id', 'action', 'changes'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }
}
