<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    protected $fillable = ['employee_id', 'period', 'total_score', 'notes', 'evaluated_by'];
}
