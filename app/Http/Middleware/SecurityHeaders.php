<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const ADMIN_PATH = 'admin*';

    private const HSTS_MAX_AGE_SECONDS = 31536000;

    // Public pages ship no inline script or style, so no 'unsafe-inline' is needed.
    // The Midtrans hosts are form-action targets because checkout redirects there after the POST.
    private const CONTENT_SECURITY_POLICY = "default-src 'self'; "
        ."script-src 'self'; "
        ."style-src 'self'; "
        ."img-src 'self' data:; "
        ."font-src 'self'; "
        ."connect-src 'self'; "
        ."object-src 'none'; "
        ."frame-ancestors 'none'; base-uri 'self'; "
        ."form-action 'self' https://app.midtrans.com https://app.sandbox.midtrans.com";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.self::HSTS_MAX_AGE_SECONDS.'; includeSubDomains');
        }

        // Filament and Livewire rely on inline scripts, so the admin panel is left without a CSP.
        if (! $request->is(self::ADMIN_PATH)) {
            $response->headers->set('Content-Security-Policy-Report-Only', self::CONTENT_SECURITY_POLICY);
        }

        return $response;
    }
}
