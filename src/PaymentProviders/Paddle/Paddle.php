<?php

namespace Eduka\Payments\PaymentProviders\Paddle;

use Eduka\Cube\Models\Variant;
use ProtoneMedia\LaravelPaddle\Paddle as PaddleGateway;

/**
 * This class will be a wrapper for the protonmedia/laravel-paddle,
 * adapted to the eduka business methods that are needed.
 * Most of the cases, this payment class is used on the
 * variant or student model classes.
 */
class Paddle
{
    protected $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Returns the product price from the payment gateway.
     *
     * [] => ['currency' => 'USD',
     *        'price'    => 90.0 ]
     */
    public function price()
    {
        $data = PaddleGateway::checkout()
            ->getPrices(['product_ids' => $this->data['product_id']])
            ->send();

        return ['currency' => $data['products'][0]['currency'],
            'price' => $data['products'][0]['price']['net']];
    }
}
