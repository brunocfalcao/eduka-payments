<?php

namespace Eduka\Payments;

use Eduka\Abstracts\Classes\EdukaServiceProvider;
use Eduka\Payments\Commands\SyncPurchasePowerParity;

class PaymentsServiceProvider extends EdukaServiceProvider
{
    public function boot()
    {
        $this->loadCommands();

        parent::boot();
    }

    public function register()
    {
    }

    protected function loadCommands()
    {
        $this->commands([
            SyncPurchasePowerParity::class,
        ]);
    }
}
