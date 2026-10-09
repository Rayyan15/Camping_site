<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'shift';
    }

    public function delete(User $user, mixed $record = null): bool
    {
        return parent::delete($user, $record)
            && ! ($record instanceof Shift && $record->employees()->exists());
    }
}
