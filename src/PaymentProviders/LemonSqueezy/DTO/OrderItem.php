<?php

namespace Eduka\Payments\PaymentProviders\LemonSqueezy\DTO;

class OrderItem
{
    public int $price;
    public int $order_id;
    public bool $test_mode;
    public string $created_at;
    public int $product_id;
    public string $updated_at;
    public int $variant_id;
    public string $product_name;
    public string $variant_name;
}
