<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AvailabilityCalendarRequest;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\AvailabilityCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class UnitTypeController extends Controller
{
    public function show(string $slug): View
    {
        $unitType = UnitType::with('photos')->withCount(['units' => fn ($units) => $units->where('status', Unit::STATUS_ACTIVE)])->where('slug', $slug)->firstOrFail();

        return view('public.tenda-detail', [
            'unitType' => $unitType,
            'otherTypes' => UnitType::with('photos')->whereKeyNot($unitType->id)->orderBy('base_price_weekday')->get(),
            'today' => today()->toDateString(),
            'tomorrow' => today()->addDay()->toDateString(),
            'maxNights' => (int) config('booking.max_nights'),
            'maxAdvanceDays' => (int) config('booking.max_advance_days'),
        ]);
    }

    public function availability(AvailabilityCalendarRequest $request, string $slug, AvailabilityCalendarService $calendar): JsonResponse
    {
        $unitType = UnitType::where('slug', $slug)->firstOrFail();

        if ($request->filled('check_in')) {
            return response()->json([
                'estimate' => $calendar->estimate($unitType, $request->string('check_in')->toString(), $request->string('check_out')->toString()),
            ]);
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $request->string('bulan')->toString());

        return response()->json(['month' => $month->format('Y-m')] + $calendar->forMonth($unitType, $month));
    }
}
