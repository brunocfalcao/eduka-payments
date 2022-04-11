<?php

namespace Eduka\Payments;

use Eduka\Analytics\Services\Affiliate;
use Eduka\Payments\Concerns\InteractsWithProducts;
use ProtoneMedia\LaravelPaddle\Paddle;

class Paylink
{
    public static function __callStatic($method, $args)
    {
        return PaylinkService::new()->{$method}(...$args);
    }
}

class PaylinkService
{
    use InteractsWithProducts;

    private $uuid;
    private $affiliates = [];
    private $payload = [];
    private $passthrough = [];
    private $data;

    public function __construct()
    {
        //
    }

    public static function new(...$args)
    {
        return new self(...$args);
    }

    protected function refresh()
    {
        // Default instanciation.
        $this->data = new \StdClass();
        $this->data->used_session = true;

        // By default, the data refresh is not needed.
        $refresh = false;

        // .env true? Then override session.
        if (env('REFRESH_PAYLINK_SESSION') == true) {
            $refresh = true;
        }

        /**
         * The paylink session mode is also managed from the product session
         * mode. Meaning when it's disabled, it also disables the session
         * verification for the paylink.
         */
        if (! $this->product()->using_session) {
            $refresh = true;
        }

        // Obtain the current product uuid to check the paylink session key.
        $this->uuid = $this->product()->uuid;

        if (! session()->has('eduka-payments:paylink:'.$this->uuid)) {
            $refresh = true;
        }

        if ($refresh) {
            $this->compute();
            $this->store();
            $this->data->used_session = false;

            return;
        }

        $this->data = session('eduka-payments:paylink:'.$this->uuid);
    }

    protected function compute()
    {
        /**
         * Obtain the paylink url structure from paddle.
         * Data to retain:
         * ->url : The paylink url to start the checkout.
         * ->success : If the paylink was succesfully generated.
         *
         * Result is stored in the $this->data variable.
         */
        $this->callPaddleApi();
    }

    protected function store()
    {
        $uuid = $this->uuid;
        session(["eduka-payments:paylink:$uuid" => $this->data]);

        return $this;
    }

    public function payload(array $payload)
    {
        $this->payload = $payload;

        return $this;
    }

    public function passthrough(array $passthrough)
    {
        $this->passthrough = $passthrough;

        return $this;
    }

