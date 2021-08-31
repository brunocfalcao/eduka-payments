<?php

namespace Eduka\Payments;

use Eduka\Payments\Listeners\OnboardUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ProtoneMedia\LaravelPaddle\Events\PaymentSucceeded;

final class EdukaPaymentsServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->loadRoutes();
        $this->loadEvents();
    }

    protected function loadEvents()
    {
        Event::listen(
            PaymentSucceeded::class,
            [OnboardUser::class, 'handle']
        );
    }

    protected function loadRoutes()
    {
        $routesPath = __DIR__.'/../routes/web.php';

        Route::middleware('web')
        ->group(function () use ($routesPath) {
            include $routesPath;
        });
    }
}
