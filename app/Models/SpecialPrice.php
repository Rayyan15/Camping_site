<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialPrice extends Model
{
    protected $fillable = ['unit_type_id', 'date', 'price', 'note'];
}
