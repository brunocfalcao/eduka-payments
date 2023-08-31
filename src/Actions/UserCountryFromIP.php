<?php

namespace Eduka\Payments\Actions;

use Hibit\Country\CountryRecord;
use Hibit\GeoDetect;
use Illuminate\Http\Request;

class UserCountryFromIP
{
    public static function get(Request $request): ?CountryRecord
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
