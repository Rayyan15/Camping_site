<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Models\Booking;
use App\Services\Account\CustomerAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    private const BOOKINGS_PER_PAGE = 10;

    /**
     * The only query is scoped to guest records owned by the signed-in account, and every link
     * out carries the booking's own access token, so no URL here takes a record id.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $bookings = Booking::query()
            ->whereIn('customer_id', $user->customers()->select('customers.id'))
            ->with('bookingUnits.unit.unitType')
            ->latest('check_in')
            ->latest('id')
            ->paginate(self::BOOKINGS_PER_PAGE);

        return view('account.index', ['user' => $user, 'bookings' => $bookings]);
    }

    public function profile(Request $request): View
    {
        $user = $request->user();

        return view('account.profile', [
            'user' => $user,
            'phone' => CustomerAccountService::localPhone($user->phone),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('account.profile')->with('status', 'Data akun diperbarui.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return redirect()->route('account.profile')->with('status', 'Password diganti.');
    }
}
