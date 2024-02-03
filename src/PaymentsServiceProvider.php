<?php

namespace Eduka\Payments;

use Eduka\Abstracts\Classes\EdukaServiceProvider;
use Eduka\Payments\Commands\SimulateWebhook;

class PaymentsServiceProvider extends EdukaServiceProvider
{
    public function boot()
    {
        $this->loadCommands();

        parent::boot();
    }

    public function register()
    {
        //
    }

    protected function loadCommands()
    {
        $this->commands([
            SimulateWebhook::class,
        ]);
    }
}
