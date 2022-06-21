<?php

namespace Eduka\Payments;

use Eduka\Payments\Commands\WebhookTest;
use Eduka\Payments\Listeners\ProcessPayment;
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

        if (! $this->app->runningInConsole()) {
            $this->overridePaymentConfiguration();
        }

        if ($this->app->runningInConsole()) {
            $this->loadCommands();
        }

        $this->importMigrations();

        $this->registerListeners();
    }

    protected function registerListeners()
    {
        Event::listen(
            PaymentSucceeded::class,
            [ProcessPayment::class, 'handle']
        );
    }

    protected function importMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Re-writes the paddle.php config file array (not the file itself!)
     * to have the correct course payment data. This changes for each
     * contexted course.
     *
     * @return void
     */
    protected function overridePaymentConfiguration()
    {
        $data = app('config')->get('paddle');

        $course = course();

        app('config')->set('paddle', array_merge($data, [
            [
                'vendor_id' => $course->paddle_vendor_id,
                'vendor_auth_code' => $course->paddle_vendor_auth_code,
                'public_key' => $course->paddle_public_key,
                'sandbox_environment' => $course->paddle_is_sandbox_environment,
            ],
        ]));
    }

    protected function loadRoutes()
    {
        $routesPath = __DIR__.'/../routes/web.php';

        Route::middleware('web')
        ->group(function () use ($routesPath) {
            include $routesPath;
        });
    }

    protected function loadCommands()
    {
        $this->commands([
            WebhookTest::class,
        ]);
    }
}
