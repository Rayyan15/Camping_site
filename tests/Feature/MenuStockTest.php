<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QrPaymentChoice;
use App\Enums\SettingKey;
use App\Exceptions\MenuItemUnavailableException;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
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
use Tests\TestCase;

class MenuStockTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $satay;

    private DiningSpot $table;

    private MenuStockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $category = MenuCategory::create(['name' => 'Makanan', 'sort_order' => 1]);
        $this->satay = MenuItem::create(['category_id' => $category->id, 'name' => 'Sate Maranggi', 'price' => 35000, 'is_available' => true]);
        $this->table = app(DiningSpotService::class)->create('Meja 1', DiningSpot::TYPE_TABLE);
        $this->stock = app(MenuStockService::class);
    }

    private function order(int $qty, QrPaymentChoice $choice = QrPaymentChoice::Cashier): Order
    {
        return app(OrderService::class)->createQrOrder($this->table, [['menu_item_id' => $this->satay->id, 'qty' => $qty]], 'Sari', null, $choice);
    }

    private function staff(string $role): User
    {
        $user = User::create(['name' => $role, 'email' => $role.Str::random(5).'@example.test', 'password' => Str::random(24), 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function bookingWithFood(BookingStatus $status, int $qty): Booking
    {
        $customer = Customer::create(['name' => 'Tamu', 'phone' => '0812'.random_int(10000000, 99999999)]);
        $booking = Booking::create([
            'code' => 'BK-'.Str::random(6), 'customer_id' => $customer->id, 'check_in' => '2026-12-01', 'check_out' => '2026-12-02',
            'guests' => 2, 'status' => $status, 'subtotal' => 100000, 'total' => 100000, 'paid_amount' => 0,
        ]);
        app(OrderService::class)->createOrder(Order::SOURCE_PREORDER, [['menu_item_id' => $this->satay->id, 'qty' => $qty]], $booking->id);

        return $booking;
    }

    public function test_items_without_a_count_are_unlimited(): void
    {
        $this->order(50);

        $this->assertNull($this->stock->remainingFor($this->satay));
    }

    public function test_orders_use_stock_and_a_cancelled_order_gives_it_back(): void
    {
        $this->stock->setRemaining($this->satay, 5);
        $order = $this->order(3);
        $this->assertSame(2, $this->stock->remainingFor($this->satay));

        try {
            $this->order(3);
            $this->fail('Ordering more than what is left must be refused.');
        } catch (MenuItemUnavailableException $e) {
            $this->assertStringContainsString('tinggal 2', $e->getMessage());
        }

        app(OrderService::class)->updateStatus($order, OrderStatus::Dibatalkan);
        $this->assertSame(5, $this->stock->remainingFor($this->satay));

        $this->stock->add($this->satay, 4);
        $this->assertSame(9, $this->stock->remainingFor($this->satay));
    }

    public function test_food_of_an_expired_booking_returns_to_stock(): void
    {
        $this->stock->setRemaining($this->satay, 10);
        $booking = $this->bookingWithFood(BookingStatus::PendingPayment, 4);
        $this->assertSame(6, $this->stock->remainingFor($this->satay));

        $booking->update(['status' => BookingStatus::Expired]);
        $this->assertSame(10, $this->stock->remainingFor($this->satay));
    }

    public function test_qr_menu_hides_sold_out_items_and_caps_the_quantity(): void
    {
        $this->stock->setRemaining($this->satay, 2);
        $this->get(route('qr.show', $this->table->qr_token))->assertSee('Sate Maranggi')->assertSee('Sisa 2')->assertSee('max="2"', false);

        $this->order(2);
        $this->get(route('qr.show', $this->table->qr_token))->assertDontSee('Sate Maranggi');
    }

    public function test_unpaid_transfer_orders_are_cancelled_after_the_owner_setting(): void
    {
        $this->stock->setRemaining($this->satay, 10);
        $settings = app(SettingRepository::class);
        $settings->put(SettingKey::QrTransferCancelMinutes, '30');
        $settings->flush();

        $this->travelTo(CarbonImmutable::parse('2026-12-01 10:00'));
        $forgotten = $this->order(2, QrPaymentChoice::Transfer);
        $proofSent = $this->order(1, QrPaymentChoice::Transfer);
        $proofSent->payments()->create(['direction' => 'in', 'method' => 'transfer', 'amount' => $proofSent->total, 'status' => PaymentStatus::Pending]);

        $this->travelTo(CarbonImmutable::parse('2026-12-01 10:29'));
        (new CancelUnpaidTransferOrders)->handle($settings);
        $this->assertSame(OrderStatus::Baru->value, $forgotten->fresh()->status);

        $this->travelTo(CarbonImmutable::parse('2026-12-01 10:31'));
        (new CancelUnpaidTransferOrders)->handle($settings);
        $this->assertSame(OrderStatus::Dibatalkan->value, $forgotten->fresh()->status);
        $this->assertSame(OrderStatus::Baru->value, $proofSent->fresh()->status);
        $this->assertSame(9, $this->stock->remainingFor($this->satay));

        $settings->put(SettingKey::QrTransferCancelMinutes, '0');
        $settings->flush();
        $this->order(1, QrPaymentChoice::Transfer);
        $this->travelTo(CarbonImmutable::parse('2026-12-02 10:31'));
        (new CancelUnpaidTransferOrders)->handle($settings);
        $this->assertSame(1, Order::where('status', OrderStatus::Dibatalkan->value)->count());
    }

    public function test_a_proof_sent_after_the_auto_cancel_reopens_the_order(): void
    {
        Storage::fake('local');
        $settings = app(SettingRepository::class);
        foreach ([SettingKey::BankName->value => 'BCA', SettingKey::BankAccountNumber->value => '123', SettingKey::BankAccountHolder->value => 'Halimun', SettingKey::QrPaymentTransferEnabled->value => '1'] as $key => $value) {
            $settings->put(SettingKey::from($key), $value);
        }
        $this->travelTo(CarbonImmutable::parse('2026-12-01 10:00'));
        $order = $this->order(1, QrPaymentChoice::Transfer);
        $this->travelTo(CarbonImmutable::parse('2026-12-01 11:01'));
        (new CancelUnpaidTransferOrders)->handle($settings);
        $this->assertSame(OrderStatus::Dibatalkan->value, $order->fresh()->status);

        $this->get(route('qr.track', [$this->table->qr_token, $order->code]))->assertSee('Kirim bukti bayar');
        $this->post(route('qr.proof', [$this->table->qr_token, $order->code]), ['proof' => UploadedFile::fake()->image('bukti.jpg')])->assertRedirect();

        $this->assertSame(OrderStatus::Baru->value, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->payments()->firstOrFail()->status);
    }

    public function test_only_the_owner_changes_stock_and_the_kitchen_sees_it(): void
    {
        Filament::setCurrentPanel(Filament::getDefaultPanel());
        $this->stock->setRemaining($this->satay, 3);

        $this->actingAs($this->staff(User::ROLE_KITCHEN));
        Livewire::test(ListMenuItems::class)
            ->assertSee('3 porsi')
            ->assertTableActionHidden('add_stock', $this->satay)
            ->assertTableActionHidden('set_stock', $this->satay);

        $this->actingAs($this->staff('owner'));
        Livewire::test(ListMenuItems::class)
            ->callTableAction('add_stock', $this->satay, ['portions' => 7]);
        $this->assertSame(10, $this->stock->remainingFor($this->satay));
    }

    public function test_top_menu_splits_booking_food_from_qr_scans_in_tents_and_the_cafe(): void
    {
        $this->bookingWithFood(BookingStatus::Paid, 2);
        $tent = app(DiningSpotService::class)->create('DOM-01', DiningSpot::TYPE_TENT);
        foreach ([[$this->table, 3], [$tent, 1]] as [$spot, $qty]) {
            $order = app(OrderService::class)->createQrOrder($spot, [['menu_item_id' => $this->satay->id, 'qty' => $qty]], 'Sari', null, QrPaymentChoice::Cashier);
            app(OrderService::class)->markPaidAtCashier($order, PaymentMethod::Cash, $this->staff(User::ROLE_CASHIER)->id);
        }
        $this->bookingWithFood(BookingStatus::Expired, 5);

        $report = app(ReportService::class)->topMenu(ReportPeriod::thisMonth());

        $this->assertSame([['Sate Maranggi', 2, 1, 3, 0, 6, 210000, 'Tidak dibatasi']], $report->rows);
    }
}
