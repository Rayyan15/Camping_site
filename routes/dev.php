<?php

use App\Http\Controllers\Dev\FakePaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/dev/pay/{reference}', [FakePaymentController::class, 'show'])->name('dev.pay.show');
Route::post('/dev/pay/{reference}', [FakePaymentController::class, 'settle'])->name('dev.pay');
