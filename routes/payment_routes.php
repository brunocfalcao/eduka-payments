<?php

use App\Http\Middleware\VerifyCsrfToken;
use Eduka\Payments\Http\Controllers\PaymentController;
use Eduka\Payments\Http\Controllers\PaymentRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/purchase', [PaymentController::class, 'redirectToCheckoutPage'])
    ->middleware('throttle:payment')
    ->name('purchase.view');

Route::get('/redirect-callback/{nonce}', [PaymentRedirectController::class, 'index'])
    ->name('purchase.callback');

Route::post('/handle-webhook', [PaymentController::class, 'handleWebhook'])
    ->withoutMiddleware([VerifyCsrfToken::class]);
