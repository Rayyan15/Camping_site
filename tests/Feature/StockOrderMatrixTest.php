<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QrPaymentChoice;
use App\Enums\SettingKey;
use App\Exceptions\MenuItemUnavailableException;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Jobs\CancelUnpaidTransferOrders;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\DiningSpot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\DiningSpotService;
use App\Services\MenuStockService;
use App\Services\OrderService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportService;
use App\Services\SettingRepository;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pre-deploy matrix: every way food is ordered, against every stock and payment state.
 * One test per cell, so a failure names the exact entry point and state.
 */
class StockOrderMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const ENTRIES = ['qr_tent', 'qr_tent_billed', 'qr_cafe', 'qr_transfer', 'walkin', 'booking_preorder'];

    /** Report column each entry must land in. */
    private const CHANNEL = [
        'qr_tent' => 'QR tenda', 'qr_tent_billed' => 'QR tenda', 'qr_cafe' => 'QR kafe',
        'qr_transfer' => 'QR kafe', 'walkin' => 'Walk-in kasir', 'booking_preorder' => 'Ikut booking',
    ];

    private MenuItem $bbq;

    private DiningSpot $tent;

    private DiningSpot $cafe;

    private Booking $booking;

    private MenuStockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-12-05 10:00'));

        $category = MenuCategory::create(['name' => 'Paket', 'sort_order' => 1]);
        $this->bbq = MenuItem::create(['category_id' => $category->id, 'name' => 'Paket BBQ', 'price' => 145000, 'is_available' => true]);
        $this->tent = app(DiningSpotService::class)->create('DOM-01', DiningSpot::TYPE_TENT);
        $this->cafe = app(DiningSpotService::class)->create('Meja 1', DiningSpot::TYPE_TABLE);
        $this->stock = app(MenuStockService::class);

        $customer = Customer::create(['name' => 'Tamu', 'phone' => '081211112222']);
        $this->booking = Booking::create([
            'code' => 'BK-MATRIX', 'customer_id' => $customer->id, 'check_in' => '2026-12-05', 'check_out' => '2026-12-06',
            'guests' => 2, 'status' => BookingStatus::Paid, 'subtotal' => 500000, 'total' => 500000, 'paid_amount' => 500000,
        ]);

        $settings = app(SettingRepository::class);
        foreach ([SettingKey::BankName->value => 'BCA', SettingKey::BankAccountNumber->value => '123', SettingKey::BankAccountHolder->value => 'Halimun', SettingKey::QrPaymentTransferEnabled->value => '1'] as $key => $value) {
            $settings->put(SettingKey::from($key), $value);
        }
    }

    private function place(string $entry, int $qty): Order
    {
        $items = [['menu_item_id' => $this->bbq->id, 'qty' => $qty]];
        $orders = app(OrderService::class);

        return match ($entry) {
            'qr_tent' => $orders->createQrOrder($this->tent, $items, 'Tamu', null, QrPaymentChoice::Cashier),
            'qr_tent_billed' => $orders->createOrder(Order::SOURCE_QR, $items, $this->booking->id, $this->tent->id, null, 'Tamu', null, true),
            'qr_cafe' => $orders->createQrOrder($this->cafe, $items, 'Tamu', null, QrPaymentChoice::Cashier),
            'qr_transfer' => $orders->createQrOrder($this->cafe, $items, 'Tamu', null, QrPaymentChoice::Transfer),
            'walkin' => $orders->createWalkinOrder($items, 'Tamu', null, null, $this->staff(User::ROLE_CASHIER)->id),
            'booking_preorder' => $orders->createOrder(Order::SOURCE_PREORDER, $items, $this->booking->id),
        };
    }

    private function staff(string $role): User
    {
        $user = User::create(['name' => $role, 'email' => $role.Str::random(5).'@example.test', 'password' => Str::random(24), 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function left(): ?int
    {
        return $this->stock->remainingFor($this->bbq);
    }

    /** @return array<string, array{string}> */
    public static function entries(): array
    {
        return array_combine(self::ENTRIES, array_map(fn (string $entry) => [$entry], self::ENTRIES));
    }

    #[DataProvider('entries')]
    public function test_stock_available_is_used(string $entry): void
    {
        $this->stock->setRemaining($this->bbq, 5);
        $this->place($entry, 2);

        $this->assertSame(3, $this->left());
    }

    #[DataProvider('entries')]
    public function test_sold_out_is_refused_and_writes_nothing(string $entry): void
    {
        $this->stock->setRemaining($this->bbq, 1);

        try {
            $this->place($entry, 2);
            $this->fail("{$entry} accepted more than the stock left.");
        } catch (MenuItemUnavailableException) {
            $this->assertSame(0, Order::count());
            $this->assertSame(1, $this->left());
        }
    }

    #[DataProvider('entries')]
    public function test_unlimited_never_blocks(string $entry): void
    {
        $this->place($entry, 40);

        $this->assertNull($this->left());
        $this->assertSame(1, Order::count());
    }

    #[DataProvider('entries')]
    public function test_cancelled_order_returns_stock(string $entry): void
    {
        $this->stock->setRemaining($this->bbq, 5);
        $order = $this->place($entry, 2);

        $order->update(['status' => OrderStatus::Dibatalkan->value]);

        $this->assertSame(5, $this->left());
    }

    /** @return array<string, array{string, BookingStatus}> */
    public static function deadBookings(): array
    {
        $cases = [];
        foreach (['qr_tent_billed', 'booking_preorder'] as $entry) {
            foreach ([BookingStatus::Expired, BookingStatus::Cancelled, BookingStatus::Refunded] as $status) {
                $cases["{$entry} {$status->value}"] = [$entry, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('deadBookings')]
    public function test_dead_booking_returns_stock_and_leaves_the_report(string $entry, BookingStatus $status): void
    {
        $this->stock->setRemaining($this->bbq, 5);
        $this->place($entry, 2);

        $this->booking->update(['status' => $status]);

        $this->assertSame(5, $this->left());
        $this->assertSame([], app(ReportService::class)->topMenu(ReportPeriod::thisMonth())->rows);
    }

    public function test_late_transfer_proof_reopens_the_order_and_takes_stock_back(): void
    {
        $this->stock->setRemaining($this->bbq, 5);
        $settings = app(SettingRepository::class);
        $order = $this->place('qr_transfer', 2);

        $this->travelTo(CarbonImmutable::parse('2026-12-05 11:01'));
        (new CancelUnpaidTransferOrders)->handle($settings);
        $this->assertSame(OrderStatus::Dibatalkan->value, $order->fresh()->status);
        $this->assertSame(5, $this->left());

        $this->post(route('qr.proof', [$this->cafe->qr_token, $order->code]), ['proof' => UploadedFile::fake()->image('bukti.jpg')])->assertRedirect();
        $this->assertSame(OrderStatus::Baru->value, $order->fresh()->status);
        $this->assertSame(3, $this->left());
    }

    public function test_transfer_proof_is_no_longer_taken_a_day_after_the_auto_cancel(): void
    {
        $order = $this->place('qr_transfer', 1);
        $this->travelTo(CarbonImmutable::parse('2026-12-05 11:01'));
        (new CancelUnpaidTransferOrders)->handle(app(SettingRepository::class));

        $this->travelTo(CarbonImmutable::parse('2026-12-06 11:02'));
        $this->get(route('qr.track', [$this->cafe->qr_token, $order->code]))->assertOk()->assertDontSee('Kirim bukti bayar');
        $this->post(route('qr.proof', [$this->cafe->qr_token, $order->code]), ['proof' => UploadedFile::fake()->image('bukti.jpg')]);
        $this->assertSame(OrderStatus::Dibatalkan->value, $order->fresh()->status);
    }

    public function test_late_proof_does_not_reopen_an_order_staff_cancelled(): void
    {
        $order = $this->place('qr_transfer', 1);
        $order->update(['status' => OrderStatus::Dibatalkan->value]);

        $this->post(route('qr.proof', [$this->cafe->qr_token, $order->code]), ['proof' => UploadedFile::fake()->image('bukti.jpg')]);

        $this->assertSame(OrderStatus::Dibatalkan->value, $order->fresh()->status);
    }

    public function test_late_proof_is_refused_when_the_portions_were_sold_meanwhile(): void
    {
        $this->stock->setRemaining($this->bbq, 2);
        $order = $this->place('qr_transfer', 2);
        $this->travelTo(CarbonImmutable::parse('2026-12-05 11:01'));
        (new CancelUnpaidTransferOrders)->handle(app(SettingRepository::class));
        $this->place('qr_cafe', 2);

        $this->post(route('qr.proof', [$this->cafe->qr_token, $order->code]), ['proof' => UploadedFile::fake()->image('bukti.jpg')])
            ->assertSessionHas('error');

        $this->assertSame(OrderStatus::Dibatalkan->value, $order->fresh()->status);
        $this->assertSame(0, $this->left());
    }

    #[DataProvider('entries')]
    public function test_paid_lands_in_its_own_report_column(string $entry): void
    {
        $order = $this->place($entry, 2);
        if (! $order->bill_to_booking) {
            $order->payments()->create(['direction' => 'in', 'method' => PaymentMethod::Cash, 'amount' => $order->total, 'status' => PaymentStatus::Paid, 'paid_at' => now()]);
            $order->update(['payment_status' => Order::PAYMENT_PAID]);
        }

        $summary = array_column(app(ReportService::class)->topMenu(ReportPeriod::thisMonth())->summary, 'value', 'label');

        $this->assertSame(2, $summary[self::CHANNEL[$entry]], "{$entry} landed in the wrong column");
        $this->assertSame(2, $summary['Total terjual']);
    }

    #[DataProvider('entries')]
    public function test_unpaid_shows_where_staff_chase_it(string $entry): void
    {
        Filament::setCurrentPanel(Filament::getDefaultPanel());
        $order = $this->place($entry, 1);
        $this->actingAs($this->staff('owner'));

        $owedAtCashier = in_array($entry, ['qr_tent', 'qr_cafe', 'walkin'], true);
        $list = Livewire::test(ListOrders::class)->set('activeTab', 'unpaid');
        $owedAtCashier ? $list->assertCanSeeTableRecords([$order]) : $list->assertCanNotSeeTableRecords([$order]);

        if ($entry === 'qr_transfer') {
            Livewire::test(ListOrders::class)->set('activeTab', 'transfer')->assertCanSeeTableRecords([$order]);
        }
    }
}
