<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AuthenticateActiveUser;
use App\Http\Middleware\EnsureOwnerHasTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->databaseNotifications()
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(EnsureOwnerHasTwoFactor::class)
            ->brandName(config('site.name'))
            ->brandLogo(asset(config('site.logo')))
            // Navy line art disappears on the dark panel, so dark mode gets the badge on a cream disc.
            ->darkModeBrandLogo(asset('images/brand/halimun-highland-160-on-dark.png'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('favicon-32.png'))
            // Light, dark or follow the device, one click away instead of buried in the user menu.
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): string => Blade::render('<x-filament-panels::theme-switcher />'))
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                // Dashboard is auto-discovered
            ])
            ->navigationGroups([
                'Operasional',
                'Menu',
                'Manajemen Tenda',
                'SDM & Karyawan',
                'Laporan & Keuangan',
                'Pengaturan',
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                //
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                SecurityHeaders::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateActiveUser::class,
            ]);
    }
}
