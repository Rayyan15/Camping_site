<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\EmailVerificationController;
use App\Http\Controllers\Account\LoginController;
use App\Http\Controllers\Account\PasswordResetController;
use App\Http\Controllers\Account\RegisterController;
use Illuminate\Support\Facades\Route;

/*
| Optional customer accounts (PRD FR-20). Booking never requires one. Route names login,
| password.* and verification.* are the ones Laravel's auth middleware and notifications look up.
*/
Route::prefix('akun')->group(function () {
    Route::get('/daftar', [RegisterController::class, 'create'])->name('account.register');
    Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:account-register')->name('account.register.store');

    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:account-login')->name('login.store');
    Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

    Route::get('/lupa-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetController::class, 'email'])->middleware('throttle:account-reset')->name('password.email');
    Route::get('/atur-ulang/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/atur-ulang', [PasswordResetController::class, 'update'])->middleware('throttle:account-reset')->name('password.update');

    Route::get('/verifikasi', [EmailVerificationController::class, 'notice'])->middleware('auth')->name('verification.notice');
    Route::post('/verifikasi/kirim', [EmailVerificationController::class, 'send'])->middleware(['auth', 'throttle:account-verification'])->name('verification.send');
    Route::get('/verifikasi/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:account-verification'])->name('verification.verify');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', [AccountController::class, 'index'])->name('account.index');
        Route::get('/profil', [AccountController::class, 'profile'])->name('account.profile');
        Route::put('/profil', [AccountController::class, 'updateProfile'])->name('account.profile.update');
        Route::put('/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    });
});
