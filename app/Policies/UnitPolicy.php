<?php

namespace App\Policies;

use App\Models\User;

class UnitPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'unit';
    }

    /** Units with booking history are kept; set them inactive instead. */
    public function delete(User $user, mixed $record = null): bool
    {
        return parent::delete($user, $record) && ! ($record !== null && $record->bookingUnits()->exists());
    }
}
