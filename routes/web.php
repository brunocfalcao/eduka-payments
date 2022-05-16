<?php

/*
|--------------------------------------------------------------------------
| Eduka Payments routes. For now only for paddle is used.
|--------------------------------------------------------------------------
|
*/

Route::get('/paylink', function () {

  /**
   * This route generates a paylink url, and redirects to that url.
   */
})->name('checkout.paylink')
  ->middleware('throttle:3,1');
