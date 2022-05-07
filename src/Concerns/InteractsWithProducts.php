<?php

namespace Eduka\Payments\Concerns;

trait InteractsWithProducts
{
    protected $type = 'default';

    public function type(string $type = 'default')
    {
        $this->type = $type;

        return $this;
    }

    protected function product()
    {
        return course()->products()
                       ->where('type', $this->type)
                       ->firstOr(function () {
                        throw new \Exception('No product found for the passed type ('.$this->type.')');
                       });
    }
}
