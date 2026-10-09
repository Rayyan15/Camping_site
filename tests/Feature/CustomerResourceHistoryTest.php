<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\BookingsRelationManager;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerResourceHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function actAs(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.Str::random(6).'@example.test',
            'password' => Str::random(24),
            'is_active' => true,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function customerWithStay(string $name = 'Tamu Uji', int $paid = 500000, int $refund = 0): Customer
    {
        $customer = Customer::create(['name' => $name, 'phone' => '081234567890', 'email' => 'tamu@example.test']);
        $booking = Booking::create([
            'code' => 'BK-'.Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-02',
            'guests' => 2,
            'status' => BookingStatus::CheckedOut,
            'subtotal' => $paid,
            'total' => $paid,
            'paid_amount' => $paid,
        ]);

        foreach ([[PaymentDirection::In, $paid], [PaymentDirection::Out, $refund]] as [$direction, $amount]) {
            if ($amount > 0) {
                Payment::create([
                    'payable_type' => $booking->getMorphClass(),
                    'payable_id' => $booking->id,
                    'direction' => $direction,
                    'method' => PaymentMethod::Cash,
                    'amount' => $amount,
                    'status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                ]);
            }
        }

        return $customer;
    }

    public function test_owner_sees_history_columns_with_exact_values(): void
    {
        $this->actAs(User::ROLE_OWNER);
        $customer = $this->customerWithStay(paid: 500000, refund: 100000);

        Livewire::test(ListCustomers::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$customer])
            ->assertTableColumnVisible('visit_count')
            ->assertTableColumnVisible('total_spend')
            ->assertTableColumnVisible('last_visit_at')
            ->assertTableColumnStateSet('visit_count', 1, $customer)
            ->assertTableColumnStateSet('total_spend', 400000, $customer);
    }

    public function test_columns_sort_and_filters_narrow_the_list(): void
    {
        $this->actAs(User::ROLE_FRONT_OFFICE);
        $big = $this->customerWithStay('Besar', 900000);
        $small = $this->customerWithStay('Kecil', 100000);
        $none = Customer::create(['name' => 'Belum Menginap', 'phone' => '081200000001']);

        Livewire::test(ListCustomers::class)
            ->sortTable('total_spend', 'desc')
            ->assertCanSeeTableRecords([$big, $small, $none], inOrder: true)
            ->sortTable('visit_count')
            ->sortTable('last_visit_at')
            ->filterTable('has_stayed', true)
            ->assertCanSeeTableRecords([$big, $small])
            ->assertCanNotSeeTableRecords([$none])
            ->removeTableFilter('has_stayed')
            ->filterTable('min_spend', ['amount' => 500000])
            ->assertCanSeeTableRecords([$big])
            ->assertCanNotSeeTableRecords([$small, $none]);
    }

    public function test_cashier_sees_name_only_without_spend_or_contact(): void
    {
        $this->actAs(User::ROLE_CASHIER);
        $customer = $this->customerWithStay();

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$customer])
            ->assertTableColumnVisible('name')
            ->assertTableColumnHidden('phone')
            ->assertTableColumnHidden('email')
            ->assertTableColumnHidden('visit_count')
            ->assertTableColumnHidden('total_spend')
            ->assertTableColumnHidden('last_visit_at');
    }

    public function test_view_page_shows_spend_summary_to_owner_and_front_office(): void
    {
        $customer = $this->customerWithStay(paid: 500000);

        foreach ([User::ROLE_OWNER, User::ROLE_FRONT_OFFICE] as $role) {
            $this->actAs($role);

            Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
                ->assertSuccessful()
                ->assertSee('Ringkasan belanja')
                ->assertSee('Jumlah kunjungan')
                ->assertSee('500.000')
                ->assertSee('081234567890');
        }
    }

    public function test_view_page_hides_spend_and_contact_from_cashier(): void
    {
        $customer = $this->customerWithStay();
        $this->actAs(User::ROLE_CASHIER);

        Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
            ->assertSuccessful()
            ->assertSee('Tamu Uji')
            ->assertDontSee('Ringkasan belanja')
            ->assertDontSee('500.000')
            ->assertDontSee('081234567890')
            ->assertDontSee('tamu@example.test');
    }

    public function test_bookings_relation_manager_lists_history_read_only_with_link(): void
    {
        $this->actAs(User::ROLE_FRONT_OFFICE);
        $customer = $this->customerWithStay();
        $booking = $customer->bookings()->first();

        Livewire::test(BookingsRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => ViewCustomer::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$booking])
            ->assertTableColumnStateSet('total', 500000, $booking)
            ->assertTableColumnStateSet('paid_amount', 500000, $booking)
            ->assertTableActionVisible('open', $booking)
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete');
    }

    public function test_relation_manager_is_available_per_role(): void
    {
        $customer = $this->customerWithStay();

        $this->actAs(User::ROLE_OWNER);
        $this->assertTrue(BookingsRelationManager::canViewForRecord($customer, ViewCustomer::class));

        $this->actAs(User::ROLE_FRONT_OFFICE);
        $this->assertTrue(BookingsRelationManager::canViewForRecord($customer, ViewCustomer::class));

        $this->actAs(User::ROLE_CASHIER);
        $this->assertFalse(BookingsRelationManager::canViewForRecord($customer, ViewCustomer::class));
    }

    public function test_list_query_count_does_not_grow_with_customers(): void
    {
        $this->actAs(User::ROLE_OWNER);

        $measure = function (int $extra): int {
            for ($i = 0; $i < $extra; $i++) {
                $this->customerWithStay('Tamu '.Str::random(5));
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(ListCustomers::class)->assertSuccessful();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $measure(1);
        $few = $measure(2);
        $many = $measure(10);

        $this->assertSame($few, $many);
    }
}