    protected function computeAffiliatesCommissions()
    {
        /**
         * Affiliates are entities that will receive a commission based on
         * the price that the visitor bought the course.
         *
         * An affiliate will always be of 2 types:
         * temporary - Will receive a commission, based on a start and end date.
         * fixed - Will always receive a specific commission for each sale.
         *
         * The temporary affiliate will have a commission priority over the
         * fixed affiliate. As example, let's say the sale price is 100 USD.
         * And we have a temporary affiliate that receives 30%, and one fixed
         * affiliate that receives always 50% commission.
         *
         * So, the payment commissions will be:
         * 100 * 30% (30 USD) from the temporary affiliate.
         * 70 * 50% (35 USD) for the fixed affiliate.
         * So, the affiliates paddle structure will be:
         * temporary,0.30 => Will receive 30 USD.
         * fixed,0.35 => Will receive 35 USD.
         *
         * The temporary affiliate is obtained from the affiliates session
         * variable.
         */

        /**
         * Array, composed of each cardinal index of associate arrays:
         * ['affiliate_id' => YYY,
         *  'vendor_id' => XXX,
         *  'type' => 'fixed|non-fixed',
         *  'amount' => The amount over the REAL commission percentage
         *  'percentage' => 0.3, // e.g. The planned price commission percentage
         *  'price_percentage' => 0.21 // The real price percentage on the
         *  remaining product price after subtracting the temporary affiliate
         *  if it exists. e.g.].
         */
        $affiliates = [];

        /**
         * Check if we have a temporary affiliate (a referrer). If so,
         * the commission percentage is absolute. Meaning there is
         * no adjustments in case it still exists fixed affiliates.
         */
        $referrer = Affiliate::fromReferrer();

        /**
         * Get possible fixed affiliates.
         */
        $fixedAffiliates = Affiliate::fixed();

        dd($fixedAffiliates);

        // Get current price for the current canonical.
        $this->getCheckoutPrice();

        //Variable just to keep a remaining price after needed subtractions.
        $remainingPrice = $this->price;

        if ($referrer) {
            // Obtain the absolute commission amount.
            $amount = round($this->price * $referrer->commission_percentage / 100, 2);

            // Add initial data to the affiliates array. Lot to come yet ...
            $affiliates[] = [
                'vendor_id' => $referrer->paddle_vendor_id,
                'affiliate_id' => $referrer->id,
                'type' => 'not-fixed',
                'amount' => $amount,
                'percentage' => $referrer->commission_percentage,
                'price_percentage' => $referrer->commission_percentage
            ];

            /**
             * If there are fixed affiliates for this product id, then they can
             * only slice the remaining part of the price, after deducted the
             * fixed referrer commission. So kets subtract the current
             * commission amount to the remaining price.
             */
            $remainingPrice -= $amount;
        }

        /**
         * Time to check the fixed affiliates commissions. In this case
         * the commission is always applied to the remaining price. First
         * we apply the percentages to calculate the amounts per each
         * fixed affiliate, for the remaining price cake. Then we re-run it
         * to calculate the percentages for the paddle api, using those
         * sliced amounts over the total product price.
         *
         * Computation:
         * The Non-fixed affiliate gets its commission from the total price,
         * in this case 30 USD. Then, the remaining 70 USD are splitted into
         * the percentages of each affiliate. Meaning, F1 will get 35 USD
         * and F2 will get 23.10 USD. At the end we need to calculate
         * the 30 USD, the 35 USD and the 23.10 USD from the 100 USD (total
         * price). In this case, the paddle vendor affiliate percentages
         * will be NF1:0.3, NF2:0.35, NF3: 0.23.
         */
        $affiliates = Affiliate::canonical($this->canonical)
                               ->affiliates();

        dd($this->price, $remainingPrice, $affiliates);
    }

    protected function getCheckoutPrice()
    {
        $this->price = Payment::canonical($this->canonical)
                              ->data()
                              ->checkout
                              ->price;
    }

    protected function callPaddleApi()
    {
        /**
         * That will be the price for the paylink. Eduka uses the
         * product price from Paddle, but then applies computations that
         * are needed (ppp, global discounts, etc).
         */
        $this->getCheckoutPrice();

        /**
         * Application of override/default values on the passthrough.
         * What's passed on the ->get() method will always
         * have priority over the default values from this class.
         */
        $passthrough = array_merge(['ip' => public_ip()], $this->passthrough);

        /**
         * The affiliates computation uses the referrer that can be in session.
         * This uses the eduka-analytics referrer session logic.
         */
        $affiliates = $this->computeAffiliatesCommissions();

        //dd($this->passthrough, $this->payload, $this->affiliates);

        $returnUrl = config('eduka-nereus.paddle.return_url');

        $this->payLink = Paddle::$this->product()
                               ->generatePayLink()
                               ->productId($this->product()->paddle_product_id)
                               ->returnUrl($returnUrl)
                               ->quantityVariable(0)
                               ->quantity(1)
                               ->prices(["USD:{$price}"]);

        if ($passthrough) {
            $this->payLink->passthrough(json_encode($passthrough));
        }

        if ($affiliates) {
            $this->payLink->affiliates();
        }

        /*
        $this->payLink = Paddle::$this->product()
             ->generatePayLink()
             ->productId(env('PADDLE_PRODUCT_ID'))
             ->returnUrl(url('paddle/thanks'))
             ->quantityVariable(0)
             ->quantity(1)
             ->prices(["USD:{$price}"]);

        $this->payLink->affiliates([$vendor_id:0.50]]);
        $this->payLink->passthrough(json_encode($passthrough));
        $this->payLink = $this->payLink->send();
        */
    }

    public function data()
    {
        $this->refresh();

        return $this->data;
    }
}
