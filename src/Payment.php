<?php

namespace Eduka\Payments;

class Payment
{
    public static function __callStatic($method, $args)
    {
        return PaymentService::new()->{$method}(...$args);
    }
}

class Payment
{
    public function __construct()
    {
        //
    }

    public static function new(...$args)
    {
        return new self(...$args);
    }
}
