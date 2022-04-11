<?php

namespace Eduka\Payments\Concerns;

trait InteractsWithProducts
{
    protected $canonical;

    public function canonical(string $canonical = 'default')
    {
        $this->canonical = $canonical;

        return $this;
    }

    protected function product(string $canonical = 'default')
    {
        $this->canonical($canonical);

        return course()->products()->firstWhere('canonical', $this->canonical);
    }
}
