<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ForgotPasswordRequest;
use App\Http\Requests\Account\ResetPasswordRequest;
use App\Models\User;
use App\Services\Account\CustomerAccountService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private const INVALID_LINK_MESSAGE = 'Tautan atur ulang tidak valid atau sudah kedaluwarsa. Minta tautan baru.';

    public function __construct(private readonly CustomerAccountService $accounts) {}

    public function request(): View
    {
        return view('account.forgot-password');
    }

    /**
     * Staff accounts are skipped silently: their passwords follow the stricter back office rules.
     */
    public function email(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = $request->validated('email');
        $user = User::where('email', $email)->first();

        if ($user === null || $this->accounts->isCustomerAccount($user)) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', 'Jika email itu terdaftar, tautan atur ulang password sudah kami kirim. Tautan berlaku '
            .config('auth.passwords.users.expire').' menit.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('account.reset-password', [
            'token' => $token,
            'email' => Str::lower((string) $request->query('email')),
        ]);
    }

    public function update(ResetPasswordRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user !== null && ! $this->accounts->isCustomerAccount($user)) {
            return $this->invalidLink($request);
        }

        $status = Password::reset(
            $request->safe()->only(['email', 'password', 'token']),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->invalidLink($request);
        }

        return redirect()->route('login')->with('status', 'Password sudah diganti. Silakan masuk dengan password baru.');
    }

    private function invalidLink(Request $request): RedirectResponse
    {
        return back()->withInput($request->only('email'))->withErrors(['email' => self::INVALID_LINK_MESSAGE]);
    }
}
