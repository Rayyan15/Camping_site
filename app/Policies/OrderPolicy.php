<?php

namespace App\Policies;

use App\Models\User;

class OrderPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'order';
    }

    public function reviewTransfer(User $user, mixed $record = null): bool
    {
        return $user->is_active && $user->can('review_order_transfer');
    }
}
