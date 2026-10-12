<?php

namespace App\Models;

use App\Enums\CleaningLogStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CleaningLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'employee_id',
        'cleaned_at',
        'status',
        'photo_path',
        'notes',
    ];

    protected $casts = [
        'cleaned_at' => 'datetime',
        'status' => CleaningLogStatus::class,
    ];

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
