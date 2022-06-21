<?php

namespace Eduka\Payments\Models;

use Eduka\Abstracts\EdukaModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hashcode extends EdukaModel
{
    use SoftDeletes;

    protected $casts = [
        'is_burnt' => 'boolean',
    ];
}
