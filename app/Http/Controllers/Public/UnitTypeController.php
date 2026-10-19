<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AvailabilityCalendarRequest;
use App\Http\Requests\Public\CatalogRequest;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\AvailabilityCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class UnitTypeController extends Controller
{
    public function index(CatalogRequest $request): View
    {
        $allTypes = UnitType::with('photos')->withCount(['units' => fn ($units) => $units->where('status', Unit::STATUS_ACTIVE)])->get();
        $guests = $request->integer('tamu');
        $facilities = $request->array('fasilitas');
        $sort = $request->string('urutkan', 'termurah')->toString();

        // ponytail: filtered in memory, the camp has 7 tent types; move to query scopes if the catalogue grows past a few dozen.
        $unitTypes = $allTypes
            ->when($guests > 0, fn ($types) => $types->where('capacity', '>=', $guests))
            ->filter(fn (UnitType $type) => array_diff($facilities, $type->facilities ?? []) === [])
            ->sortBy(match ($sort) {
                'termahal' => [['base_price_weekday', 'desc']],
                'kapasitas' => [['capacity', 'desc'], ['base_price_weekday', 'asc']],
                default => [['base_price_weekday', 'asc']],
            })
            ->values();

        return view('public.tenda-katalog', [
            'unitTypes' => $unitTypes,
            'totalTypes' => $allTypes->count(),
            'facilityOptions' => $allTypes->pluck('facilities')->flatten()->filter()->unique()->sort()->values(),
            'filters' => ['tamu' => $guests ?: null, 'urutkan' => $sort, 'fasilitas' => $facilities],
        ]);
    }

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
