<?php

namespace Eduka\Payments\Directives;

use Eduka\Payments\Paylink as PaylinkService;

class Paylink
{
    public function __invoke(string $path, array $payload = [], array $passthrough = [], string $type = 'default')
    {
        return PayLinkService::type($type)
                             ->payload($payload)
                             ->passthrough($passthrough)
                             ->data($path);
    }
}
