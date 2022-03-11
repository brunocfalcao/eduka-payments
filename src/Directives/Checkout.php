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
                var_dump(Payment::data());
                break;

            case 'currency':
                break;
        }
    }
}
