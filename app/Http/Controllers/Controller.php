<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Script uploads navigate themselves: a followed redirect would use up the flash message
     * before the guest's browser loads the page that shows it.
     */
    protected function redirectForUpload(Request $request, RedirectResponse $redirect): RedirectResponse|JsonResponse
    {
        return $request->wantsJson()
            ? response()->json(['redirect' => $redirect->getTargetUrl()])
            : $redirect;
    }
}
