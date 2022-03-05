<?php

namespace Eduka\Payments;

use Eduka\Payments\Directives\Checkout;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ImLiam\BladeHelper\Facades\BladeHelper;

final class EdukaPaymentsServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->loadRoutes();
        $this->overridePaymentConfiguration();
        $this->loadDirectives();
        //Payment::dumpPaymentData();
    }

    protected function loadDirectives()
    {
        BladeHelper::directive(
            'checkout',
            function (...$args) {
                return (new Checkout())($args);
            }
        );
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
