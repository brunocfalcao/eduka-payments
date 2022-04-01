<?php

namespace Eduka\Payments\Directives;

use Eduka\Payments\Paylink as PaylinkService;

class Paylink
{
    public function __invoke(string $path, string $canonical = 'default', array $payload = [], array $passthrough = [])
    {
        dd(PayLinkService::canonical($canonical)
                         ->payload($payload)
                         ->passthrough($passthrough)
                         ->data());

        return data_get(stdclass_to_array(PaylinkService::data()), $path);
    }
}
