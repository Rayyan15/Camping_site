<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;

/**
 * Owner (view_any_attendance) sees every employee. Staff holding manage_own_attendance
 * see only the rows of the employee record linked to their own user.
 */
class AttendancePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'attendance';
    }

    public function viewAny(User $user): bool
    {
        return parent::viewAny($user) || $this->canManageOwn($user);
    }

    public function view(User $user, mixed $record = null): bool
    {
        if (parent::view($user, $record)) {
            return true;
        }

        return $record instanceof Attendance
            && $this->canManageOwn($user)
            && Employee::query()->whereKey($record->employee_id)->where('user_id', $user->id)->exists();
    }

    private function canManageOwn(User $user): bool
    {
        return $user->is_active && $user->can('manage_own_attendance');
    }
}
