<?php

use Eduka\Payments\Controllers\PaylinkController;

/*
|--------------------------------------------------------------------------
| Eduka Payments routes. For now only for paddle is used.
|--------------------------------------------------------------------------
|
*/

/**
 * This route generates a paylink url, and redirects to that url.
 * It also uses a throttling middleware (3 requests per minute) to avoid
 * credit card unusal usage attacks.
 *
 * We also need to check if the HTTP_REFERER is the base domain for the
 * contexted course, for security reasons, so no one can directly call the
 * /paylink url without sourcing from an approved course domain.
 */
Route::get('/paylink', [PaylinkController::class, 'checkout'])
     ->name('checkout.paylink')
     ->middleware('throttle:3,1');

/*

 $referrer = $_SERVER['HTTP_REFERER'];

  dd(course()->domain, domain($referrer), $_SERVER);

  return redirect(Paylink::data('url'));

)
 */
