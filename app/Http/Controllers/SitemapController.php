<?php

namespace App\Http\Controllers;

use App\Models\UnitType;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    private const CACHE_KEY = 'sitemap.xml';

    private const CACHE_SECONDS = 3600;

    private const DISALLOWED_PATHS = ['/admin', '/checkout', '/booking/', '/order/', '/dev'];

    public function sitemap(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => view('sitemap', [
            'homeUrl' => route('home'),
            'unitTypes' => UnitType::orderBy('base_price_weekday')->get(['slug', 'updated_at']),
        ])->render());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Allow: /'];

        foreach (self::DISALLOWED_PATHS as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
