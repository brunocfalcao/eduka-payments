<?php

namespace Eduka\Payments\Models;

use Eduka\Abstracts\EdukaModel;

class PaymentWebhook extends EdukaModel
{
    protected $casts = [
        'passthrough' => 'array',
    ];
}
