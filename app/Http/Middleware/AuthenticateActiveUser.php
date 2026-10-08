<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;

/**
 * Filament's Authenticate answers 403 for accounts that may no longer enter the panel.
 * A deactivated account is instead logged out and sent back to the login page.
 */
class AuthenticateActiveUser extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if ($guard->check() && ! $guard->user()->is_active) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        parent::authenticate($request, $guards);
    }
}
