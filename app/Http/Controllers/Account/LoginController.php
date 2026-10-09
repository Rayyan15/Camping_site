<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\LoginRequest;
use App\Services\Account\CustomerAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /** One message for every failure, so the form never reveals which emails have accounts. */
    private const FAILED_MESSAGE = 'Email atau password tidak cocok.';

    public function __construct(private readonly CustomerAccountService $accounts) {}

    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('account.index');
        }

        return view('account.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $signedIn = Auth::attempt($request->safe()->only(['email', 'password']) + ['is_active' => true]);

        if ($signedIn && ! $this->accounts->isCustomerAccount(Auth::user())) {
            Auth::logout();
            $signedIn = false;
        }

        if (! $signedIn) {
            return back()->withInput($request->only('email'))->withErrors(['email' => self::FAILED_MESSAGE]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda sudah keluar.');
    }
}
