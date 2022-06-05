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
   * It also uses a throttling middleware (3 requests per minute) to avoid
   * credit card.
   */
})->name('checkout.paylink')
  ->middleware('throttle:3,1');
