<?php

namespace Eduka\Payments\Commands;

use Eduka\Abstracts\Classes\EdukaCommand;
use Eduka\Cube\Models\Coupon;
use Eduka\Cube\Models\Course;
use Eduka\Payments\Actions\LemonSqueezyCoupon;
use Eduka\Payments\PaymentProviders\LemonSqueezy\LemonSqueezy;
use Eduka\Payments\PurchasePowerParity\PPPApiRequest;
use Eduka\Payments\PurchasePowerParity\PPPApiResponse;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncPurchasePowerParity extends EdukaCommand
{
    protected $signature = 'eduka-payments:ppp-sync {--course-id=} {--countries=*}';

    protected $description = 'Sync coupons with purchase power parity for a specific course.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('
                    _       _
             ___  _| | _ _ | |__ ___
            / ._>/ . || | || / /<_> |
            \___.\___|`___||_\_\<___|

        ');

        $this->paragraph('-= Run purchase power parity sync =-', false);

        $courseId = $this->option('course-id');

        if ($courseId === null) {
            $this->error('--course-id is required.');

            return 1;
        }

        $countryCodes = $this->option('countries');

        if (count($countryCodes) === 0) {
            $this->error('--countries is required. use * to sync for all countries.');

            return 1;
        }

        $course = Course::find($courseId);

        if (! $course) {
            $this->error('could not find course with id '.$courseId);

            return 1;
        }

        $couponsQuery = Coupon::query()->where('course_id', $courseId);

        if (count($countryCodes) === 1) {
            if ($countryCodes[0] !== '*') {
                if (Str::contains($countryCodes[0], ',')) {
                    $countryCodes = explode(',', $countryCodes[0]);
                }

                $couponsQuery = $couponsQuery->whereIn('country_iso_code', $countryCodes);
            }
        }

        $coupons = $couponsQuery->get();
        $api = new PPPApiRequest;

        $coupons->each(function (Coupon $coupon) use ($course, $api) {
            $apiResponse = $api->requestFor($coupon->country_iso_code);

            if (is_null($apiResponse)) {
                $this->error('could not get PPP data for '.$coupon->country_iso_code);
                $this->logError('could not get PPP data for '.$coupon->country_iso_code);

                return;
            }

            $res = new PPPApiResponse($apiResponse);

            $originalPrice = $course->course_price;
            $shouldBePrice = $originalPrice * $res->GetPppConversionFactor();
            $couponPercentage = (($originalPrice - $shouldBePrice) / $originalPrice) * 100;

            if ($couponPercentage === $coupon->discount_amount) {
                $this->logInfo(sprintf('coupon discount amount is %s and coupon percent should be %s. they match, skipping this one.', $coupon->discount_amount, $couponPercentage));

                return;
            }

            $couponApi = new LemonSqueezy(env('LEMON_SQUEEZY_API_KEY', ''));

            if ($coupon->remote_reference_id !== null && $coupon->remote_reference_id !== 0) {
                // delete the coupon first
                try {
                    $couponApi->deleteDiscount($coupon->remote_reference_id);
                } catch (\Exception $e) {
                    $this->logError('could not delete coupon.', $e);

                    return;
                }
                $this->logInfo(sprintf('coupon (remote ref id: %s) deleted successfully.', $coupon->remote_reference_id));
            }

            $isFlatDiscount = false;

            try {
                $reference = (new LemonSqueezyCoupon)->create(
                    $couponApi,
                    $course->paymentProviderStoreId(),
                    $coupon->code,
                    $couponPercentage,
                    $isFlatDiscount,
                );
            } catch (\Exception $e) {
                $this->logError('could not create new coupon.', $e);

                return;
            }

            if ($reference !== null) {
                $coupon->update([
                    'is_flat_discount' => $isFlatDiscount,
                    'remote_reference_id' => $reference,
                    'discount_amount' => $couponPercentage,
                ]);

                $this->logInfo('updated coupon ppp');
            }
        });

        return 0;
    }

    private function logInfo(string $message, array $data = [])
    {
        Log::info($message, $data);
    }

    private function logError(string $message, Exception $e = null, array $data = [])
    {
        if ($e) {
            $data[] = [
                'message' => $e->getMessage(),
            ];
        }

        Log::error($message, $data);
    }
}
