<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SettingKey;
use App\Events\OrderPlaced;
use App\Models\DiningSpot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\DiningSpotService;
use App\Services\Payment\OrderTransferProofService;
use App\Services\SettingRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrTransferPaymentTest extends TestCase
{
    use RefreshDatabase;

    private DiningSpot $table;

    private MenuItem $coffee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $category = MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        $this->coffee = MenuItem::create(['category_id' => $category->id, 'name' => 'Kopi Tubruk', 'price' => 12000, 'is_available' => true]);
        $this->table = app(DiningSpotService::class)->create('Meja 1', DiningSpot::TYPE_TABLE);

        $settings = app(SettingRepository::class);
        $settings->put(SettingKey::BankName, 'BCA');
        $settings->put(SettingKey::BankAccountNumber, '1234567890');
        $settings->put(SettingKey::BankAccountHolder, 'Halimun Highland');
    }

    private function enableTransfer(bool $on = true): void
    {
        app(SettingRepository::class)->put(SettingKey::QrPaymentTransferEnabled, $on ? '1' : '0');
    }

    private function staff(string $role): User
    {
        $user = User::create(['name' => $role, 'email' => $role.Str::random(5).'@example.test', 'password' => Str::random(24), 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function transferOrder(): Order
    {
        $this->enableTransfer();
        $this->post(route('qr.store', $this->table->qr_token), [
            'customer_name' => 'Sari', 'customer_phone' => '081200000000', 'payment_choice' => 'transfer',
            'items' => [$this->coffee->id => ['qty' => 2]],
        ])->assertRedirect();

        return Order::firstOrFail();
    }

    private function upload(Order $order): void
    {
        $this->post(route('qr.proof', [$this->table->qr_token, $order->code]), [
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect(route('qr.track', [$this->table->qr_token, $order->code]));
    }

    public function test_transfer_choice_follows_the_admin_setting(): void
    {
        $this->get(route('qr.show', $this->table->qr_token))->assertDontSee('Transfer bank');

        $this->enableTransfer();
        $this->get(route('qr.show', $this->table->qr_token))->assertSee('Transfer bank');
    }

    public function test_transfer_order_waits_for_the_cashier_before_the_kitchen_sees_it(): void
    {
        Event::fake([OrderPlaced::class]);
        $order = $this->transferOrder();

        Event::assertNotDispatched(OrderPlaced::class);
        $this->assertFalse(Order::kitchenRelevant()->whereKey($order->id)->exists());
        $this->get(route('qr.track', [$this->table->qr_token, $order->code]))
            ->assertOk()->assertSee('1234567890')->assertSee('Kirim bukti bayar');

        $this->upload($order);
        $payment = Payment::firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(24000, $payment->amount);

        app(OrderTransferProofService::class)->confirm($payment, $this->staff(User::ROLE_CASHIER)->id);

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertTrue(Order::kitchenRelevant()->whereKey($order->id)->exists());
        Event::assertDispatched(OrderPlaced::class);
    }

    public function test_rejected_proof_can_be_uploaded_again(): void
    {
        $order = $this->transferOrder();
        $this->upload($order);

        app(OrderTransferProofService::class)->reject(Payment::firstOrFail(), 'Nominal kurang', $this->staff(User::ROLE_CASHIER)->id);

        $this->get(route('qr.track', [$this->table->qr_token, $order->code]))->assertSee('Nominal kurang');
        $this->upload($order);
        $this->assertSame(PaymentStatus::Pending, Payment::latest('id')->firstOrFail()->status);
    }

    public function test_only_the_cashier_reviews_food_order_proofs(): void
    {
        $order = $this->transferOrder();
        $this->upload($order);
        $payment = Payment::firstOrFail();

        $this->assertTrue($this->staff(User::ROLE_CASHIER)->can('reviewProof', $payment));
        $this->assertFalse($this->staff(User::ROLE_FRONT_OFFICE)->can('reviewProof', $payment));
    }
}
