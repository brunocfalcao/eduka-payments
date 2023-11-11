<?php

namespace Eduka\Payments;

use Eduka\Abstracts\Classes\EdukaServiceProvider;
use Eduka\Payments\Commands\SyncPurchasePowerParity;
use Illuminate\Support\Facades\Route;

class PaymentsServiceProvider extends EdukaServiceProvider
{
    public function boot()
    {
        $this->loadFrontendRoutes();

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

    protected function loadFrontendRoutes()
    {
        $routesPath = __DIR__.'/../routes/payment_routes.php';

        Route::middleware([
            'web',
        ])
        ->group(function () use ($routesPath) {
            include $routesPath;
        });
    }
}
