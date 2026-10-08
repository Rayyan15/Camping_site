<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament only offers a panel-wide switch for required two factor authentication.
 * This middleware narrows it to owner accounts (PRD 7.2).
 */
class EnsureOwnerHasTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $mustEnroll = config('access.owner_two_factor_required')
            && $user?->isOwner()
            && ! MultiFactorChallenge::make()->hasEnabledProviders($user);

        if ($mustEnroll) {
            return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
        }

        return $next($request);
    }
}
