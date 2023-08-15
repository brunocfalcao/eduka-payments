<?php

namespace Eduka\Payments\PaymentProviders\LemonSqueezy\DTO;

class Order
{
    public string $id;
    public string $type;
    /**
     * Link[]
     *
     * @var array
     */
    public array $links;
    public array $attributes;
    public array $relationships;
    public Meta $meta;
}
