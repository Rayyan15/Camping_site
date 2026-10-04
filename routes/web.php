<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhook\PaymentWebhookController;

use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\UnitTypeController;
use App\Http\Controllers\Public\BookingController;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/tenda/{slug}', [UnitTypeController::class, 'show'])->name('tenda.show');
Route::get('/booking/cek', [BookingController::class, 'checkAvailability'])->name('booking.cek');
Route::post('/booking', [BookingController::class, 'store'])->name('booking.store');
Route::get('/booking/success/{code}', [BookingController::class, 'success'])->name('booking.success');

// Route webhook wajib dikecualikan dari CSRF di bootstrap/app.php nantinya
Route::post('/webhook/payment', [PaymentWebhookController::class, 'handle'])->name('webhook.payment');
