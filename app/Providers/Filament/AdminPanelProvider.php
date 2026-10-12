<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\BrandAvatarProvider;
use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\AuthenticateActiveUser;
use App\Http\Middleware\EnsureOwnerHasTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
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
            ->defaultAvatarProvider(BrandAvatarProvider::class)
            // Same body face as the public site, served from the build instead of a font CDN.
            ->font('Plus Jakarta Sans', provider: LocalFontProvider::class)
            ->favicon(asset('favicon-32.png'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): string => view('filament.theme-toggle')->render())
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('filament.brand-tokens')->render().view('filament.panel-chrome')->render())
            ->sidebarCollapsibleOnDesktop()
            // Brand forest as the only accent, and a forest-tinted neutral as gray so every surface shares one hue.
            ->colors([
                'primary' => config('brand.forest'),
                'gray' => config('brand.neutral'),
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
