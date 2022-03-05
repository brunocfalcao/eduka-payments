<?php

namespace Eduka\Payments;

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
    private $uuid;
    private $tag = 'default'; //Default product tag from Eduka LMS


    /**
     * The data structure is where all the payment/checkout data exists.
     * It is a stdClass.
     *
     * Structure:
     * price
     *     ->amount
     *     ->discount
     *
     * @var [type]
     */
    private $data;

    public function __construct()
    {
    }

    public static function new(...$args)
    {
        return new self(...$args);
    }

    public function tag(string $tag)
    {
        $this->tag = $tag;
    }

    /**
     * Refreshes the checkout/product data if necessary.
     *
     * @param  string $type Payment category: 'price', 'currency'
     *
     * @return void
     */
    public function get(string $type)
    {
        /**
         * First thing: Do we need to refresh the payment session for this
         * product tag? If not we just return the current payment data for
         * this product. If we need to refresh, we first call the Paddle
         * api before returning the payment data.
         */
        if ($this->needsSessionRefresh($type)) {
        }
    }

    /**
     * Receives a paddle product given the product canonical via Paddle API.
     *
     * @param  string $canonical
     *
     * @return object
     */
    protected function callPaddleApi(string $canonical = 'default', int $productId)
    {
        return (object)
                (Paddle::checkout()
                ->getPrices([
                    'product_ids' => $productId,
                    'customer_ip' => public_ip(),
                ])
                ->send());
    }
}
