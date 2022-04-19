<?php

namespace Eduka\Payments\Directives;

use Eduka\Payments\Paylink as PaylinkService;

class Paylink
{
    public function __invoke(string $path, array $payload = [], array $passthrough = [], string $canonical = 'default')
    {
        return PayLinkService::canonical($canonical)
                             ->payload($payload)
                             ->passthrough($passthrough)
                             ->data($path);
    }
}
