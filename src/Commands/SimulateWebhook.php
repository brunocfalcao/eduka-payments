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

        /*
          DB::table('orders')
              ->truncate();

          DB::table('students')
              ->where('email', 'bruno.falcao@live.com')
              ->delete();
          */

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Create the token that will be called by the json below.
        Token::createToken('s3Exo2NJvjFj1tH1nw4aktPSOIXuR3qjnRdenpnk');

        $url = 'http://brunofalcao.local:8000/lemonsqueezy/webhook';

        /**
         * Silver surfer: 191933
         * Orion: 192214
         */
        $variantId = 191933;

        $webhook = '
{
  "data": {
    "id": "2490206",
    "type": "orders",
    "links": {
      "self": "https://api.lemonsqueezy.com/v1/orders/2490206"
    },
    "attributes": {
      "tax": 0,
      "urls": {
        "receipt": "https://app.lemonsqueezy.com/my-orders/2fc92a86-e390-4244-9125-fbd666b648dd?signature=23e820e0cade490b574295bce7c81c42c78e6972e64f94fe4f47d655407ed09c"
      },
      "total": 0,
      "status": "paid",
      "tax_usd": 0,
      "currency": "USD",
      "refunded": false,
      "store_id": 25678,
      "subtotal": 0,
      "tax_name": "",
      "tax_rate": 0,
      "setup_fee": 0,
      "test_mode": true,
      "total_usd": 0,
      "user_name": "Bruno Falcao",
      "created_at": "2024-04-15T07:21:43.000000Z",
      "identifier": "2fc92a86-e390-4244-9125-fbd666b648dd",
      "updated_at": "2024-04-15T07:21:44.000000Z",
      "user_email": "bruno.falcao@live.com",
      "customer_id": 1628498,
      "refunded_at": null,
      "order_number": 2567853,
      "subtotal_usd": 0,
      "currency_rate": "1.00000000",
      "setup_fee_usd": 0,
      "tax_formatted": "$0.00",
      "tax_inclusive": false,
      "discount_total": 0,
      "total_formatted": "$0.00",
      "first_order_item": {
        "id": 2450871,
        "price": 0,
        "order_id": 2490206,
        "price_id": 206098,
        "quantity": 1,
        "test_mode": true,
        "created_at": "2024-04-15T07:21:44.000000Z",
        "product_id": 154629,
        "updated_at": "2024-04-15T07:21:44.000000Z",
        "variant_id": 192214,
        "product_name": "Mastering Nova - Orion",
        "variant_name": "Default"
      },
      "status_formatted": "Paid",
      "discount_total_usd": 0,
      "subtotal_formatted": "$0.00",
      "setup_fee_formatted": "$0.00",
      "discount_total_formatted": "$0.00"
    },
    "relationships": {
      "store": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2490206/relationships/store",
          "related": "https://api.lemonsqueezy.com/v1/orders/2490206/store"
        }
      },
      "customer": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2490206/relationships/customer",
          "related": "https://api.lemonsqueezy.com/v1/orders/2490206/customer"
        }
      },
      "order-items": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2490206/relationships/order-items",
          "related": "https://api.lemonsqueezy.com/v1/orders/2490206/order-items"
        }
      },
      "license-keys": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2490206/relationships/license-keys",
          "related": "https://api.lemonsqueezy.com/v1/orders/2490206/license-keys"
        }
      },
      "subscriptions": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2490206/relationships/subscriptions",
          "related": "https://api.lemonsqueezy.com/v1/orders/2490206/subscriptions"
        }
      },
      "discount-redemptions": {
        "links": {
          "self": "https://api.lemonsqueezy.com/v1/orders/2490206/relationships/discount-redemptions",
          "related": "https://api.lemonsqueezy.com/v1/orders/2490206/discount-redemptions"
        }
      }
    }
  },
  "meta": {
    "test_mode": true,
    "event_name": "order_created",
    "webhook_id": "c9a15f05-ccf0-4af7-8eba-6bccb330e371",
    "custom_data": {
      "token": "s3Exo2NJvjFj1tH1nw4aktPSOIXuR3qjnRdenpnk"
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
