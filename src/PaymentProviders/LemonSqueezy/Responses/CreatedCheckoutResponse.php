<?php

namespace Eduka\Payments\PaymentProviders\LemonSqueezy\Responses;

class CreatedCheckoutResponse
{
    public function __construct(private array $raw) {}

    public function data()
    {
        return $this->raw['data'];
    }

    public function checkoutId()
    {
        return $this->data()['id'];
    }

    public function checkoutUrl()
    {
        return $this->data()['attributes']['url'];
    }
}
