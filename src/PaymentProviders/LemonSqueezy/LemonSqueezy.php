<?php

namespace Eduka\Payments\PaymentProviders\LemonSqueezy;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class LemonSqueezy
{
    public const GATEWAY_ID = 'lemonsqueezy';

    private const METHOD_POST = 'POST';

    private const METHOD_DELETE = 'DELETE';

    private const METHOD_GET = 'GET';

    private string $baseUri = 'https://api.lemonsqueezy.com/v1';

    private array $data = [];

    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->data['relationships'] = [];
    }

    public function getVariant($variantId)
    {
        return $this->get('variants/'.$variantId);
    }

    public function createCheckout()
    {
        $this->data['type'] = 'checkouts';

        return $this->post('checkouts');
    }

    public function setCustomPrice($price)
    {
        $this->data['attributes']['custom_price'] = $price;

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
            ],
        ];

        return $this;
    }

    public function setStoreId(string $storeId)
    {
        $this->data['relationships']['store'] = [
            'data' => [
                'type' => 'stores',
                'id' => $storeId,
            ],
        ];

        return $this;
    }

    public function setExpiresAt($dateTime)
    {
        $this->data['expires_at'] = $dateTime;

        return $this;
    }

    public function build()
    {
        return json_encode(['data' => $this->data]);
    }

    protected function post(string $path)
    {
        return $this->makeRequest($path, self::METHOD_POST);
    }

    protected function get(string $path)
    {
        return $this->makeRequest($path, self::METHOD_GET);
    }

    protected function delete(string $path)
    {
        return $this->makeRequest($path, self::METHOD_DELETE);
    }

    protected function makeRequest(string $path, string $method)
    {
        $headers = [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
            'Authorization' => 'Bearer '.$this->apiKey,
        ];

        $uri = sprintf('%s/%s', $this->baseUri, $path);

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
}
