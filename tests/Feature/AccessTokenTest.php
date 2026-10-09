<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\RefundPolicy;
use App\Models\Unit;
use App\Models\UnitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccessTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->travelTo('2026-12-01 10:00:00');
    }

    private function booking(string $code = 'RCM-261201-ABC123', BookingStatus $status = BookingStatus::Paid): Booking
    {
        $type = UnitType::firstOrCreate(['slug' => 'dome'], [
            'name' => 'Dome', 'capacity' => 2, 'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
        Unit::firstOrCreate(['code' => 'D1'], ['unit_type_id' => $type->id, 'status' => 'active']);
        $customer = Customer::firstOrCreate(
            ['phone' => '0812-3456-7890'],
            ['name' => 'Budi Santoso', 'email' => 'budi@example.com'],
        );

        return Booking::create([
            'code' => $code, 'customer_id' => $customer->id,
            'check_in' => '2026-12-20', 'check_out' => '2026-12-22', 'guests' => 2,
            'status' => $status, 'subtotal' => 200000, 'tax' => 22000, 'total' => 222000, 'paid_amount' => 222000,
        ]);
    }

    public function test_tokens_are_unique_random_and_not_the_booking_code(): void
    {
        $first = $this->booking('RCM-261201-AAAAAA');
        $second = $this->booking('RCM-261201-BBBBBB');

        $this->assertSame(40, strlen($first->access_token));
        $this->assertNotSame($first->access_token, $second->access_token);
        $this->assertStringNotContainsString('AAAAAA', $first->access_token);
    }

    public function test_status_page_opens_by_token_and_not_by_code(): void
    {
        $booking = $this->booking();

        $this->get(route('booking.status', $booking->access_token))->assertOk()->assertSee($booking->code);
        $this->get('/booking/'.$booking->code)->assertNotFound();
        $this->get('/booking/'.str_repeat('x', 40))->assertNotFound();
    }

    public function test_invoice_checkout_and_cancel_only_work_through_the_token(): void
    {
        $paid = $this->booking('RCM-261201-PAID00');
        $pending = $this->booking('RCM-261201-PEND00', BookingStatus::PendingPayment);

        $this->get('/booking/'.$paid->code.'/invoice')->assertNotFound();
        $this->get(route('booking.invoice', $paid->access_token))->assertOk();
        $this->get('/checkout/'.$pending->code)->assertNotFound();
        $this->post('/checkout/'.$pending->code.'/pay')->assertNotFound();
        $this->post('/booking/'.$paid->code.'/cancel', ['reason' => 'Sakit mendadak'])->assertNotFound();
    }

    public function test_manual_lookup_redirects_to_token_url_with_phone_tail_or_email(): void
    {
        $booking = $this->booking();

        $this->post(route('booking.find.submit'), ['code' => $booking->code, 'proof' => '7890'])
            ->assertRedirect(route('booking.status', $booking->access_token));

        $this->post(route('booking.find.submit'), ['code' => strtolower($booking->code), 'proof' => 'BUDI@example.com'])
            ->assertRedirect(route('booking.status', $booking->access_token));
    }

    public function test_manual_lookup_answers_the_same_for_every_kind_of_miss(): void
    {
        $booking = $this->booking();
        $sameMessage = fn (string $text) => str_contains($text, 'Data tidak cocok');

        foreach ([
            ['code' => $booking->code, 'proof' => '0000'],
            ['code' => 'RCM-000000-ZZZZZZ', 'proof' => '7890'],
            ['code' => $booking->code, 'proof' => 'salah@example.com'],
        ] as $payload) {
            $this->from(route('booking.find'))
                ->post(route('booking.find.submit'), $payload)
                ->assertRedirect(route('booking.find'))
                ->assertSessionHas('error', $sameMessage);
        }
    }

    public function test_manual_lookup_requires_both_fields_and_is_throttled(): void
    {
        $this->post(route('booking.find.submit'), ['code' => 'RCM-261201-ABC123'])->assertSessionHasErrors('proof');

        config(['booking.throttle.find_per_ip' => 2]);
        Cache::flush();

        $this->post(route('booking.find.submit'), ['code' => 'A', 'proof' => '1111']);
        $this->post(route('booking.find.submit'), ['code' => 'A', 'proof' => '1111']);
        $this->post(route('booking.find.submit'), ['code' => 'A', 'proof' => '1111'])->assertStatus(429);
    }

    public function test_lookup_form_renders_and_footer_links_to_it(): void
    {
        $this->get(route('booking.find'))->assertOk()->assertSee('Kode booking')->assertSee('4 digit terakhir');
        $this->get(route('home'))->assertSee(route('booking.find'), false);
    }

    public function test_status_page_shows_refund_amount_before_confirmation(): void
    {
        $booking = $this->booking();
        RefundPolicy::create(['min_days_before' => 7, 'percent' => 50]);

        $this->get(route('booking.status', $booking->access_token))
            ->assertOk()
            ->assertSee('Jika dibatalkan sekarang, dana yang dikembalikan:')
            ->assertSee('Rp 111.000 (50%)');
    }

    public function test_status_page_states_when_cancellation_has_no_refund(): void
    {
        $booking = $this->booking();

        $this->get(route('booking.status', $booking->access_token))
            ->assertSee('Pembatalan ini tidak mendapat pengembalian dana');
    }

    public function test_token_is_hidden_from_serialization_and_activity_log(): void
    {
        $booking = $this->booking();

        $this->assertArrayNotHasKey('access_token', $booking->toArray());
        $this->assertStringNotContainsString($booking->access_token, $booking->toJson());

        $logged = json_encode(ActivityLog::all()->pluck('changes')->all());
        $this->assertStringNotContainsString($booking->access_token, $logged);
    }

    public function test_migration_backfills_missing_tokens_idempotently(): void
    {
        $booking = $this->booking();
        DB::table('bookings')->where('id', $booking->id)->update(['access_token' => null]);

        $migration = require database_path('migrations/2026_10_14_000001_add_access_token_to_bookings_table.php');
        $migration->up();

        $token = DB::table('bookings')->where('id', $booking->id)->value('access_token');
        $this->assertSame(40, strlen($token));

        $migration->up();
        $this->assertSame($token, DB::table('bookings')->where('id', $booking->id)->value('access_token'));
    }
}
