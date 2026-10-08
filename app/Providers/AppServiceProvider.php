<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundPolicy;
use App\Models\Setting;
use App\Models\SpecialPrice;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\MidtransGateway;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Models whose changes are written to the audit trail (PRD 6.1). */
    private const AUDITED_MODELS = [
        Booking::class, Payment::class, Refund::class, UnitType::class, SpecialPrice::class,
        Setting::class, Unit::class, Addon::class, RefundPolicy::class, User::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => match (config('payment.gateway')) {
            'midtrans' => new MidtransGateway,
            default => new FakeGateway,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureProxies();
        $this->forceHttpsInProduction();

        Relation::enforceMorphMap(config('morph_map'));

        foreach (self::AUDITED_MODELS as $model) {
            $model::observe(ActivityLogObserver::class);
        }
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
