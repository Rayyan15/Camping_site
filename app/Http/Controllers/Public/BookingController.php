<?php

namespace App\Http\Controllers\Public;

use App\Exceptions\MenuItemUnavailableException;
use App\Exceptions\UnitUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CheckAvailabilityRequest;
use App\Http\Requests\Public\StoreBookingRequest;
use App\Models\UnitType;
use App\Services\Account\CustomerAccountService;
use App\Services\BookingService;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly PricingService $pricing,
    ) {}

    public function checkAvailability(CheckAvailabilityRequest $request): View
    {
        $stay = $request->validated();
        $this->prefillGuestFromAccount($request);
        $unitType = UnitType::findOrFail($stay['unit_type_id']);
        $availableUnits = $this->bookingService->checkAvailability($unitType->id, $stay['check_in'], $stay['check_out']);

        $checkIn = CarbonImmutable::parse($stay['check_in']);
        $nights = $this->pricing->nightsBetween($checkIn, $stay['check_out']);

        return view('public.booking-cek', [
            'unitType' => $unitType,
            'stay' => $stay,
            'nights' => $nights,
            'serveDates' => collect(range(0, $nights - 1))->map(fn (int $offset) => $checkIn->addDays($offset)),
            'availableUnits' => $availableUnits,
            'isAvailable' => $availableUnits->isNotEmpty(),
            'stayPrice' => $this->pricing->stayTotal($unitType, $stay['check_in'], $stay['check_out']),
            'taxRate' => $this->pricing->taxRate(),
            'serveTimes' => config('booking.serve_times'),
            ...$this->bookingService->formOptions(),
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        try {
            $booking = $this->bookingService->createFromCheckout($request->validated());
        } catch (UnitUnavailableException|MenuItemUnavailableException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->user() !== null) {
            $booking->customer->linkToUser($request->user());
        }

        return redirect()->route('checkout.show', $booking->access_token);
    }

    /**
     * Signed-in guests start with their own details in the form. Anything already in old input
     * (a failed submit) wins, so a correction is never overwritten.
     */
    private function prefillGuestFromAccount(Request $request): void
    {
        $user = $request->user();

        if ($user === null || ! $user->hasVerifiedEmail()) {
            return;
        }

        $request->session()->now('_old_input', $request->session()->get('_old_input', []) + [
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => CustomerAccountService::localPhone($user->phone),
        ]);
    }
}
