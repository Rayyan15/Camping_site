<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = ['employee_id', 'date', 'clock_in', 'clock_out', 'status', 'source', 'note'];

    /** The date column stays a plain Y-m-d string so the unique index and lookups compare exactly. */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'source' => AttendanceSource::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
