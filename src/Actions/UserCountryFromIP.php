<?php

namespace Eduka\Payments\Actions;

use Illuminate\Http\Request;
use Hibit\GeoDetect;
use Hibit\Country\CountryRecord;

class UserCountryFromIP
{
    public static function get(Request $request) : CountryRecord|null
    {
        try {
            $geoDetect = new GeoDetect();
            return $geoDetect->getCountry($request->ip2());
        } catch (\Exception $_) {
            // @todo throw exception?
        }

        return null;
    }
}
