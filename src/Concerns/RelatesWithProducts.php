<?php

namespace Eduka\Payments\Concerns;

trait RelatesWithProducts
{
    private $canonical = 'default'; //Default product canonical.

    /**
     * Returns the current contextualized product given the canonical.
     *
     * @return Product
     */
    protected function product()
    {
        return course()->products()
                       ->firstWhere('canonical', $this->canonical);
    }

    public function canonical(string $canonical)
    {
        $this->canonical = $canonical;

        return $this;
    }
}
