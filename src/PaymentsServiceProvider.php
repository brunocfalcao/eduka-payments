<?php

namespace Eduka\Payments;

use Eduka\Abstracts\Classes\EdukaServiceProvider;
use Eduka\Payments\Commands\SimulateWebhook;
use Eduka\Payments\Commands\SyncPurchasePowerParity;
use Eduka\Payments\Events\CallbackFromPaymentGateway;
use Eduka\Payments\Listeners\ProcessPaymentWebhook;
use Illuminate\Support\Facades\Event;

class PaymentsServiceProvider extends EdukaServiceProvider
{
    public function boot()
    {
        $this->loadCommands();
        $this->registerEventListeners();

        parent::boot();
    }

    public function register()
    {
        //
    }

    protected function registerEventListeners()
    {
        Event::listen(
            CallbackFromPaymentGateway::class,
            ProcessPaymentWebhook::class,
        );
    }

    protected function loadCommands()
    {
        $this->commands([
            SyncPurchasePowerParity::class,
            SimulateWebhook::class,
        ]);
    }
}
