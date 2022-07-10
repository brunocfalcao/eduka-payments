<?php

namespace Eduka\Payments;

use Brunocfalcao\Cerebrus\ConcernsSessionPersistence;
use Brunocfalcao\Chrono\Chrono;
use Eduka\Analytics\Services\Affiliate;
use Eduka\Analytics\Services\Referrer;
use Eduka\Analytics\Services\Visit;
use Eduka\Cube\Services\ApplicationLog;
use Eduka\Payments\Concerns\InteractsWithProducts;
use Eduka\Payments\Hashcode;
use Illuminate\Support\Str;
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
    use ConcernsSessionPersistence;

    private $uuid;
    private $passthrough = [];
    private $data;
    private $price;

    public function __construct()
    {
        // Default instanciation.
        $this->data = new \StdClass();

        // Get product uuid to be used in different paylink product sessions.
        $this->product = $this->product();

        $this->withPrefix('eduka:payments:paylink:'.$this->product->uuid)
             ->forceRefreshIf(function () {

                $result = false;

                if (env('EDUKA_FORCE_PAYMENT_REFRESH') == true) {
                    $result = true;
                }

                if (env('EDUKA_IP_SIMULATION') !== null) {
                    $result = true;
                }

                // Is this product allowed to used session?
                if (! $this->product->using_session) {
                    $result = true;
                }

                // Is the current referrer different from the referrer session?
                if (Referrer::get() != Referrer::current() &&
                    Referrer::current() !== null) {
                    $result = true;
                }

                return $result;
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

    public function passthrough(array $passthrough)
    {
        $this->passthrough = $passthrough;

        return $this;
    }

    protected function computeAffiliatesCommissions()
    {
        /**
         * Affiliates are entities that will receive a commission based on
         * the price that the visit source bought the course.
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

         // Get possible fixed affiliates.
        $fixedAffiliates = Affiliate::fixed();

        // Get current price for the current product type.
        $this->getCheckoutPrice();

        //Variable just to keep a remaining price after needed subtractions.
        $remainingPrice = $this->price;

        if ($referrer) {
            // Obtain the absolute commission amount.
            $amount = round($this->price * $referrer->commission_percentage / 100, 2);

            /**
             * Add the first affiliate to the affiliates temporary structure.
             * This one is referrer, and not fixed.
             */
            $affiliates[] = [
                'vendor_id' => $referrer->paddle_vendor_id,
                'affiliate_id' => $referrer->id,
                'name' => $referrer->name,
                'type' => $referrer->type,
                'amount' => $amount,
                'commission_percentage' => round($referrer->commission_percentage / 100, 2),

                /**
                 * These are computed variables. For the referrer (not a fixed
                 * affiliate) paddle percentage are the same because the
                 * referrer commission is absolute. The fixed affiliates
                 * might differ in case there is a referrer like now.
                 */
                'paddle_percentage' => round($referrer->commission_percentage / 100, 2),
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
        foreach ($fixedAffiliates as $affiliate) {
            /**
             * Lets add each fixed affiliate and compute a first slice
             * percentage. Then we need to re-run the fixed affiliates
             * to compute the final percentage based on the initial
             * slice percentages.
             *
             * Calculate the right commission percentage. Get the amount
             * from the affiliates.commission_percentage, and then compute
             * the real paddle commission percentage from that amount.
             */

            // 1. Calculate the amount on the current available cake.
            $amount = $this->getAmountFromPercentage(
                $remainingPrice,
                $affiliate->commission_percentage
            );

            // 2. With the amount, calculate the percentake of the total cake.
            $percentage = $this->getPercentageFromAmount(
                $this->price, // Full product price.
                $amount
            );

            $affiliates[] = [
                'vendor_id' => $affiliate->paddle_vendor_id,
                'affiliate_id' => $affiliate->id,
                'type' => $affiliate->type,
                'amount' => $amount,
                'commission_percentage' => round($affiliate->commission_percentage / 100, 2),
                'paddle_percentage' => $percentage / 100,
            ];
        }

        if (count($affiliates) > 0) {
            ApplicationLog::properties($affiliates)
                          ->group('affiliates')
                          ->model(Visit::get())
                          ->log('Affiliates captured');
        }

        return $affiliates;
    }

    protected function getAmountFromPercentage($cake, $percentage)
    {
        return round($cake * $percentage / 100, 2);
    }

    protected function getPercentageFromAmount($cake, $amount)
    {
        return round($amount / $cake * 100, 2);
    }

    protected function getCheckoutPrice()
    {
        // Copy this product type to the new payment type instantiation.
        $this->price = Payment::type($this->type)
                              ->data('checkout.price');
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
         *
         * There are MANDATORY keys needs always need to be present:
         * visit_id : The visit id that started the checkout.
         * hashcode : A randomized code, so we don't have DDOS attacks.
         *            This code is recorded into the database, for later
         *            burning.
         */
        $hashcode = Hashcode::create();

        $passthrough = array_merge([
            'visit_id' => Visit::get()->id,
            'hashcode' => $authcode,
        ], $this->passthrough);

        /**
         * The affiliates computation uses the referrer that can be in session.
         * This uses the eduka-analytics referrer session logic.
         */
        $affiliates = $this->computeAffiliatesCommissions();

        /**
         * The return url is the url suffix that is redirected after
         * the visit source buys the course at the end of the checkout
         * process.
         */
        $returnUrl = url(config('eduka-nereus.paddle.return_url'));

        $paylink = Paddle::product()
                               ->generatePayLink()
                               ->productId($this->product()->paddle_product_id)
                               ->returnUrl($returnUrl)
                               ->quantityVariable(0)
                               ->quantity(1)
                               ->prices(['USD:'.$this->price]);

        if ($passthrough) {
            $paylink->passthrough(json_encode($passthrough));
        }

        if ($affiliates) {
            $result = [];
            // Parse the affiliates values and commissions into an array.
            foreach ($affiliates as $affiliate) {
                $result[] = $affiliate['vendor_id'].':'.$affiliate['paddle_percentage'];
            }
            $paylink->affiliates($result);
        }

        $this->data->url = $paylink->send()['url'];
        $this->data->success = true;
    }

    public function data(string $path = null)
    {
        if ($path) {
            return data_get($this->session(), $path);
        } else {
            return $this->session();
        }
    }
}
