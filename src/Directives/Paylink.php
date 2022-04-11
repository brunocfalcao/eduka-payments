<?php

namespace Eduka\Payments\Directives;

use Eduka\Payments\Paylink as PaylinkService;

class Paylink
{
    public function __invoke(string $path, array $payload = [], array $passthrough = [], string $canonical = 'default')
    {
        dd('Paylink.php', PayLinkService::canonical($canonical)
                                         ->payload($payload)
                                         ->passthrough($passthrough)
                                         ->data());

        return data_get(stdclass_to_array(PaylinkService::data()), $path);
    }
}
