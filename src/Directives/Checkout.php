<?php

namespace Eduka\Payments\Directives;

use Eduka\Payments\Payment;

class Checkout
{
    public function __invoke(string|bool $path)
    {
        return data_get(stdclass_to_array(Payment::data()), $path);
    }
}
