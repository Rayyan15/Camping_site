<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Only the frame and copy change here; authentication, rate limiting and the
 * multi-factor challenge stay in Filament's base page.
 */
class Login extends BaseLogin
{
    protected static string $layout = 'filament.auth.login-layout';

    public function getHeading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getHeading();
        }

        return 'Masuk ruang kerja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return 'Khusus staf dan pemilik. Pakai email kerja yang didaftarkan pemilik.';
    }
}
