<?php

namespace Eduka\Payments\Actions;

class LemonSqueezyWebhookPayloadExtractor
{
    public function extract(array $data): array
    {
        $data = $data['data'];
        $attributes = $data['attributes'];

        return [
            'remote_reference_order_id' => $data['id'],
            'remote_reference_customer_id' => $attributes['customer_id'],
            'remote_reference_order_attribute_id' => $attributes['identifier'],
            'currency_id' => $attributes['currency'],
            'remote_reference_payment_status' => $attributes['status'],
            'refunded_at' => $attributes['refunded_at'],
            'tax' => $attributes['tax'],
            'discount_total' => $attributes['discount_total'],
            'subtotal' => $attributes['subtotal'],
            'total' => $attributes['total'],
        ];
    }
}
