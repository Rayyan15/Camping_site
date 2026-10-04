<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitBlock extends Model
{
    protected $fillable = ['unit_id', 'start_date', 'end_date', 'reason'];
}
