<?php

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const ALLOWED_LOCALES = ['id', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', config('app.locale', 'id'));

        if (! in_array($locale, self::ALLOWED_LOCALES, true)) {
            $locale = 'id';
        }

        app()->setLocale($locale);
        CarbonImmutable::setLocale($locale);

        return $next($request);
    }
}
