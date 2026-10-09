<?php

namespace App\Policies;

use App\Models\User;

class CustomerPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'customer';
    }

    /** Customers with bookings are kept: deleting them would cascade into financial data. */
    public function delete(User $user, mixed $record = null): bool
    {
        return parent::delete($user, $record) && ! ($record !== null && $record->bookings()->exists());
    }

    /** Visit history and lifetime spend are personal financial data; the cashier sees the name only. */
    public function viewSpend(User $user): bool
    {
        return $user->is_active && $user->can('view_customer_spend');
    }
}
