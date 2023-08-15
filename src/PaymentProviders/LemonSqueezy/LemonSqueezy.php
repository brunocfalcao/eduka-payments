<?php

namespace Eduka\Payments\PaymentProviders\LemonSqueezy;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class LemonSqueezy
{
    public const GATEWAY_ID = "lemon_squeezy";

    private string $baseUri = "https://api.lemonsqueezy.com/v1";

    private array $data = [];

    private string $apiKey;

    private const METHOD_POST = "POST";

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->data['relationships'] = [];
    }

    public function createCheckout()
    {
        $this->data['type'] = 'checkouts';

        return $this->post("checkouts");
    }

    private function post(string $path)
    {
        return $this->makeRequest($path, self::METHOD_POST);
    }

    public function createDiscount(string $code, float $amount, bool $isFixed)
    {
        $this->data['type'] = 'discounts';

        $this->data['attributes'] = [
            "name" => $code,
            "code" => $code,
            "amount" => $isFixed ? $amount * 100 : $amount,
            "amount_type" => $isFixed ? 'fixed' : 'percent'
        ];

        return $this->post("discounts");
    }

    private function makeRequest(string $path, string $method)
    {
        $headers = [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];

        $uri = sprintf("%s/%s", $this->baseUri, $path);

        $data = $this->build();

        $request = new Request($method, $uri, $headers, $data);
        $client = new Client();
        $res = $client->sendRequest($request);

        try {
            return $res->getBody()->getContents();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function setCustomPrice($price)
    {
        $this->data['attributes']['custom_price'] = $price;
        return $this;
    }

    public function disableProductVariants()
    {
        $this->data['attributes']['product_options']['enabled_variants'] = [];
        return $this;
    }

    public function setRedirectUrl($url)
    {
        $this->data['attributes']['product_options']['redirect_url'] = $url;
        return $this;
    }

    public function setButtonColor($color)
    {
        $this->data['checkout_options']['button_color'] = $color;
        return $this;
    }

    public function setCustomData(array $customData)
    {
        $this->data['attributes']['checkout_data']['custom'] = $customData;
        return $this;
    }

    public function setVariantId(string $id)
    {
        $this->data['relationships']['variant'] = [
            'data' => [
                'type' => 'variants',
                'id' => $id,
            ]
        ];

        return $this;
    }

    public function setStoreId(string $storeId)
    {
        $this->data['relationships']['store'] = [
            'data' => [
                'type' => 'stores',
                'id' => $storeId,
            ]
        ];

        return $this;
    }

    public function setCouponId(string $discountCode)
    {
        $this->data['checkout_options']['discount_code'] = $discountCode;
        return $this;
    }

    public function setExpiresAt($dateTime)
    {
        $this->data['expires_at'] = $dateTime;
        return $this;
    }

    public function setPreview($preview)
    {
        $this->data['preview'] = $preview;
        return $this;
    }

    public function build()
    {
        return json_encode(['data' => $this->data]);
    }
}
