<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = ['user_id', 'name', 'position', 'fingerprint_id', 'shift_id'];

    public function cleaningLogs(): HasMany
    {
        return $this->hasMany(CleaningLog::class);
    }
}
