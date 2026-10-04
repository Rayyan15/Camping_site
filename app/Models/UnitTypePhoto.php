<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitTypePhoto extends Model
{
    protected $fillable = ['unit_type_id', 'path', 'sort_order'];
}
