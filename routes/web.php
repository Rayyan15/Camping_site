<?php

use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\BookingStatusController;
use App\Http\Controllers\Public\CheckoutController;
use App\Http\Controllers\Public\InvoiceController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\QrOrderController;
use App\Http\Controllers\Public\UnitTypeController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Webhook\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/tenda/{slug}', [UnitTypeController::class, 'show'])->name('tenda.show');

Route::get('/booking/cek', [BookingController::class, 'checkAvailability'])->name('booking.cek');
Route::post('/booking', [BookingController::class, 'store'])->middleware('throttle:10,1')->name('booking.store');

Route::get('/checkout/{code}', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout/{code}/pay', [CheckoutController::class, 'pay'])->middleware('throttle:10,1')->name('checkout.pay');

Route::get('/booking/{code}', [BookingStatusController::class, 'show'])->name('booking.status');
Route::post('/booking/{code}/cancel', [BookingStatusController::class, 'cancel'])->middleware('throttle:5,1')->name('booking.cancel');
Route::get('/booking/{code}/invoice', [InvoiceController::class, 'download'])->name('booking.invoice');

Route::get('/order/{token}', [QrOrderController::class, 'show'])->name('qr.show');
Route::post('/order/{token}', [QrOrderController::class, 'store'])->middleware('throttle:10,1')->name('qr.store');
Route::get('/order/{token}/pesanan/{code}', [QrOrderController::class, 'track'])->name('qr.track');

// CSRF exclusion for this route lives in bootstrap/app.php
Route::post('/webhook/payment', [PaymentWebhookController::class, 'handle'])->name('webhook.payment');

// Fake payment screen: only ever registered on a local machine running the fake gateway.
if (app()->environment('local') && config('payment.gateway') === 'fake') {
    require __DIR__.'/dev.php';
}

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
