<?php

namespace Eduka\Payments\Commands;

use Brunocfalcao\Tokenizer\Models\Token;
use Eduka\Cube\Models\Variant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SimulateWebhook extends Command
{
    protected $signature = 'eduka:webhook';

    protected $description = 'Simulate a Lemon Squeezy webhook call';

    public function handle()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('orders')->truncate();
        DB::table('users')->where('id', '>', 1)->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $url = 'http://brunofalcao.local:8000/lemonsqueezy/webhook';

        $payload = [
            'meta' => [
                'event_name' => 'order_created',
                'custom_data' => [
                    'variant_uuid' => Variant::first()->uuid,
                    'token' => Token::createToken(),
                ],
            ],
            'data' => [
                'type' => 'orders',
                'id' => '1',
                'attributes' => [
                    'store_id' => 1,
                    'customer_id' => 1,
                    'identifier' => '104e18a2-d755-4d4b-80c4-a6c1dcbe1c10',
                    'order_number' => 1,
                    'user_name' => 'Darlene Daugherty',
                    'user_email' => 'gernser@yahoo.com',
                    'currency' => 'USD',
                    'currency_rate' => '1.0000',
                    'subtotal' => 999,
                    'discount_total' => 0,
                    'tax' => 200,
                    'total' => 1199,
                    'subtotal_usd' => 999,
                    'discount_total_usd' => 0,
                    'tax_usd' => 200,
                    'total_usd' => 1199,
                    'tax_name' => 'VAT',
                    'tax_rate' => '20.00',
                    'status' => 'paid',
                    'status_formatted' => 'Paid',
                    'refunded' => false,
                    'refunded_at' => null,
                    'subtotal_formatted' => '$9.99',
                    'discount_total_formatted' => '$0.00',
                    'tax_formatted' => '$2.00',
                    'total_formatted' => '$11.99',
                    'first_order_item' => [
                        'id' => 1,
                        'order_id' => 1,
                        'product_id' => 1,
                        'variant_id' => Variant::first()->lemon_squeezy_variant_id,
                        'product_name' => 'Test Limited License for 2 years',
                        'variant_name' => 'Default',
                        'price' => 1199,
                        'created_at' => '2021-08-17T09:45:53.000000Z',
                        'updated_at' => '2021-08-17T09:45:53.000000Z',
                        'deleted_at' => null,
                        'test_mode' => false,
                    ],
                    'urls' => [
                        'receipt' => 'https://app.lemonsqueezy.com/my-orders/104e18a2-d755-4d4b-80c4-a6c1dcbe1c10?signature=8847fff02e1bfb0c7c43ff1cdf1b1657a8eed2029413692663b86859208c9f42',
                    ],
                    'created_at' => '2021-08-17T09:45:53.000000Z',
                    'updated_at' => '2021-08-17T09:45:53.000000Z',
                ],
                'relationships' => [
                    'store' => [
                        'links' => [
                            'related' => 'https://api.lemonsqueezy.com/v1/orders/1/store',
                            'self' => 'https://api.lemonsqueezy.com/v1/orders/1/relationships/store',
                        ],
                    ],
                    'customer' => [
                        'links' => [
                            'related' => 'https://api.lemonsqueezy.com/v1/orders/1/customer',
                            'self' => 'https://api.lemonsqueezy.com/v1/orders/1/relationships/customer',
                        ],
                    ],
                    'order-items' => [
                        'links' => [
                            'related' => 'https://api.lemonsqueezy.com/v1/orders/1/order-items',
                            'self' => 'https://api.lemonsqueezy.com/v1/orders/1/relationships/order-items',
                        ],
                    ],
                    'subscriptions' => [
                        'links' => [
                            'related' => 'https://api.lemonsqueezy.com/v1/orders/1/subscriptions',
                            'self' => 'https://api.lemonsqueezy.com/v1/orders/1/relationships/subscriptions',
                        ],
                    ],
                    'license-keys' => [
                        'links' => [
                            'related' => 'https://api.lemonsqueezy.com/v1/orders/1/license-keys',
                            'self' => 'https://api.lemonsqueezy.com/v1/orders/1/relationships/license-keys',
                        ],
                    ],
                    'discount-redemptions' => [
                        'links' => [
                            'related' => 'https://api.lemonsqueezy.com/v1/orders/1/discount-redemptions',
                            'self' => 'https://api.lemonsqueezy.com/v1/orders/1/relationships/discount-redemptions',
                        ],
                    ],
                ],
                'links' => [
                    'self' => 'https://api.lemonsqueezy.com/v1/orders/1',
                ],
            ],
        ];

        // Convert the payload to JSON
        $payloadJson = json_encode($payload);

        // Secret for HMAC hash
        $secret = 'eduka-1234-';

        // Generate the HMAC hash of the payload
        $signature = hash_hmac('sha256', $payloadJson, $secret);

        // Set the headers, including the signature
        $headers = [
            'Content-Type' => 'application/json',
            'X-Event-Name' => 'order_created',
            'X-Signature' => $signature,
        ];

        try {
            $this->info('Calling '.$url.' ...');

            // Send the POST request
            $response = Http::withHeaders($headers)->post($url, $payload);

            // Output the response status and body
            $this->info('Webhook call simulated. Response status: '.$response->status());
            $this->line('Response body: '.$response->body());
        } catch (Exception $e) {
            $this->error('Exception: '.$e->getMessage());
        }
    }
}
