<?php

namespace Eduka\Payments;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class EdukaPaymentsServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        info('[EdukaPayments][ServiceProvider] Start');

        $this->loadRoutes();

        if (! $this->app->runningInConsole()) {
            $this->overridePaymentConfiguration();
        }

        $this->importMigrations();

        info('[EdukaPayments][ServiceProvider] Stop');
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
}
