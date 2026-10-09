<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Notifications\ResetCustomerPassword;
use App\Notifications\VerifyCustomerEmail;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'rahasia-panjang-1';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->travelTo('2026-12-01 10:00:00');
    }

    private function customerUser(array $overrides = []): User
    {
        $user = User::create(array_replace([
            'name' => 'Sinta Dewi',
            'email' => 'sinta@example.com',
            'password' => self::PASSWORD,
        ], $overrides));
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function unverifiedUser(): User
    {
        $user = $this->customerUser(['email' => 'baru@example.com']);
        $user->forceFill(['email_verified_at' => null])->save();

        return $user;
    }

    private function guestBooking(string $email, string $code, BookingStatus $status = BookingStatus::Paid, string $phone = '6281234567890'): Booking
    {
        $type = UnitType::firstOrCreate(['slug' => 'dome'], [
            'name' => 'Dome', 'capacity' => 2, 'base_price_weekday' => 100000, 'base_price_weekend' => 150000,
        ]);
        Unit::firstOrCreate(['code' => 'D1'], ['unit_type_id' => $type->id, 'status' => Unit::STATUS_ACTIVE]);
        $customer = Customer::firstOrCreate(['email' => $email, 'phone' => $phone], ['name' => 'Tamu']);

        $booking = Booking::create([
            'code' => $code, 'customer_id' => $customer->id,
            'check_in' => '2026-12-20', 'check_out' => '2026-12-22', 'guests' => 2,
            'status' => $status, 'subtotal' => 200000, 'tax' => 22000, 'total' => 222000, 'paid_amount' => 222000,
        ]);
        $booking->bookingUnits()->create(['unit_id' => Unit::firstWhere('code', 'D1')->id, 'check_in' => '2026-12-20', 'check_out' => '2026-12-22', 'nights' => 2, 'price_per_night' => 100000, 'subtotal' => 200000]);

        return $booking;
    }

    public function test_registration_creates_an_unverified_account_without_signing_in(): void
    {
        Notification::fake();

        $this->post(route('account.register.store'), [
            'name' => 'Sinta Dewi', 'email' => 'Sinta@Example.com', 'phone' => '0812 3456 7890',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ])->assertRedirect(route('login'));

        $user = User::where('email', 'sinta@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('6281234567890', $user->phone);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertGuest();
        Notification::assertSentTo($user, VerifyCustomerEmail::class);
        $this->assertFalse($user->hasAnyRole(User::PANEL_ROLES));
    }

    public function test_registration_validates_password_length_and_confirmation(): void
    {
        $this->post(route('account.register.store'), [
            'name' => 'Sinta', 'email' => 'sinta@example.com',
            'password' => 'pendek', 'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors(['password' => 'Password minimal 8 karakter.']);

        $this->post(route('account.register.store'), [
            'name' => 'Sinta', 'email' => 'sinta@example.com',
            'password' => self::PASSWORD, 'password_confirmation' => 'lain-sama-sekali',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registering_a_taken_email_gives_the_same_reply_and_changes_nothing(): void
    {
        $existing = $this->customerUser();
        Notification::fake();

        $fresh = $this->post(route('account.register.store'), [
            'name' => 'Orang Lain', 'email' => 'lain@example.com',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ]);
        $taken = $this->post(route('account.register.store'), [
            'name' => 'Penyusup', 'email' => 'sinta@example.com',
            'password' => 'password-penyusup', 'password_confirmation' => 'password-penyusup',
        ]);

        $this->assertSame($fresh->getSession()->get('status'), $taken->getSession()->get('status'));
        $taken->assertRedirect(route('login'));
        $this->assertTrue(Hash::check(self::PASSWORD, $existing->fresh()->password));
        $this->assertSame('Sinta Dewi', $existing->fresh()->name);
        Notification::assertNotSentTo($existing, VerifyCustomerEmail::class);
    }

    public function test_signed_link_verifies_email_and_wrong_hash_is_refused(): void
    {
        $user = $this->unverifiedUser();

        $bad = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('lain@example.com')]);
        $this->get($bad)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);

        $unsigned = route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($unsigned)->assertForbidden();

        $good = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($good)->assertRedirect(route('login'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_old_bookings_link_only_after_the_email_is_verified(): void
    {
        $booking = $this->guestBooking('baru@example.com', 'RCM-261201-AAAAAA');
        $user = $this->unverifiedUser();

        $this->assertNull($booking->customer->fresh()->user_id);

        $this->actingAs($user)->get(route('account.index'))->assertRedirect(route('verification.notice'));
        $this->assertNull($booking->customer->fresh()->user_id);

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($link);

        $this->assertSame($user->id, $booking->customer->fresh()->user_id);
    }

    public function test_verification_never_takes_over_a_record_that_already_belongs_to_another_account(): void
    {
        $owner = $this->customerUser(['email' => 'pemilik@example.com']);
        $booking = $this->guestBooking('baru@example.com', 'RCM-261201-BBBBBB');
        $booking->customer->update(['user_id' => $owner->id]);

        $user = $this->unverifiedUser();
        $user->markEmailAsVerified();
        event(new Verified($user));

        $this->assertSame($owner->id, $booking->customer->fresh()->user_id);
    }

    public function test_login_logout_and_redirect_to_history(): void
    {
        $this->customerUser();

        $this->post(route('login.store'), ['email' => 'SINTA@example.com', 'password' => self::PASSWORD])
            ->assertRedirect(route('account.index'));
        $this->assertAuthenticated();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_failed_login_does_not_reveal_whether_the_email_exists(): void
    {
        $this->customerUser();

        $wrongPassword = $this->from(route('login'))->post(route('login.store'), ['email' => 'sinta@example.com', 'password' => 'salah-salah']);
        $unknown = $this->from(route('login'))->post(route('login.store'), ['email' => 'tidak-ada@example.com', 'password' => 'salah-salah']);

        $this->assertSame(
            $wrongPassword->getSession()->get('errors')->first('email'),
            $unknown->getSession()->get('errors')->first('email'),
        );
        $this->assertGuest();
    }

    public function test_login_is_throttled_to_five_attempts_per_minute(): void
    {
        $this->customerUser();
        $attempt = fn () => $this->post(route('login.store'), ['email' => 'sinta@example.com', 'password' => 'salah-salah']);

        foreach (range(1, 5) as $ignored) {
            $attempt()->assertStatus(302);
        }

        $attempt()->assertStatus(429);
        $this->post(route('login.store'), ['email' => 'sinta@example.com', 'password' => self::PASSWORD])->assertStatus(429);
    }

    public function test_password_reset_by_mailed_link(): void
    {
        Notification::fake();
        $user = $this->customerUser();

        $this->post(route('password.email'), ['email' => 'sinta@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetCustomerPassword::class, function (ResetCustomerPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => 'sinta@example.com']))
            ->assertOk()
            ->assertSee('sinta@example.com');

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'sinta@example.com',
            'password' => 'password-baru-9', 'password_confirmation' => 'password-baru-9',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('password-baru-9', $user->fresh()->password));

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'sinta@example.com',
            'password' => 'password-baru-8', 'password_confirmation' => 'password-baru-8',
        ])->assertSessionHasErrors('email');
    }

    public function test_reset_request_reply_is_identical_for_known_and_unknown_emails(): void
    {
        Notification::fake();
        $this->customerUser();

        $known = $this->post(route('password.email'), ['email' => 'sinta@example.com']);
        $unknown = $this->post(route('password.email'), ['email' => 'hantu@example.com']);

        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
        $known->assertSessionHasNoErrors();
        $unknown->assertSessionHasNoErrors();
        Notification::assertSentToTimes(User::where('email', 'sinta@example.com')->first(), ResetCustomerPassword::class, 1);
    }

    public function test_reset_with_a_wrong_token_is_refused(): void
    {
        $user = $this->customerUser();

        $this->post(route('password.update'), [
            'token' => Str::random(60), 'email' => 'sinta@example.com',
            'password' => 'password-baru-9', 'password_confirmation' => 'password-baru-9',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_customer_account_is_refused_by_the_admin_panel(): void
    {
        $user = $this->customerUser();

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/admin/bookings')->assertForbidden();
    }

    public function test_staff_cannot_use_the_customer_login_or_password_reset(): void
    {
        $this->seed(RoleSeeder::class);
        Notification::fake();

        $staff = $this->customerUser(['email' => 'staf@example.com']);
        $staff->assignRole(User::ROLE_OWNER);

        $this->post(route('login.store'), ['email' => 'staf@example.com', 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('password.email'), ['email' => 'staf@example.com']);
        Notification::assertNothingSent();

        $token = Password::broker()->createToken($staff);
        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'staf@example.com',
            'password' => 'password-baru-9', 'password_confirmation' => 'password-baru-9',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check(self::PASSWORD, $staff->fresh()->password));
    }

    public function test_history_lists_only_the_signed_in_accounts_bookings(): void
    {
        $mine = $this->guestBooking('sinta@example.com', 'RCM-261201-MINE01');
        $other = $this->guestBooking('lain@example.com', 'RCM-261201-OTHER1', phone: '6281111111111');
        $user = $this->customerUser();
        $mine->customer->update(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk()
            ->assertSee('RCM-261201-MINE01')
            ->assertDontSee('RCM-261201-OTHER1')
            ->assertSee(route('booking.status', $mine->access_token), false)
            ->assertDontSee($other->access_token)
            ->assertSee('Lunas')
            ->assertSee('Rp 222.000')
            ->assertSee(route('booking.invoice', $mine->access_token), false);
    }

    public function test_unpaid_bookings_show_no_invoice_link_and_use_the_status_label(): void
    {
        $booking = $this->guestBooking('sinta@example.com', 'RCM-261201-PEND01', BookingStatus::PendingPayment);
        $user = $this->customerUser();
        $booking->customer->update(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('account.index'))
            ->assertSee('Menunggu Bayar')
            ->assertDontSee(route('booking.invoice', $booking->access_token), false);
    }

    public function test_empty_history_explains_itself_and_links_to_the_landing_page(): void
    {
        $this->actingAs($this->customerUser())->get(route('account.index'))
            ->assertOk()
            ->assertSee('Belum ada booking di akun ini')
            ->assertSee(route('home').'#cari', false);
    }

    public function test_history_requires_login(): void
    {
        $this->get(route('account.index'))->assertRedirect(route('login'));
    }

    public function test_profile_updates_name_and_phone_and_password(): void
    {
        $user = $this->customerUser();

        $this->actingAs($user)->put(route('account.profile.update'), ['name' => 'Sinta D.', 'phone' => '0813 1111 2222'])
            ->assertRedirect(route('account.profile'));
        $this->assertSame('Sinta D.', $user->fresh()->name);
        $this->assertSame('6281311112222', $user->fresh()->phone);

        $this->put(route('account.password.update'), [
            'current_password' => 'bukan-itu', 'password' => 'password-baru-9', 'password_confirmation' => 'password-baru-9',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('account.password.update'), [
            'current_password' => self::PASSWORD, 'password' => 'password-baru-9', 'password_confirmation' => 'password-baru-9',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('password-baru-9', $user->fresh()->password));
    }

    public function test_profile_cannot_change_email(): void
    {
        $user = $this->customerUser();

        $this->actingAs($user)->put(route('account.profile.update'), ['name' => 'Sinta', 'email' => 'curi@example.com']);

        $this->assertSame('sinta@example.com', $user->fresh()->email);
    }

    public function test_checkout_form_is_prefilled_for_a_signed_in_guest(): void
    {
        $type = UnitType::create(['name' => 'Dome', 'slug' => 'dome', 'capacity' => 2, 'base_price_weekday' => 100000, 'base_price_weekend' => 150000]);
        Unit::create(['unit_type_id' => $type->id, 'code' => 'D1', 'status' => Unit::STATUS_ACTIVE]);
        $user = $this->customerUser(['phone' => '6281234567890']);

        $this->actingAs($user)
            ->get(route('booking.cek', ['unit_type_id' => $type->id, 'check_in' => '2026-12-07', 'check_out' => '2026-12-09', 'guests' => 2]))
            ->assertOk()
            ->assertSee('value="Sinta Dewi"', false)
            ->assertSee('value="sinta@example.com"', false)
            ->assertSee('value="081234567890"', false);
    }

    public function test_booking_made_while_signed_in_is_linked_to_the_account(): void
    {
        $type = UnitType::create(['name' => 'Dome', 'slug' => 'dome', 'capacity' => 2, 'base_price_weekday' => 100000, 'base_price_weekend' => 150000]);
        Unit::create(['unit_type_id' => $type->id, 'code' => 'D1', 'status' => Unit::STATUS_ACTIVE]);
        $user = $this->customerUser();

        $this->actingAs($user)->post(route('booking.store'), $this->bookingPayload($type, 'sinta@example.com'))->assertRedirect();

        $this->assertSame($user->id, Booking::firstOrFail()->customer->user_id);
    }

    public function test_booking_with_someone_elses_email_is_not_linked_to_the_signed_in_account(): void
    {
        $type = UnitType::create(['name' => 'Dome', 'slug' => 'dome', 'capacity' => 2, 'base_price_weekday' => 100000, 'base_price_weekend' => 150000]);
        Unit::create(['unit_type_id' => $type->id, 'code' => 'D1', 'status' => Unit::STATUS_ACTIVE]);
        $user = $this->customerUser();

        $this->actingAs($user)->post(route('booking.store'), $this->bookingPayload($type, 'korban@example.com'))->assertRedirect();

        $this->assertNull(Booking::firstOrFail()->customer->user_id);
    }

    public function test_guest_checkout_still_works_without_an_account(): void
    {
        $type = UnitType::create(['name' => 'Dome', 'slug' => 'dome', 'capacity' => 2, 'base_price_weekday' => 100000, 'base_price_weekend' => 150000]);
        Unit::create(['unit_type_id' => $type->id, 'code' => 'D1', 'status' => Unit::STATUS_ACTIVE]);

        $this->post(route('booking.store'), $this->bookingPayload($type, 'tamu@example.com'))->assertRedirect();

        $this->assertNull(Booking::firstOrFail()->customer->user_id);
    }

    public function test_nav_offers_one_quiet_entry_point_matching_the_session(): void
    {
        Event::fake();

        $this->get(route('home'))->assertSee('Masuk')->assertSee(route('login'), false)->assertDontSee('Akun saya');

        $this->actingAs($this->customerUser())->get(route('home'))->assertSee('Akun saya')->assertSee(route('account.index'), false);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPayload(UnitType $type, string $email): array
    {
        return [
            'unit_type_id' => $type->id, 'check_in' => '2026-12-07', 'check_out' => '2026-12-09',
            'guests' => 2, 'quantity' => 1,
            'customer_name' => 'Sinta Dewi', 'customer_email' => $email, 'customer_phone' => '0812-3456-7890',
        ];
    }
}
