<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['unit_type_id', 'code', 'status'];

    public function unitType()
    {
        return $this->belongsTo(UnitType::class);
    }
}
