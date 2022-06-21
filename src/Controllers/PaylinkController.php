<?php

namespace Eduka\Payments\Controllers;

use App\Http\Controllers\Controller;
use Eduka\Payments\Paylink;

class PaylinkController extends Controller
{
    public function __construct()
    {
        //
    }

    public function checkout()
    {
        /**
         * This route generates a paylink url, and redirects to that url.
         *
         * We also need to check if the HTTP_REFERER is the base domain for the
         * contexted course, for security reasons, so no one can directly call the
         * /paylink url without sourcing from an approved course domain.
         */
        if (! array_key_exists('HTTP_REFERER', $_SERVER)) {
            throw new \Exception('Security request error. Please retry again');
        }

        if (course()->domain != domain()) {
            throw new \Exception('Security course domain incompatibility error. Please rety again');
        }

        return redirect(Paylink::data('url'));
    }
}
