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
    // public Attributes $attributes;
    // public Relationships $relationships;
    public Meta $meta;
}
