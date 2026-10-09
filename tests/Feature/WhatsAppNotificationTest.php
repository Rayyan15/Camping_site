<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Enums\BookingStatus;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Jobs\SendWhatsappNotification;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\RefundPolicy;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\WhatsAppMessage;
use App\Services\BookingService;
use App\Services\WhatsApp\FonnteGateway;
use App\Services\WhatsApp\LogWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppConfigurationException;
use App\Services\WhatsApp\WhatsAppMessageComposer;
use App\Services\WhatsApp\WhatsAppSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Customer $customer;

    private FakeWhatsAppGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        RefundPolicy::create(['min_days_before' => 0, 'percent' => 100]);

        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 500000, 'base_price_weekend' => 700000,
        ]);
        $this->unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D-01', 'status' => Unit::STATUS_ACTIVE]);
        $this->customer = Customer::create(['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '0812-3456-7890']);

        $this->gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
        config(['whatsapp.enabled' => true]);
    }

    private function book(int $daysAhead = 10, ?Customer $customer = null): Booking
    {
        return app(BookingService::class)->createBooking(
            ($customer ?? $this->customer)->id,
            now()->addDays($daysAhead)->toDateString(),
            now()->addDays($daysAhead + 2)->toDateString(),
            2,
            [$this->unit->id],
        );
    }

    private function send(Booking $booking, WhatsAppMessageType $type): void
    {
        SendWhatsappNotification::dispatchSync($booking->id, $type);
    }

    public function test_creating_a_booking_sends_booking_created_once_with_hold_and_pay_link(): void
    {
        $booking = $this->book();

        $this->assertCount(1, $this->gateway->sent);
        $this->assertSame('6281234567890', $this->gateway->sent[0]['to']);
        $message = $this->gateway->sent[0]['message'];
        $this->assertStringContainsString($booking->code, $message);
        $this->assertStringContainsString(route('checkout.show', $booking->access_token), $message);
        $this->assertStringContainsString($booking->hold_expires_at->format('d/m/Y H:i'), $message);

        $row = WhatsAppMessage::where('booking_id', $booking->id)->sole();
        $this->assertSame(WhatsAppMessageType::BookingCreated, $row->type);
        $this->assertSame(WhatsAppMessageStatus::Sent, $row->status);
        $this->assertNotNull($row->sent_at);
    }

    public function test_marking_paid_sends_payment_confirmed_with_invoice_link(): void
    {
        $booking = $this->book();
        $this->gateway->sent = [];

        $booking->update(['status' => BookingStatus::Paid]);

        $this->assertCount(1, $this->gateway->sent);
        $message = $this->gateway->sent[0]['message'];
        $this->assertStringContainsString('lunas', $message);
        $this->assertStringContainsString(route('booking.invoice', $booking->access_token), $message);
        $this->assertSame(2, WhatsAppMessage::where('booking_id', $booking->id)->count());
    }

    public function test_dispatching_twice_sends_only_once(): void
    {
        $booking = $this->book();

        $this->send($booking, WhatsAppMessageType::BookingCreated);
        $this->send($booking, WhatsAppMessageType::BookingCreated);

        $this->assertCount(1, $this->gateway->sent);
        $this->assertSame(1, WhatsAppMessage::count());
    }

    public function test_messages_contain_no_emoji_or_em_dash(): void
    {
        $booking = $this->book();
        $booking->update(['status' => BookingStatus::Paid]);

        foreach (WhatsAppMessageType::cases() as $type) {
            $text = app(WhatsAppMessageComposer::class)->compose($booking->fresh(), $type);
            $this->assertSame(0, preg_match('/[\x{2014}\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', $text), $type->value);
        }
    }

    public function test_disabled_records_skipped_and_sends_nothing(): void
    {
        $booking = $this->book();
        $this->gateway->sent = [];
        WhatsAppMessage::query()->delete();
        config(['whatsapp.enabled' => false]);

        $this->send($booking, WhatsAppMessageType::BookingCreated);

        $this->assertSame([], $this->gateway->sent);
        $row = WhatsAppMessage::sole();
        $this->assertSame(WhatsAppMessageStatus::Skipped, $row->status);
        $this->assertStringContainsString('WHATSAPP_ENABLED', $row->error);
    }

    public function test_observer_does_not_dispatch_while_disabled(): void
    {
        config(['whatsapp.enabled' => false]);

        $this->book();

        $this->assertSame([], $this->gateway->sent);
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_invalid_phone_is_skipped_not_an_error(): void
    {
        $customer = Customer::create(['name' => 'Sari', 'email' => 's@example.com', 'phone' => '12345']);

        $booking = $this->book(customer: $customer);

        $this->assertSame([], $this->gateway->sent);
        $row = WhatsAppMessage::where('booking_id', $booking->id)->sole();
        $this->assertSame(WhatsAppMessageStatus::Skipped, $row->status);
        $this->assertStringContainsString('tidak valid', $row->error);
    }

    public function test_sender_failure_is_recorded_without_breaking_the_booking(): void
    {
        $this->gateway->result = WhatsAppSendResult::failed('Device disconnect');

        $booking = $this->book();

        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
        $row = WhatsAppMessage::where('booking_id', $booking->id)->sole();
        $this->assertSame(WhatsAppMessageStatus::Failed, $row->status);
        $this->assertSame('Device disconnect', $row->error);
    }

    public function test_sender_exception_is_recorded_as_failed(): void
    {
        $this->gateway->throw = new \RuntimeException('boom');

        $booking = $this->book();

        $this->assertNotNull($booking->id);
        $this->assertSame(WhatsAppMessageStatus::Failed, WhatsAppMessage::sole()->status);
    }

    public function test_failed_message_can_be_retried_by_a_later_dispatch(): void
    {
        $this->gateway->result = WhatsAppSendResult::failed('timeout');
        $booking = $this->book();

        $this->gateway->result = WhatsAppSendResult::sent('abc');
        $this->send($booking, WhatsAppMessageType::BookingCreated);

        $row = WhatsAppMessage::sole();
        $this->assertSame(WhatsAppMessageStatus::Sent, $row->status);
        $this->assertSame('abc', $row->provider_message_id);
        $this->assertNull($row->error);
    }

    public function test_no_message_for_expired_or_cancelled_booking(): void
    {
        $booking = $this->book();
        WhatsAppMessage::query()->delete();
        $this->gateway->sent = [];

        foreach ([BookingStatus::Expired, BookingStatus::Cancelled] as $status) {
            $booking->forceFill(['status' => $status])->saveQuietly();
            $this->send($booking, WhatsAppMessageType::BookingCreated);
            $this->send($booking, WhatsAppMessageType::CheckInReminder);
        }

        $this->assertSame([], $this->gateway->sent);
        $this->assertSame(0, WhatsAppMessage::where('status', WhatsAppMessageStatus::Sent)->count());
    }

    public function test_reminders_go_only_to_paid_bookings_checking_in_tomorrow_and_only_once(): void
    {
        $tomorrowPaid = $this->book(1);
        $tomorrowPaid->update(['status' => BookingStatus::Paid]);
        $this->unit->update(['status' => Unit::STATUS_ACTIVE]);
        $tomorrowPending = $this->bookSecondUnit(1);
        $laterPaid = $this->bookSecondUnit(5);
        $laterPaid->update(['status' => BookingStatus::Paid]);
        $this->gateway->sent = [];

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();
        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        $reminders = WhatsAppMessage::where('type', WhatsAppMessageType::CheckInReminder)->get();
        $this->assertCount(1, $reminders);
        $this->assertSame($tomorrowPaid->id, $reminders->first()->booking_id);
        $this->assertCount(1, $this->gateway->sent);
        $this->assertStringContainsString(now()->addDay()->format('d/m/Y'), $this->gateway->sent[0]['message']);
        $this->assertNotSame($tomorrowPending->id, $reminders->first()->booking_id);
    }

    public function test_reminders_command_does_nothing_while_disabled(): void
    {
        $booking = $this->book(1);
        $booking->update(['status' => BookingStatus::Paid]);
        $this->gateway->sent = [];
        WhatsAppMessage::query()->delete();
        config(['whatsapp.enabled' => false]);

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        $this->assertSame([], $this->gateway->sent);
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_phone_is_masked_in_table_and_log(): void
    {
        Log::spy();
        $this->app->instance(WhatsAppGateway::class, app(LogWhatsAppGateway::class));

        $this->book();

        $this->assertSame('62********890', WhatsAppMessage::sole()->to_masked);
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []) {
            return $context['to'] === '62********890' && ! str_contains(json_encode($context), '6281234567890');
        })->once();
    }

    public function test_fonnte_gateway_sends_token_number_and_message(): void
    {
        config(['whatsapp.fonnte.token' => 'secret-token', 'whatsapp.fonnte.url' => 'https://api.fonnte.com/send']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['8821']], 200)]);

        $result = app(FonnteGateway::class)->send('6281234567890', 'Halo');

        $this->assertTrue($result->successful);
        $this->assertSame('8821', $result->providerMessageId);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'secret-token')
            && $r['target'] === '6281234567890'
            && $r['message'] === 'Halo');
    }

    public function test_fonnte_error_response_becomes_failed_result(): void
    {
        config(['whatsapp.fonnte.token' => 'secret-token']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'invalid token'], 200)]);

        $result = app(FonnteGateway::class)->send('6281234567890', 'Halo');

        $this->assertFalse($result->successful);
        $this->assertSame('invalid token', $result->reason);
    }

    public function test_fonnte_without_token_is_rejected_and_sends_nothing(): void
    {
        config(['whatsapp.fonnte.token' => '']);
        Http::fake();

        $this->expectException(WhatsAppConfigurationException::class);

        try {
            app(FonnteGateway::class)->send('6281234567890', 'Halo');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_missing_token_through_the_job_is_recorded_as_failed(): void
    {
        config(['whatsapp.driver' => 'fonnte', 'whatsapp.fonnte.token' => '']);
        $this->app->forgetInstance(WhatsAppGateway::class);
        $this->app->bind(WhatsAppGateway::class, FonnteGateway::class);
        Http::fake();

        $booking = $this->book();

        $this->assertSame(WhatsAppMessageStatus::Failed, WhatsAppMessage::where('booking_id', $booking->id)->sole()->status);
        Http::assertNothingSent();
    }

    public function test_driver_config_selects_the_gateway(): void
    {
        $this->app->forgetInstance(WhatsAppGateway::class);
        $this->app->bind(WhatsAppGateway::class, fn ($app) => $app->make(
            config('whatsapp.driver') === 'fonnte' ? FonnteGateway::class : LogWhatsAppGateway::class
        ));
        $this->assertInstanceOf(LogWhatsAppGateway::class, app(WhatsAppGateway::class));
    }

    private function bookSecondUnit(int $daysAhead): Booking
    {
        $second = Unit::create(['unit_type_id' => $this->unit->unit_type_id, 'code' => 'D-0'.random_int(2, 99).$daysAhead, 'status' => Unit::STATUS_ACTIVE]);

        return app(BookingService::class)->createBooking(
            $this->customer->id,
            now()->addDays($daysAhead)->toDateString(),
            now()->addDays($daysAhead + 2)->toDateString(),
            2,
            [$second->id],
        );
    }
}

class FakeWhatsAppGateway implements WhatsAppGateway
{
    /** @var array<int, array{to: string, message: string}> */
    public array $sent = [];

    public ?WhatsAppSendResult $result = null;

    public ?\Throwable $throw = null;

    public function send(string $toE164, string $message): WhatsAppSendResult
    {
        if ($this->throw !== null) {
            throw $this->throw;
        }

        $this->sent[] = ['to' => $toE164, 'message' => $message];

        return $this->result ?? WhatsAppSendResult::sent('fake-id');
    }

    public function test_cancelling_and_refunding_tell_the_guest(): void
    {
        $booking = $this->book();
        $this->gateway->sent = [];

        $booking->update(['status' => BookingStatus::Cancelled]);
        $this->assertStringContainsString('dibatalkan', $this->gateway->sent[0]['message']);

        $other = $this->book(20);
        $this->gateway->sent = [];
        $other->update(['status' => BookingStatus::Refunded]);
        $this->assertStringContainsString('refund', $this->gateway->sent[0]['message']);
    }
}
