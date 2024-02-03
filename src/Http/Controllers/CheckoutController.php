<?php

namespace Eduka\Payments\Http\Controllers;

use Brunocfalcao\Tokenizer\Models\Token;
use Eduka\Cube\Models\Variant;
use Eduka\Nereus\Facades\Nereus;
use Eduka\Payments\PaymentProviders\LemonSqueezy\LemonSqueezy;
use Eduka\Payments\PaymentProviders\LemonSqueezy\Responses\CreatedCheckoutResponse;
use Exception;
use Illuminate\Support\Facades\URL;

class CheckoutController
{
    protected $api;

    protected $variant;

    protected $country;

    protected $payload;

    public function __invoke()
    {
        $this->getLemonSqueezyApi();

        $this->getVariant();

        $this->validateRequiredData();

        $this->obtainCountry();

        $this->createPayload();

        $checkoutResponse = $this->createCheckout($this->api, $this->payload);

        $checkoutUrl = (new CreatedCheckoutResponse($checkoutResponse))->checkoutUrl();

        return redirect()->away($checkoutUrl);
    }

    protected function createCheckout(LemonSqueezy $paymentsApi, array $payload)
    {
        try {
            $responseString = $paymentsApi
                ->setRedirectUrl(URL::temporarySignedRoute(
                    'purchase.callback',
                    now()->addMinutes(5)
                ))
                ->setExpiresAt(now()->addHours(1)->toString())
                ->setCustomData([
                    // This token will be burnt on the webhook controller.
                    'token' => Token::createToken()->token,
                ]);

            // Conditionally applying setCustomPrice.
            if ($this->variant->lemon_squeezy_price_override) {
                $responseString = $responseString
                    ->setCustomPrice(
                        $this->variant->lemon_squeezy_price_override * 100
                    );
            }

            $course = Nereus::course();

            $responseString = $responseString
                ->setStoreId($course->lemon_squeezy_store_id)
                ->setVariantId($this->variant->lemon_squeezy_variant_id)
                ->createCheckout();

            $raw = json_decode($responseString, true);

            if (isset($raw['errors'])) {
                throw new Exception(reset($raw['errors'][0]));
            }

            return $raw;
        } catch (Exception $e) {
            throw new Exception('could not create checkout - '.$e->getMessage());
        }
    }

    protected function getVariant()
    {
        $this->variant = Variant::with('course')
            ->firstWhere(
                'uuid',
                request('variant')
            );
    }

    protected function createPayload()
    {
        // Contains all checkout custom information that is needed for eduka.
        $this->payload = [
            'variant' => $this->variant,
            'country' => $this->country,
        ];
    }

    protected function getLemonSqueezyApi()
    {
        $this->api = new LemonSqueezy(
            Nereus::course()->lemon_squeezy_api_key
        );
    }

    protected function obtainCountry()
    {
        // https://developers.cloudflare.com/fundamentals/reference/http-request-headers/
        $this->country ??= request()->header('cf-ipcountry');
    }

    protected function validateRequiredData()
    {
        if (! Nereus::course() ||
            ! $this->variant ||
            ! $this->api) {
            // No minimum data to continue. Abort.
            return redirect()->back();
        }
    }
}
