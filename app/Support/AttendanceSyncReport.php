<?php

namespace App\Support;

final class AttendanceSyncReport
{
    /** @var array<string, int> fingerprint id => number of punches */
    public array $unknownFingerprintIds = [];

    /** @var list<string> */
    public array $employeesWithoutShift = [];

    public int $rowsRead = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    /** Days an owner entered by hand, left untouched. */
    public int $protectedDays = 0;

    public int $malformedLines = 0;

    public function skipped(): int
    {
        return $this->malformedLines + $this->protectedDays;
    }

    public function unknownIdCount(): int
    {
        return count($this->unknownFingerprintIds);
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'rows_read' => $this->rowsRead,
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'skipped' => $this->skipped(),
            'malformed_lines' => $this->malformedLines,
            'protected_days' => $this->protectedDays,
            'unknown_fingerprint_ids' => implode(', ', array_keys($this->unknownFingerprintIds)),
            'employees_without_shift' => implode(', ', $this->employeesWithoutShift),
        ];
    }
}
