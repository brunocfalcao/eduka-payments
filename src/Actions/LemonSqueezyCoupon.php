<?php

namespace Eduka\Payments\Actions;

use Eduka\Payments\PaymentProviders\LemonSqueezy\LemonSqueezy;
use Exception;
use Illuminate\Support\Facades\Log;

class LemonSqueezyCoupon
{
    public function create(LemonSqueezy $couponApi, string $storeId, string $code, float $amount, bool $isFixed)
    {
        try {
            $response = $couponApi
                ->setStoreId($storeId)
                ->createDiscount($code, $amount, $isFixed);

            $res = json_decode($response, true);

            if (isset($res['errors'])) {
                $this->log($res['errors'][0]['detail'], null, $res['errors']);

                return false;
            }

            if (isset($res['data'])) {
                return $res['data']['id'];
            }

            return false;
        } catch (Exception $e) {
            $this->log('could not create coupon in lemonsquzzy', $e);

            return false;
        }
    }

    private function log(string $message, ?Exception $e, array $data = [])
    {
        if ($e) {
            $data[] = [
                'message' => $e->getMessage(),
            ];
        }

        Log::error($message, $data);
    }
}
