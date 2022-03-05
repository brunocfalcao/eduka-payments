<?php

namespace Eduka\Payments\Directives;

use Eduka\Payments\Payment;

class Checkout
{
    public function __invoke(array $args)
    {
        $tag = $args[0];

        switch ($args[1]) {
            case 'price':
                return Payment::tag($tag)->get('price');
                break;

            case 'currency':
                break;
        }
    }
}
