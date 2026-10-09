<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\LastOwnerException;
use App\Jobs\ReleaseExpiredHolds;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use App\Services\UserAccountService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(): Booking
    {
        $customer = Customer::create(['name' => 'Tamu', 'phone' => '081234567890']);

        return Booking::create([
            'code' => 'BK-TEST-1',
            'customer_id' => $customer->id,
            'check_in' => '2026-12-01',
            'check_out' => '2026-12-02',
            'guests' => 2,
            'status' => BookingStatus::PendingPayment,
            'subtotal' => 100000,
            'total' => 100000,
        ]);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Staf',
            'email' => 'staf.member@example.test',
            'phone' => '081234567890',
            'password' => Str::random(24),
        ]);
    }

    public function test_booking_status_change_is_logged_with_old_and_new_values_and_actor(): void
    {
        $booking = $this->makeBooking();
        $actor = $this->makeUser();
        $this->actingAs($actor);

        $booking->update(['status' => BookingStatus::Paid]);

        $log = ActivityLog::where('subject_type', 'booking')->where('action', 'status_changed')->sole();
        $this->assertSame($booking->id, $log->subject_id);
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame('pending_payment', $log->changes['old']['status']);
        $this->assertSame('paid', $log->changes['new']['status']);
    }

    public function test_changes_without_a_user_are_attributed_to_the_system(): void
    {
        $booking = $this->makeBooking();

        $booking->update(['status' => BookingStatus::Expired]);

        $log = ActivityLog::where('action', 'status_changed')->sole();
        $this->assertNull($log->user_id);
        $this->assertSame('Sistem', $log->actor_label);
    }

    public function test_touching_only_timestamps_writes_nothing(): void
    {
        $booking = $this->makeBooking();
        ActivityLog::query()->delete();

        $booking->touch();

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_sensitive_fields_are_hidden_or_partially_masked(): void
    {
        $user = $this->makeUser();
        ActivityLog::query()->delete();

        $user->update(['phone' => '089876543210', 'email' => 'new.address@example.test', 'password' => Str::random(24)]);

        $changes = ActivityLog::where('subject_type', 'user')->sole()->changes;
        $this->assertStringNotContainsString('089876543210', json_encode($changes));
        $this->assertStringNotContainsString('new.address', json_encode($changes));
        $this->assertSame('n**********@example.test', $changes['new']['email']);
        $this->assertSame('089*******10', $changes['new']['phone']);
        $this->assertSame('[disembunyikan]', $changes['new']['password']);
        $this->assertSame('[disembunyikan]', $changes['old']['password']);
    }

    public function test_creating_and_deleting_are_logged(): void
    {
        $booking = $this->makeBooking();
        $booking->delete();

        $this->assertSame(
            ['created', 'deleted'],
            ActivityLog::where('subject_type', 'booking')->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_last_active_owner_cannot_be_deactivated_or_demoted(): void
    {
        $this->seed(RoleSeeder::class);
        $service = app(UserAccountService::class);
        $owner = $service->create(['name' => 'Boss', 'email' => 'boss@example.test', 'password' => Str::random(20)], User::ROLE_OWNER);

        $this->expectException(LastOwnerException::class);

        $service->update($owner, ['is_active' => false], User::ROLE_OWNER);
    }

    public function test_expired_hold_release_is_logged_as_system_status_change(): void
    {
        $booking = $this->makeBooking();
        $booking->update(['hold_expires_at' => now()->subMinute()]);
        ActivityLog::query()->delete();

        (new ReleaseExpiredHolds)->handle();

        $log = ActivityLog::where('subject_type', 'booking')->where('action', 'status_changed')->sole();
        $this->assertSame($booking->id, $log->subject_id);
        $this->assertNull($log->user_id);
        $this->assertSame('pending_payment', $log->changes['old']['status']);
        $this->assertSame('expired', $log->changes['new']['status']);
    }
}
