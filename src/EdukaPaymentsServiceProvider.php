<?php

namespace Eduka\Payments;

use Eduka\Payments\Directives\Checkout;
use Eduka\Payments\Directives\Paylink;
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
        /**
         * The @checkout directive works by passing 2 possible values:.
         *
         * @checkout(true);
         * @checkout('paddle.price') as example. It's an array conversion from
         * the Payment::$data StdClass. Please check the Payment->compute()
         * method to see what possible data structure values can be fetched.
         */
        BladeHelper::directive(
            'checkout',
            function (string|bool $path) {
                return (new Checkout())($path);
            }
        );

        BladeHelper::directive(
            'paylink',
            function (string|bool $path, string $canonical, array $payload = [], array $passthrough = []) {
                return (new Paylink())(
                    $path,
                    $canonical,
                    $payload,
                    $passthrough
                );
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
