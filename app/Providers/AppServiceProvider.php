<?php

namespace App\Providers;

use App\Contracts\AttendanceScoreSource;
use App\Contracts\FingerprintLogSource;
use App\Contracts\PaymentGateway;
use App\Contracts\WhatsAppGateway;
use App\Models\Addon;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\DiningSpot;
use App\Models\Evaluation;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundPolicy;
use App\Models\Setting;
use App\Models\SpecialPrice;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Observers\BookingWhatsAppObserver;
use App\Observers\DeletionGuardObserver;
use App\Services\AttendanceRecapScoreSource;
use App\Services\FileFingerprintLogSource;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\MidtransGateway;
use App\Services\WhatsApp\FonnteGateway;
use App\Services\WhatsApp\LogWhatsAppGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /** Models whose changes are written to the audit trail (PRD 6.1). */
    private const AUDITED_MODELS = [
        Booking::class, Payment::class, Refund::class, UnitType::class, SpecialPrice::class,
        Setting::class, Unit::class, Addon::class, RefundPolicy::class, User::class,
        Order::class, MenuItem::class, Attendance::class, Evaluation::class, DiningSpot::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AttendanceScoreSource::class, AttendanceRecapScoreSource::class);

        $this->app->bind(PaymentGateway::class, fn () => match (config('payment.gateway')) {
            'midtrans' => new MidtransGateway,
            default => new FakeGateway,
        });

        $this->app->bind(WhatsAppGateway::class, fn ($app) => match (config('whatsapp.driver')) {
            'fonnte' => $app->make(FonnteGateway::class),
            default => $app->make(LogWhatsAppGateway::class),
        });
        $this->app->bind(FingerprintLogSource::class, fn () => new FileFingerprintLogSource((string) config('attendance.source_path')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureProxies();
        $this->forceHttpsInProduction();
        $this->configureRateLimiters();
        $this->configureAccountRateLimiters();

        Relation::enforceMorphMap(config('morph_map'));

        foreach (self::AUDITED_MODELS as $model) {
            $model::observe(ActivityLogObserver::class);
        }

        Booking::observe(BookingWhatsAppObserver::class);

        foreach ([Customer::class, Unit::class, Booking::class] as $model) {
            $model::observe(DeletionGuardObserver::class);
        }
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('booking-lookup', fn (Request $request) => [
            Limit::perMinute(config('booking.throttle.lookup_per_code'))
                ->by($request->ip().'|'.$request->route('code').$request->route('token')),
            Limit::perMinute(config('booking.throttle.lookup_per_ip'))->by($request->ip()),
        ]);

        RateLimiter::for('booking-find', fn (Request $request) => Limit::perMinute(
            config('booking.throttle.find_per_ip')
        )->by($request->ip()));

        RateLimiter::for('availability-check', fn (Request $request) => Limit::perMinute(
            config('booking.throttle.availability_per_ip')
        )->by($request->ip()));
    }

    private function configureAccountRateLimiters(): void
    {
        $perIpAndEmail = fn (string $configKey) => fn (Request $request) => Limit::perMinute(
            config("auth.customer_throttle.{$configKey}")
        )->by($request->ip().'|'.Str::lower((string) $request->input('email')));

        RateLimiter::for('account-login', $perIpAndEmail('login_per_minute'));
        RateLimiter::for('account-register', $perIpAndEmail('register_per_minute'));
        RateLimiter::for('account-reset', $perIpAndEmail('reset_per_minute'));

        RateLimiter::for('account-verification', fn (Request $request) => Limit::perMinute(
            config('auth.customer_throttle.verification_per_minute')
        )->by((string) ($request->user()?->id ?? $request->ip())));
    }

    private function configureProxies(): void
    {
        $trusted = config('proxy.trusted');

        if ($trusted !== []) {
            TrustProxies::at($trusted === ['*'] ? '*' : $trusted);
        }
    }

    private function forceHttpsInProduction(): void
    {
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
