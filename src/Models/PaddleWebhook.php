<?php

namespace Eduka\Payments\Models;

use Eduka\Abstracts\EdukaModel;

class PaddleWebhook extends EdukaModel
{
    protected $casts = [
        'passthrough' => 'array',
    ];
}
