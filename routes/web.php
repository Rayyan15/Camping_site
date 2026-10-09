<?php

use App\Http\Controllers\Admin\PaymentProofController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\BookingLookupController;
use App\Http\Controllers\Public\BookingStatusController;
use App\Http\Controllers\Public\CheckoutController;
use App\Http\Controllers\Public\InvoiceController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\QrOrderController;
use App\Http\Controllers\Public\UnitTypeController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Webhook\PaymentWebhookController;
use App\Http\Middleware\AuthenticateActiveUser;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/tenda/{slug}', [UnitTypeController::class, 'show'])->name('tenda.show');
Route::get('/tenda/{slug}/ketersediaan', [UnitTypeController::class, 'availability'])->middleware('throttle:availability-check')->name('tenda.ketersediaan');

Route::get('/booking/cek', [BookingController::class, 'checkAvailability'])->middleware('throttle:availability-check')->name('booking.cek');
Route::post('/booking', [BookingController::class, 'store'])->middleware('throttle:10,1')->name('booking.store');

Route::get('/checkout/{token}', [CheckoutController::class, 'show'])->middleware('throttle:booking-lookup')->name('checkout.show');
Route::post('/checkout/{token}/pay', [CheckoutController::class, 'pay'])->middleware('throttle:10,1')->name('checkout.pay');

Route::get('/cek-booking', [BookingLookupController::class, 'form'])->name('booking.find');
Route::post('/cek-booking', [BookingLookupController::class, 'find'])->middleware('throttle:booking-find')->name('booking.find.submit');

Route::get('/booking/{token}', [BookingStatusController::class, 'show'])->middleware('throttle:booking-lookup')->name('booking.status');
Route::post('/booking/{token}/cancel', [BookingStatusController::class, 'cancel'])->middleware('throttle:5,1')->name('booking.cancel');
Route::get('/booking/{token}/invoice', [InvoiceController::class, 'download'])->middleware('throttle:booking-lookup')->name('booking.invoice');

Route::get('/order/{token}', [QrOrderController::class, 'show'])->middleware('throttle:booking-lookup')->name('qr.show');
Route::post('/order/{token}', [QrOrderController::class, 'store'])->middleware('throttle:10,1')->name('qr.store');
Route::get('/order/{token}/pesanan/{code}', [QrOrderController::class, 'track'])->middleware('throttle:booking-lookup')->name('qr.track');
Route::post('/order/{token}/pesanan/{code}/bayar', [QrOrderController::class, 'pay'])->middleware('throttle:10,1')->name('qr.pay');

// CSRF exclusion for this route lives in bootstrap/app.php
Route::post('/webhook/payment', [PaymentWebhookController::class, 'handle'])->name('webhook.payment');

// Fake payment screen: only ever registered on a local machine running the fake gateway.
if (app()->environment('local') && config('payment.gateway') === 'fake') {
    require __DIR__.'/dev.php';
}

Route::get('/admin/pembayaran/{payment}/bukti', PaymentProofController::class)
    ->middleware(AuthenticateActiveUser::class)
    ->name('admin.payments.proof');

Route::middleware(AuthenticateActiveUser::class)->prefix('/admin/laporan/{type}')->group(function () {
    Route::get('/excel', [ReportExportController::class, 'excel'])->name('admin.reports.excel');
    Route::get('/pdf', [ReportExportController::class, 'pdf'])->name('admin.reports.pdf');
});

require __DIR__.'/account.php';

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
