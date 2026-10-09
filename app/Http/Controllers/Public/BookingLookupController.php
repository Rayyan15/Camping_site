<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\FindBookingRequest;
use App\Services\BookingLookupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BookingLookupController extends Controller
{
    /** One message for every miss, so the form never reveals whether a code exists. */
    private const MISMATCH_MESSAGE = 'Data tidak cocok. Periksa kode booking dan data pendukungnya.';

    public function __construct(private readonly BookingLookupService $lookup) {}

    public function form(): View
    {
        return view('public.booking-find');
    }

    public function find(FindBookingRequest $request): RedirectResponse
    {
        $booking = $this->lookup->find($request->validated('code'), $request->validated('proof'));

        if ($booking === null) {
            return back()->withInput($request->only('code'))->with('error', self::MISMATCH_MESSAGE);
        }

        return redirect()->route('booking.status', $booking->access_token);
    }
}
