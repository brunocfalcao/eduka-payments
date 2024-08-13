<?php

namespace Eduka\Payments\Http\Controllers;

use Brunocfalcao\Tokenizer\Models\Token;
use Eduka\Cube\Models\Variant;
use Eduka\Helpers\Facades\Eduka;
use Eduka\Nereus\Facades\Nereus;
use Exception;
use Illuminate\Support\Facades\URL;

class CheckoutController
{
    protected $api;

    protected $variant;

    protected $country;

    protected $checkoutUrl;

    public function __invoke()
    {
        // Obtain the Payment Class that we need to use.
        $gateway = Nereus::course()->payments_gateway_class;

        dd($gateway);

        $this->getLemonSqueezyApi();

        $this->getVariant();

        $this->validateRequiredData();

        $this->createPayload();

        $this->createCheckoutLink();

        return redirect()->away($this->checkoutUrl);
    }

    protected function createCheckoutLink()
    {
        $checkoutResponse = $this->createCheckout($this->api);

        $this->checkoutUrl = (new CreatedCheckoutResponse($checkoutResponse))->checkoutUrl();
    }

    protected function createCheckout(LemonSqueezy $paymentsApi)
    {
        $customData = [];
        $customData['token'] = Token::createToken()->token;

        // In local environments, the country will be null.
        if ($this->getCountry() != null) {
            $customData['country'] = $this->getCountry();
        }

        try {
            $responseString = $paymentsApi
                ->setRedirectUrl(URL::temporarySignedRoute(
                    'purchase.callback',
                    now()->addMinutes(5)
                ))
                ->setExpiresAt(now()->addHours(1)->toString())
                ->setCustomData($customData);

            // Conditionally applying setCustomPrice.
            if ($this->variant->price_override !== null) {
                $responseString = $responseString
                    ->setCustomPrice(
                        $this->variant->price_override * 100
                    );
            }

            $responseString = $responseString
                ->setStoreId($this->variant->course->lemon_squeezy_store_id)
                ->setVariantId($this->variant->product_id)
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

    protected function getCountry()
    {
        // https://developers.cloudflare.com/fundamentals/reference/http-request-headers/
        return request()->header('cf-ipcountry');
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
