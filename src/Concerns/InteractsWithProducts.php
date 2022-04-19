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

    protected function product()
    {
        return course()->products()
                       ->where('canonical', $this->canonical)
                       ->firstOr(function () {
                        throw new \Exception('No product found for the passed canonical');
                       });
    }
}
