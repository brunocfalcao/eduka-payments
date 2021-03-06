<?php

namespace Eduka\Payments;

use Brunocfalcao\Cerebrus\ConcernsSessionPersistence;
use Brunocfalcao\Chrono\Chrono;
use Eduka\Cube\Models\Country;
use Eduka\Cube\Services\ApplicationLog;
use Eduka\Payments\Concerns\InteractsWithProducts;
use Illuminate\Support\Facades\Request;
use ProtoneMedia\LaravelPaddle\Paddle;

class Payment
{
    public static function __callStatic($method, $args)
    {
        return PaymentService::new()->{$method}(...$args);
    }
}

class PaymentService
{
    use InteractsWithProducts;
    use ConcernsSessionPersistence;

    private $uuid;
    private $data;

    public function __construct()
    {
        $this->withPrefix('eduka:payments:payment:'.$this->product()->uuid)
             ->forceRefreshIf(function () {

                $refresh = false;

                // EDUKA_FORCE_PAYMENT_REFRESH true?
                if (env('EDUKA_FORCE_PAYMENT_REFRESH') == true) {
                    $refresh = true;
                }

                // or EDUKA_IP_SIMULATION is set?
                if (env('EDUKA_IP_SIMULATION') != null) {
                    $refresh = true;
                }

                // Product type not using session mode? Then override session.
                if (! $this->product()->using_session) {
                    $refresh = true;
                }

                return $refresh;
             })
             ->getOr(function () {
                $this->compute();

                return $this->data;
             });
    }

    public static function new(...$args)
    {
        return new self(...$args);
    }

    public function data(string $path)
    {
        return data_get($this->session(), $path);
    }

    protected function sanitizePaddlePricingData()
    {
        $data = $this->data;

        $data->paddle = new \StdClass();
        $data->paddle->price = $this->data->products[0]['price']['gross'];
        $data->paddle->currency_symbol = $this->data->products[0]['currency'];
        $data->paddle->country_iso = $this->data->customer_country;
        $data->paddle->ip = public_ip();
        $data->paddle->country_name = Country::firstWhere(
            'code',
            $data->paddle->country_iso
        )->name;

        //Update checkout data.
        $data->checkout = new \StdClass();
        $data->checkout->price = $data->paddle->price;
        $data->checkout->currency = $data->paddle->currency_symbol;

        // Remove any other product except the first key.
        $this->data->paddle->product = $this->data->products[0];
        unset($this->data->products);

        // Re-apply data into the object $data attribute.
        $this->data = $data;
    }

    /**
     * The payment data is computed, given the current product uuid.
     * This method is called when eduka needs to refresh the product
     * session payment data, given a possible product uuid refresh.
     * Meaning if we want to refresh the product payment data, we can
     * force that by refreshing the uuid in the database.
     *
     * IMPORTANT:
     * Eduka uses the paddle price.gross and not the list_price.gross
     * to get the default product price from Paddle.
     *
     * @return void
     */
    protected function compute()
    {
        /**
         * Obtain the default pricing structure from paddle.
         * Data to retain:
         * ->customer_country (used to calculate a possible PPP).
         * ->products[0]['currency'] (the default price currency).
         * ->products[0]['price']['gross'] (the default price amount).
         *
         * Result is stored in the $this->data variable.
         */
        $this->getPrices();

        /**
         * Now lets sanitize the raw object into a more structure object.
         * This time we still don't make any pricing override calculation.
         */
        $this->sanitizePaddlePricingData();

        /**
         * Next lets additionally compute the pricing model for PPP,
         * pricing override, global discounts, etc.
         *
         * The real price computation starts below.
         */

        /**
         * First thing to compute is if there is a global discount. A global
         * discount will take over the paddle net price, and apply a
         * discount percentage. The paddle net price normally should be the
         * default price, and then we should control the price global discount
         * (as example for a pre-launch marketing campaign) via the database.
         *
         * A global price discount will affect everything from here. As example,
         * if there is a PPP active, then it will be applied after the global
         * discount is applied.
         */
        if ($this->product()->discount_percentage != 0) {
            $this->data->discount = new \StdClass();

            $this->data->discount->percentage =
                $this->product()->discount_percentage;

            $this->data->discount->amount =
                $this->data->paddle->price *
                $this->data->discount->percentage / 100;

            //Update checkout data.
            $this->data->checkout->price =
                $this->data->paddle->price -
                $this->data->discount->amount;
        }

        /**
         * Next step is to compute the purchase power parity.
         * First is to check if:
         * 1. The url is ?ppp=1 or
         * 2. The products.using_ppp is true or
         * 3. .env('EDUKA_FORCE_PPP') is 1.
         *
         * If so, the we compute the ppp discount via the countries.ppp_index
         * value. To consider that the ppp discount should be applied after
         * the global discount is applied.
         */
        if ($this->usingPPP()) {
            $country = Country::firstWhere('code', $this->data->paddle->country_iso);

            $this->data->ppp = new \StdClass();
            $this->data->ppp->country_iso = $country->code;
            $this->data->ppp->country_name = $country->name;
            $this->data->ppp->discount_percentage =
                100 - $country->ppp_index * 100;

            $this->data->ppp->discount_amount =
                $this->data->checkout->price * (1 - $country->ppp_index);

            /**
             * We will now update the checkout price, will be the final price.
             * After this, the visit source can still apply a coupon, for an
             * extremely cheap price! Logically the best is not to use PPP
             * urls logic but actually to use a coupon logic (TBC) !
             */
            $this->data->checkout->price -= $this->data->ppp->discount_amount;
        }

        return $this->data;
    }

    protected function usingPPP()
    {
        /**
         * The verification of using purchase power parity is focused in
         * several possible conditions:
         * The url has /?ppp
         * The product has .using_ppp = true
         * T.
         */
        return Request::input('ppp') ||
               $this->product()->using_ppp ||
               env('EDUKA_FORCE_PPP') == true;
    }

    protected function getPrices()
    {
        /**
         * Compute ip address. On this case we need to check if the
         * products.testing_ip is not null.
         *
         * Also, if there is a EDUKA_IP_SIMULATION then it will take
         * precedence over the product.testing_ip value.
         *
         * Then we need to override the customer_ip with this testing ip
         * in case it exists.
         */
        $ip = $this->product()->testing_ip ?? public_ip();

        // Precedence override (mostly for testing reasons).
        if (env('EDUKA_IP_SIMULATION') != null) {
            $ip = env('EDUKA_IP_SIMULATION');
        }

        Chrono::category('duration-checkout')->start();

        $this->data = (object)
                (Paddle::checkout()
                ->getPrices([
                    'product_ids' => $this->product()->paddle_product_id,
                    'customer_ip' => $ip,
                ])
                ->send());

        $duration = Chrono::category('duration-checkout')->stop();
        ApplicationLog::group('duration-checkout ')->log($duration);
    }
}
