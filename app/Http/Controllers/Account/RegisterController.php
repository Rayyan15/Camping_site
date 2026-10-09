<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\RegisterRequest;
use App\Services\Account\CustomerAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __construct(private readonly CustomerAccountService $accounts) {}

    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('account.index');
        }

        return view('account.register');
    }

    /**
     * The reply is the same whether or not the email already has an account, and the visitor is
     * not signed in until the email is verified, so the form cannot be used to probe addresses.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $this->accounts->register($request->validated());

        return redirect()->route('login')->with(
            'status',
            'Pendaftaran diterima. Jika email ini belum punya akun terverifikasi, kami mengirim tautan verifikasi ke sana. Buka tautannya, lalu masuk.'
        );
    }
}
