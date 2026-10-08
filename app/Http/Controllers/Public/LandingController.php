<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\LandingSearchRequest;
use App\Models\UnitType;
use App\Services\BookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class LandingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function index(LandingSearchRequest $request): View
    {
        $unitTypes = UnitType::with('photos')
            ->withCount('units')
            ->orderBy('base_price_weekday')
            ->get();

        $availability = $request->hasStay()
            ? $this->availableUnitsByType($unitTypes, $request->string('check_in'), $request->string('check_out'))
            : null;

        return view('welcome', [
            'unitTypes' => $unitTypes,
            'availability' => $availability,
            'totalUnits' => $unitTypes->sum('units_count'),
            'holdMinutes' => config('booking.hold_minutes'),
            'search' => $request->safe()->only(['check_in', 'check_out', 'guests']),
            'structuredData' => $this->structuredData($unitTypes),
        ]);
    }

    /**
     * @param  Collection<int, UnitType>  $unitTypes
     * @return Collection<int, int> free unit count keyed by unit type id
     */
    private function availableUnitsByType(Collection $unitTypes, string $checkIn, string $checkOut): Collection
    {
        return $unitTypes->mapWithKeys(fn (UnitType $type) => [
            $type->id => $this->bookingService->checkAvailability($type->id, $checkIn, $checkOut)->count(),
        ]);
    }

    /**
     * Schema.org Campground markup. Fields without data are left out instead of guessed.
     *
     * @param  Collection<int, UnitType>  $unitTypes
     * @return array<string, mixed>
     */
    private function structuredData(Collection $unitTypes): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Campground',
            'name' => config('site.name'),
            'url' => route('home'),
            'image' => [asset('images/og-default.jpg'), asset('images/hero-canvas-tent-1600.webp')],
            'numberOfRooms' => $unitTypes->sum('units_count'),
        ];

        if ($unitTypes->isNotEmpty()) {
            $lowest = $unitTypes->min('base_price_weekday');
            $highest = max($unitTypes->max('base_price_weekday'), $unitTypes->max('base_price_weekend'));
            $data['priceRange'] = 'Rp '.number_format($lowest, 0, ',', '.').' - Rp '.number_format($highest, 0, ',', '.');
        }

        if (config('site.address')) {
            $data['address'] = ['@type' => 'PostalAddress', 'streetAddress' => config('site.address')];
        }

        if (config('site.whatsapp_number')) {
            $data['telephone'] = '+'.config('site.whatsapp_number');
        }

        $sameAs = array_values(array_filter([config('site.instagram_url'), config('site.maps_url')]));
        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }
}
