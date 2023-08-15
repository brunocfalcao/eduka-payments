<?php

namespace Eduka\Payments;

use Eduka\Abstracts\Classes\EdukaServiceProvider;
use Eduka\Analytics\Middleware\TrackVisit;
use Illuminate\Support\Facades\Route;

class PaymentsServiceProvider extends EdukaServiceProvider
{
    public function boot()
    {
        $this->loadFrontendRoutes();

        parent::boot();
    }

    public function register()
    {
    }

    protected function loadFrontendRoutes()
    {
        $routesPath = __DIR__.'/../routes/payment_routes.php';

        Route::middleware([
            'web',
            TrackVisit::class,
        ])
        ->group(function () use ($routesPath) {
            include $routesPath;
        });
    }
}
