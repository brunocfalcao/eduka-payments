<?php

namespace Eduka\Payments\Listeners;

use App\Models\User;
use Illuminate\Http\Request;
use ProtoneMedia\LaravelPaddle\Events\PaymentSucceeded as EventPaymentSucceeded;

class PaymentSucceeded
{
    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(EventPaymentSucceeded $event)
    {
        info($event);

        /**
         * Called from the paddle webhook.
         *
         * Operations:
         * - Verify if the passthrough has a 'auth_hashcode' value valid in
         *   the database.
         * - Verify if the post request has the minimum data keys.
         * - Re-structure the $event object into a sanitized collection.
         * - Validate the mandatory data (using a validation object).
         * - Connect the visitor id with the user id (table visitors).
         * - Send thank you email to visitor.
         */
    }
}
