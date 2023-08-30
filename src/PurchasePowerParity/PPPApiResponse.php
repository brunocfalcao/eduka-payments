<?php

namespace Eduka\Payments\PurchasePowerParity;

class PPPApiResponse
{
    protected array $data;

    public function __construct($jsonData)
    {
        $this->data = json_decode($jsonData, true);
    }

    public function getCountryCodeAlpha2()
    {
        return $this->data['ppp']['countryCodeIsoAlpha2'];
    }

    public function getCountryCodeAlpha3()
    {
        return $this->data['ppp']['countryCodeIsoAlpha3'];
    }

    public function getMainCurrencyName()
    {
        return $this->data['ppp']['currencyMain']['name'];
    }

    public function getMainCurrencySymbol()
    {
        return $this->data['ppp']['currencyMain']['symbol'];
    }

    public function GetPppConversionFactor()
    {
        return $this->data['ppp']['pppConversionFactor'];
    }

    public function getPPP()
    {
        return $this->data['ppp']['ppp'];
    }
}
