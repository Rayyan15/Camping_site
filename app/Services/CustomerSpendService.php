<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentDirection;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Visit history and lifetime spend per customer (FR-55).
 *
 * A visit is a booking that was paid: status Paid, CheckedIn or CheckedOut. Expired, cancelled,
 * refunded, pending and needs-review bookings are not visits and contribute no spend. Spend is the
 * net cash of those bookings (money in minus refunds) plus the net cash of food orders attached to them.
 * Everything is computed as correlated subqueries so a customer list needs one query.
 */
class CustomerSpendService
{
    /** @return list<string> */
    public function visitStatuses(): array
    {
        return [
            BookingStatus::Paid->value,
            BookingStatus::CheckedIn->value,
            BookingStatus::CheckedOut->value,
        ];
    }

    /**
     * Adds visit_count, last_visit_at and total_spend columns to a customer query.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function withSummary(Builder $query): Builder
    {
        return $query
            ->select('customers.*')
            ->selectSub($this->visitCountQuery(), 'visit_count')
            ->selectSub($this->lastVisitQuery(), 'last_visit_at')
            ->selectSub($this->totalSpendQuery(), 'total_spend');
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function whereHasVisited(Builder $query): Builder
    {
        return $query->whereExists($this->visitsQuery());
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function whereSpendAtLeast(Builder $query, int $rupiah): Builder
    {
        return $query->where($this->totalSpendQuery(), '>=', $rupiah);
    }

    /** @return array{visit_count: int, last_visit_at: ?string, total_spend: int} */
    public function summary(Customer $customer): array
    {
        $row = $this->withSummary(Customer::query()->whereKey($customer->getKey()))->firstOrFail();

        return [
            'visit_count' => (int) $row->visit_count,
            'last_visit_at' => $row->last_visit_at,
            'total_spend' => (int) $row->total_spend,
        ];
    }

    private function visitsQuery(): Builder
    {
        return Booking::query()
            ->whereColumn('bookings.customer_id', 'customers.id')
            ->whereIn('bookings.status', $this->visitStatuses());
    }

    private function visitCountQuery(): Builder
    {
        return $this->visitsQuery()->selectRaw('COUNT(*)');
    }

    private function lastVisitQuery(): Builder
    {
        return $this->visitsQuery()->selectRaw('MAX(bookings.check_in)');
    }

    private function totalSpendQuery(): Builder
    {
        return Payment::query()
            ->settled()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN payments.direction = ? THEN payments.amount ELSE -payments.amount END), 0)',
                [PaymentDirection::In->value],
            )
            ->where(function (Builder $payments) {
                $payments
                    ->where(fn (Builder $booking) => $booking
                        ->where('payments.payable_type', (new Booking)->getMorphClass())
                        ->whereIn('payments.payable_id', $this->visitsQuery()->select('bookings.id')))
                    ->orWhere(fn (Builder $order) => $order
                        ->where('payments.payable_type', (new Order)->getMorphClass())
                        ->whereIn('payments.payable_id', Order::query()
                            ->select('orders.id')
                            ->join('bookings', 'bookings.id', '=', 'orders.booking_id')
                            ->whereColumn('bookings.customer_id', 'customers.id')
                            ->whereIn('bookings.status', $this->visitStatuses())));
            });
    }
}
