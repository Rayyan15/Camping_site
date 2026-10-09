<?php

namespace App\Models;

use App\Exceptions\ReferencedSetupRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    public const MAX_LATE_TOLERANCE_MINUTES = 120;

    protected $fillable = ['name', 'start_time', 'end_time', 'late_tolerance_minutes'];

    protected static function booted(): void
    {
        // Employees.shift_id is nullOnDelete, so without this guard a delete would silently unassign people.
        static::deleting(function (Shift $shift): void {
            if ($shift->employees()->exists()) {
                throw ReferencedSetupRecordException::shift();
            }
        });
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** A shift that ends at or before its start time runs past midnight into the next day. */
    public function crossesMidnight(): bool
    {
        return substr((string) $this->end_time, 0, 5) <= substr((string) $this->start_time, 0, 5);
    }
}
