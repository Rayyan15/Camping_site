<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    private const ALLOWED_LOCALES = ['id', 'en'];

    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, self::ALLOWED_LOCALES, true)) {
            session(['locale' => $locale]);
        }

        return redirect()->back();
    }
}
