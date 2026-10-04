<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiningSpot extends Model
{
    protected $fillable = ['name', 'type', 'unit_id', 'qr_token'];
}
