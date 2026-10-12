<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Payments are read only. Front office sees booking payments, the cashier sees order payments
 * (PRD FR-32); the owner holds both functional permissions and therefore sees everything.
 */
class PaymentPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'payment';
    }

    /**
     * Morph aliases of the payables this user may see.
     *
     * @return array<int, string>
     */
    public static function visiblePayableTypes(User $user): array
    {
        return array_keys(array_filter([
            'booking' => $user->can('record_manual_payment'),
            'order' => $user->can('record_order_payment'),
        ]));
    }

    public function viewAny(User $user): bool
    {
        return parent::viewAny($user) && self::visiblePayableTypes($user) !== [];
    }

    public function view(User $user, mixed $record = null): bool
    {
        if (! parent::view($user, $record)) {
            return false;
        }

        return $record === null || in_array($record->payable_type, self::visiblePayableTypes($user), true);
    }

    public function viewProof(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }

    /** Confirming or rejecting a guest's transfer proof is a booking payment decision. */
    public function reviewProof(User $user, Payment $payment): bool
    {
        return $user->is_active
            && $user->can('record_manual_payment')
            && in_array('booking', self::visiblePayableTypes($user), true)
            && $payment->payable_type === 'booking';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $record = null): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record = null): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
