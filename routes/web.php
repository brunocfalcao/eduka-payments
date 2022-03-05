<?php

/*
|--------------------------------------------------------------------------
| Eduka Payments routes. For now only for paddle is used.
|--------------------------------------------------------------------------
|
*/

Route::get('/paylink', function () {
    return view('welcome');
})->name('checkout.paylink')
  ->middleware('throttle:3,1');
