<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.index');
        }

        return view('account.verify-email', ['email' => $request->user()->email]);
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.index');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Tautan verifikasi baru sudah dikirim. Cek kotak masuk dan folder spam.');
    }

    /**
     * Works without a session (the link is opened from a mail app) because the signed URL plus the
     * email hash already prove the visitor holds the mailbox. Binding to the id and hash keeps one
     * link from verifying another account.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->user()?->is($user)) {
            return redirect()->route('account.index')->with('status', 'Email terverifikasi.');
        }

        return redirect()->route('login')->with('status', 'Email terverifikasi. Silakan masuk.');
    }
}
