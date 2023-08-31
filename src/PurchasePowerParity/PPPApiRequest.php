<?php

namespace Eduka\Payments\PurchasePowerParity;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class PPPApiRequest
{
    protected Client $client;

    protected string $ApiUrl = 'https://api.purchasing-power-parity.com';

    public function __construct()
    {
        $this->client = new Client();
    }

    public function requestFor(string $countryCode)
    {
        $url = sprintf('%s/?target=%s', $this->ApiUrl, urlencode($countryCode));

        try {
            $response = $this->client->get($url);

            if ($this->isSuccessful($response)) {
                return $response->getBody()->getContents();
            } else {
                // Handle non-successful response
                return null;
            }
        } catch (RequestException $e) {
            // Handle request exception
            return null;
        }
    }

    protected function isSuccessful(ResponseInterface $response)
    {
        return $response->getStatusCode() === 200;
    }
}
