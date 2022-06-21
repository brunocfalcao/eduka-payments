<?php

namespace Eduka\Payments\Listeners;

use App\Models\User;
use Eduka\Cube\Services\ApplicationLog;
use Eduka\Payments\Models\PaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ProtoneMedia\LaravelPaddle\Events\PaymentSucceeded as EventPaymentSucceeded;

class ProcessPayment
{
    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(EventPaymentSucceeded $event)
    {

        /**
         * Convert the webhook data into an stdClass, and convert the
         * passthrough into an array.
         */
        $data = (object) $event->all();
        $data->passthrough = json_decode($data->passthrough, true);

        /**
         * The passthrough MUST contain 2 keys:
         * visit_id
         * auth_hashcode.
         */
        if (! array_key_exists('visit_id', $data->passthrough) ||
            ! array_key_exists('auth_hashcode', $data->passthrough)) {
            ApplicationLog::properties($event->all())
                      ->group('checkout error')
                      ->log('Passthrough without minimum control keys (visit_id and/or auth_hashcode)')
                      ->throw('Passthrough without minimum control keys (visit_id and/or auth_hashcode)');
        }

        // Verify if the auth_hashcode exists.

        /**
         * There are several major steps now:
         * 1. Insert the data sanitized into the payments_webhook table.
         * 2. Insert an application log entry to signal the purchase.
         * 3. If the checkout data is not already an user, then create the
         *    user, generate a reset password link. Give permissions to
         *    access the course data.
         *    If the checkout data points to an already created user, who
         *    purchased another course, we just need to give access to this
         *    new course (via the passed product id).
         *
         * 4. Send notification to the user, thanking about buying the course.
         */
        $webhook = new PaymentWebhook();

        // Insert data "by hand". Not mass filled, for security reasons.

        dd($data);

        //DB::table('payments_webhook')->insert($data);

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
