<?php

namespace Eduka\Payments\PaymentProviders\LemonSqueezy\Responses;

class CreatedCheckoutResponse
{
    private array $raw;

    public function __construct(string $responseString)
    {
        $this->raw = json_decode($responseString, true);
    }

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
