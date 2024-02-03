<?php

namespace Eduka\Payments\Http\Controllers;

use Exception;
use Eduka\Cube\Models\Order;
use Eduka\Cube\Models\Variant;
use Brunocfalcao\Tokenizer\Models\Token;

class WebhookController
{
    public function __invoke()
    {
        /**
         * Controller itself will:
         * 1. Validate the token from the webhook payload.
         * 2. Add the order into the database.
         *
         * The remaining activities are called via the event
         * that will be triggered on the order.created
         * observer.
         */

        info('-- Webhook called for ' . request()->all()['meta']['event_name']);

        // Validates and burns token.
        $this->validateWebhookToken();

        // Verify if the variant id is part of our course variants.
        $this->validateLemonSqueezyVariantId();

        // Store the order and start the course assignment process.
        $this->storeOrder();

        // We can return ok. Any exception needs to be treated later.
        return response()->json();
    }

    protected function validateLemonSqueezyVariantId()
    {
        $payload = request()->all();

        // This is the LS variant id, not the eduka variants.id value.
        $variantId = data_get($payload, 'data.attributes.first_order_item.variant_id');

        // Check if there is an eduka variant with this LS variant id.
        if (! Variant::where('lemon_squeezy_variant_id', $variantId)->exists()) {
            throw new \Exception('No variant matched in eduka');
        }
    }

    protected function validateWebhookToken()
    {
        $payload = request()->all();

        $token = data_get($payload, 'meta.custom_data.token');

        Token::burn($token);
    }

    protected function storeOrder()
    {
        $payload = request()->all();

        // Columns to array paths (data_get) mappings.
        // Except response_body.
        $mapping = [
            'event_name' => 'meta.event_name',
            'custom_data' => 'meta.custom_data',
            'store_id' => 'data.attributes.store_id',
            'customer_id' => 'data.attributes.store_id',
            'order_number' => 'data.attributes.order_number',
            'user_name' => 'data.attributes.user_name',
            'user_email' => 'data.attributes.user_email',
            'subtotal_usd' => 'data.attributes.subtotal_usd',
            'discount_total_usd' => 'data.attributes.discount_total_usd',
            'tax_usd' => 'data.attributes.tax_usd',
            'total_usd' => 'data.attributes.total_usd',
            'tax_name' => 'data.attributes.tax_name',
            'status' => 'data.attributes.status',
            'refunded' => 'data.attributes.refunded',
            'refunded_at' => 'data.attributes.refunded_at',
            'order_id' => 'data.attributes.first_order_item.order_id',
            'lemon_squeezy_product_id' => 'data.attributes.first_order_item.product_id',
            'lemon_squeezy_variant_id' => 'data.attributes.first_order_item.variant_id',
            'lemon_squeezy_product_name' => 'data.attributes.first_order_item.product_name',
            'lemon_squeezy_variant_name' => 'data.attributes.first_order_item.variant_name',
            'price' => 'data.attributes.first_order_item.price',
            'receipt' => 'data.attributes.urls.receipt',
        ];

        $data = [];

        foreach ($mapping as $column => $webhookAttribute) {
            $data[$column] = data_get($payload, $webhookAttribute);
        }

        $data['response_body'] = request()->all();

        //Order::create($data);
    }
}
