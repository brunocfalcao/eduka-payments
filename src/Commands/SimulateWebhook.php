<?php

namespace Eduka\Payments\Commands;

use Brunocfalcao\Tokenizer\Models\Token;
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

        DB::table('tokens')
            ->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Create the token that will be called by the json below.
        Token::createToken('s3Exo2NJvjFj1tH1nw4aktPSOIXuR3qjnRdenpnk');

        $url = 'http://eduka-pro.local:8000/lemonsqueezy/webhook';

        $variantId = env('EDUKA_WEBHOOK_VARIANT_ID');

        $webhook = '
{
  "data": {
    "id": "2773413",
    "type": "orders",
    "links": {
      "self": "https://api.lemonsqueezy.com/v1/orders/2773413"
    },
    "attributes": {
      "tax": 0,
      "urls": {
        "receipt": "https://app.lemonsqueezy.com/my-orders/2749f9aa-434a-41f8-ad12-1ee7d2bc449d?signature=eed27fdfaf81a2cbc2f3658ad1f3997013202b79d5be29ecdbfad91f3b33c04e"
      },
      "total": 100,
      "status": "paid",
      "tax_usd": 0,
      "currency": "USD",
      "refunded": false,
      "store_id": 25678,
      "subtotal": 100,
      "tax_name": "",
      "tax_rate": 0,
      "setup_fee": 0,
      "test_mode": true,
      "total_usd": 100,
      "user_name": '. env('EDUKA_WEBHOOK_TEST_NAME') . ',
      "created_at": "2024-05-27T19:38:00.000000Z",
      "identifier": "2749f9aa-434a-41f8-ad12-1ee7d2bc449d",
      "updated_at": "2024-05-27T19:38:00.000000Z",
      "user_email": "'. env('EDUKA_WEBHOOK_TEST_EMAIL') . '",
      "customer_id": 1628498,
      "refunded_at": null,
      "order_number": 2567855,
      "subtotal_usd": 100,
      "currency_rate": "1.00000000",
      "setup_fee_usd": 0,
      "tax_formatted": "$0.00",
      "tax_inclusive": false,
      "discount_total": 0,
      "total_formatted": "$1.00",
      "first_order_item": {
        "id": 2731768,
        "price": 100,
        "order_id": 2773413,
        "price_id": 206098,
        "quantity": 1,
        "test_mode": true,
        "created_at": "2024-05-27T19:38:00.000000Z",
        "product_id": 154629,
        "updated_at": "2024-05-27T19:38:00.000000Z",
        "variant_id": 192214,
        "product_name": "Mastering Nova - Orion",
        "variant_name": "Default"
      },
      "status_formatted": "Paid",
      "discount_total_usd": 0,
      "subtotal_formatted": "$1.00",
      "setup_fee_formatted": "$0.00",
      "discount_total_formatted": "$0.00"
    },
    "relationships": {
      "store": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2773413/relationships/store",
          "related": "https://api.lemonsqueezy.com/v1/orders/2773413/store"
        }
      },
      "customer": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2773413/relationships/customer",
          "related": "https://api.lemonsqueezy.com/v1/orders/2773413/customer"
        }
      },
      "order-items": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2773413/relationships/order-items",
          "related": "https://api.lemonsqueezy.com/v1/orders/2773413/order-items"
        }
      },
      "license-keys": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2773413/relationships/license-keys",
          "related": "https://api.lemonsqueezy.com/v1/orders/2773413/license-keys"
        }
      },
      "subscriptions": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2773413/relationships/subscriptions",
          "related": "https://api.lemonsqueezy.com/v1/orders/2773413/subscriptions"
        }
      },
      "discount-redemptions": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2773413/relationships/discount-redemptions",
          "related": "https://api.lemonsqueezy.com/v1/orders/2773413/discount-redemptions"
        }
      }
    }
  },
  "meta": {
    "test_mode": true,
    "event_name": "order_created",
    "webhook_id": "56baa80d-8843-4e2a-8af6-bbb5abcdeb85",
    "custom_data": {
      "token": "s3Exo2NJvjFj1tH1nw4aktPSOIXuR3qjnRdenpnk",
      "country": "CH"
    }
  }
}
        ';

        // Convert the payload to JSON
        $payload = json_decode($webhook, true);

        // Secret for HMAC hash
        $secret = config('eduka.integrations.lemon_squeezy.hash_key');

        // Generate the HMAC hash of the payload
        $signature = hash_hmac('sha256', $webhook, $secret);

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
            $this->line('Response body: '.$response->getBody()->getContents());
        } catch (Exception $e) {
            $this->error('Exception: '.$e->getMessage());
        }
    }
}
