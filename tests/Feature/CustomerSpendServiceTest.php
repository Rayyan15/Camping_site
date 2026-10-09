<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CustomerSpendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerSpendServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerSpendService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CustomerSpendService::class);
    }

    private function makeCustomer(string $name = 'Tamu'): Customer
    {
        return Customer::create(['name' => $name, 'phone' => '0812'.random_int(10000000, 99999999)]);
    }

    private function makeBooking(Customer $customer, BookingStatus $status, string $checkIn = '2026-11-01'): Booking
    {
        return Booking::create([
            'code' => 'BK-'.Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'check_in' => $checkIn,
            'check_out' => date('Y-m-d', strtotime($checkIn.' +1 day')),
            'guests' => 2,
            'status' => $status,
            'subtotal' => 500000,
            'total' => 500000,
            'paid_amount' => 500000,
        ]);
    }

    private function pay(Booking|Order $payable, int $amount, PaymentDirection $direction = PaymentDirection::In, PaymentStatus $status = PaymentStatus::Paid): Payment
    {
        return Payment::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->id,
            'direction' => $direction,
            'method' => PaymentMethod::Cash,
            'amount' => $amount,
            'status' => $status,
            'paid_at' => now(),
        ]);
    }

    private function makeOrder(Booking $booking): Order
    {
        return Order::create([
            'code' => 'OR-'.Str::upper(Str::random(8)),
            'source' => Order::SOURCE_PREORDER,
            'booking_id' => $booking->id,
            'status' => 'selesai',
            'total' => 80000,
            'payment_status' => Order::PAYMENT_PAID,
        ]);
    }

    public function test_spend_is_net_of_refunds_and_includes_food_orders(): void
    {
        $customer = $this->makeCustomer();
        $booking = $this->makeBooking($customer, BookingStatus::CheckedOut);
        $this->pay($booking, 500000);
        $this->pay($booking, 100000, PaymentDirection::Out);
        $this->pay($this->makeOrder($booking), 80000);

        $summary = $this->service->summary($customer);

        $this->assertSame(480000, $summary['total_spend']);
        $this->assertSame(1, $summary['visit_count']);
    }

    public function test_only_paid_checked_in_and_checked_out_bookings_count_as_visits(): void
    {
        $customer = $this->makeCustomer();

        foreach ([BookingStatus::Paid, BookingStatus::CheckedIn, BookingStatus::CheckedOut] as $status) {
            $this->pay($this->makeBooking($customer, $status), 100000);
        }

        foreach ([BookingStatus::Expired, BookingStatus::Cancelled, BookingStatus::PendingPayment, BookingStatus::Refunded, BookingStatus::NeedsReview] as $status) {
            $excluded = $this->makeBooking($customer, $status);
            $this->pay($excluded, 999000);
            $this->pay($this->makeOrder($excluded), 55000);
        }

        $summary = $this->service->summary($customer);

        $this->assertSame(3, $summary['visit_count']);
        $this->assertSame(300000, $summary['total_spend']);
    }

    public function test_unsettled_payments_are_ignored(): void
    {
        $customer = $this->makeCustomer();
        $booking = $this->makeBooking($customer, BookingStatus::Paid);
        $this->pay($booking, 200000);
        $this->pay($booking, 300000, status: PaymentStatus::Pending);
        $this->pay($booking, 400000, status: PaymentStatus::Failed);

        $this->assertSame(200000, $this->service->summary($customer)['total_spend']);
    }

    public function test_last_visit_is_the_latest_check_in_of_a_visit(): void
    {
        $customer = $this->makeCustomer();
        $this->makeBooking($customer, BookingStatus::CheckedOut, '2026-03-10');
        $this->makeBooking($customer, BookingStatus::Paid, '2026-08-20');
        $this->makeBooking($customer, BookingStatus::Cancelled, '2026-12-25');

        $this->assertSame('2026-08-20', substr((string) $this->service->summary($customer)['last_visit_at'], 0, 10));
    }

    public function test_customer_without_visits_has_zero_summary(): void
    {
        $customer = $this->makeCustomer();
        $this->makeBooking($customer, BookingStatus::Expired);

        $this->assertSame(
            ['visit_count' => 0, 'last_visit_at' => null, 'total_spend' => 0],
            $this->service->summary($customer),
        );
    }

    public function test_spend_does_not_leak_between_customers(): void
    {
        $first = $this->makeCustomer('Satu');
        $second = $this->makeCustomer('Dua');
        $this->pay($this->makeBooking($first, BookingStatus::Paid), 111000);
        $this->pay($this->makeBooking($second, BookingStatus::Paid), 222000);

        $this->assertSame(111000, $this->service->summary($first)['total_spend']);
        $this->assertSame(222000, $this->service->summary($second)['total_spend']);
    }

    public function test_filters_select_visited_and_minimum_spend(): void
    {
        $big = $this->makeCustomer('Besar');
        $small = $this->makeCustomer('Kecil');
        $never = $this->makeCustomer('Belum');
        $this->makeBooking($never, BookingStatus::Cancelled);
        $this->pay($this->makeBooking($big, BookingStatus::Paid), 900000);
        $this->pay($this->makeBooking($small, BookingStatus::Paid), 100000);

        $visited = $this->service->whereHasVisited(Customer::query())->pluck('name')->sort()->values()->all();
        $minimum = $this->service->whereSpendAtLeast(Customer::query(), 500000)->pluck('name')->all();

        $this->assertSame(['Besar', 'Kecil'], $visited);
        $this->assertSame(['Besar'], $minimum);
    }

    public function test_list_summary_uses_one_query_regardless_of_customer_count(): void
    {
        $countQueries = function (int $customers): int {
            Customer::query()->delete();
            for ($i = 0; $i < $customers; $i++) {
                $customer = $this->makeCustomer('Tamu '.$i);
                $booking = $this->makeBooking($customer, BookingStatus::Paid);
                $this->pay($booking, 100000);
                $this->pay($this->makeOrder($booking), 20000);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $rows = $this->service->withSummary(Customer::query())->get();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertCount($customers, $rows);
            $this->assertSame(120000, (int) $rows->first()->total_spend);

            return $count;
        };

        $this->assertSame(1, $countQueries(2));
        $this->assertSame(1, $countQueries(12));
    }
}
