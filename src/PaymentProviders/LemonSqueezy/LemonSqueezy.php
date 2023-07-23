<?php

namespace Eduka\Payments\PaymentProviders\LemonSqueezy;

use Eduka\Payments\PaymentsInterface;

class LemonSqueezy implements PaymentsInterface
{
    private string $baseUri = "https://api.lemonsqueezy.com/v1";

    private array $data = [];

    public function getProductById(int|string $id)
    {
        dd($id);
    }


    public function calculateGrandTotal()
    {
    }

    public function setCustomPrice($price)
    {
        $this->data['custom_price'] = $price;
        return $this;
    }

    public function disableProductVariants()
    {
        $this->data['product_options']['enabled_variants'] = [];
        return $this;
    }

    public function setRedirectUrl($url)
    {
        $this->data['product_options']['redirect_url'] = $url;
        return $this;
    }

    public function setButtonColor($color)
    {
        $this->data['checkout_options']['button_color'] = $color;
        return $this;
    }

    public function setCustomData(array $customData)
    {
        $this->data['checkout_data']['custom'] = $customData;
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

    public function buildData()
    {
        $this->setCustomPrice(50000)
            ->disableProductVariants()
            ->setRedirectUrl("http://masteringnova.com")
            ->setButtonColor("#2DD272")
            ->setCustomData([])
            ->setExpiresAt("2025-10-30T15:20:06Z")
            ->setPreview(true)
            ->build();
    }
}
