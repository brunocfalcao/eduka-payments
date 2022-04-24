<?php

namespace Eduka\Payments\Listeners;

/**
 * Listener called via the Paddle API webhook.
 *
 * Processes the visitor payment and relates visitor and user too.
 * Stores all the paddle information on the database.
 */
class ProcessPayment
{
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(PaymentSucceeded $event)
    {
        info('paddle webhook api called with ' . $event->all());
    }
}

/*
use App\Models\PaddleLog;
use App\Models\User;
use Illuminate\Http\Request;
use ProtoneMedia\LaravelPaddle\Events\PaymentSucceeded as EventPaymentSucceeded;
use ProtoneMedia\LaravelPaddle\Events\PaymentSucceeded;

class PaymentSucceeded
{
    public $request;

    /**
     * Create the event listener.
     *
     * @param  Request  $request
     * @return void
public function __construct(Request $request)
{
    $this->request = $request;
}

    /**
     * Handle the event.
     *
     * @param  Login  $event
     * @return void
    public function handle(EventPaymentSucceeded $event)
    {
        /**
         * $event->all() returns an array with all the data
         * that was given from the Paddle log webhook.
         * We need make some transformations to be able to
         * save it correctly in the paddle_log table.
         *
         * paddle_log.data => Saves all the request.
         * paddle_log.ip => Saves passthrough.ip data.
         *
         *
         * @var [type]
        $data = $event->all();
        $data['data'] = $event->all();

        if (! empty($data['passthrough'])) {
            $data['passthrough'] = json_decode($data['passthrough'], true);

            if (is_array($data['passthrough'])) {
                if (array_key_exists('ip', $data['passthrough'])) {
                    $data['ip'] = $data['passthrough']['ip'];
                }

                if (array_key_exists('affiliate_id', $data['passthrough'])) {
                    $data['affiliate_id'] = $data['passthrough']['affiliate_id'];
                }
            }
        }

        $paddleLog = PaddleLog::create($data);

        // Create user from purchase.
        $user = User::createFromPurchase($paddleLog);
    }
}
*/
