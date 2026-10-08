<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\UnitType;
use Illuminate\Contracts\View\View;

class UnitTypeController extends Controller
{
    public function show(string $slug): View
    {
        $unitType = UnitType::with('photos')->where('slug', $slug)->firstOrFail();

        return view('public.tenda-detail', [
            'unitType' => $unitType,
            'today' => today()->toDateString(),
            'tomorrow' => today()->addDay()->toDateString(),
        ]);
    }
}
