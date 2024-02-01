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

        DB::table('orders')
          ->truncate();

        DB::table('users')
          ->where('email', 'bruno.falcao@live.com')
          ->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $url = 'http://brunofalcao.local:8000/lemonsqueezy/webhook';

        $webhook = "
{
  \"data\": {
    \"id\": \"2021602\",
    \"type\": \"orders\",
    \"links\": {
      \"self\": \"https://api.lemonsqueezy.com/v1/orders/2021602\"
    },
    \"attributes\": {
      \"tax\": 0,
      \"urls\": {
        \"receipt\": \"https://app.lemonsqueezy.com/my-orders/a71e3578-2975-4771-9e5b-3b5c996b3738?signature=f7016f3e60727cab88de78889d2f3084611d3326acb285a89c18fd9a6425489c\"
      },
      \"total\": 0,
      \"status\": \"paid\",
      \"tax_usd\": 0,
      \"currency\": \"USD\",
      \"refunded\": false,
      \"store_id\": 25678,
      \"subtotal\": 0,
      \"tax_name\": null,
      \"tax_rate\": \"0.00\",
      \"setup_fee\": 0,
      \"test_mode\": true,
      \"total_usd\": 0,
      \"user_name\": \"Bruno Falcao\",
      \"created_at\": \"2024-02-01T13:48:42.000000Z\",
      \"identifier\": \"a71e3578-2975-4771-9e5b-3b5c996b3738\",
      \"updated_at\": \"2024-02-01T13:48:42.000000Z\",
      \"user_email\": \"me@brunofalcao.dev\",
      \"customer_id\": 1623583,
      \"refunded_at\": null,
      \"order_number\": 2567813,
      \"subtotal_usd\": 0,
      \"currency_rate\": \"1.00000000\",
      \"setup_fee_usd\": 0,
      \"tax_formatted\": \"$0.00\",
      \"discount_total\": 0,
      \"total_formatted\": \"$0.00\",
      \"first_order_item\": {
        \"id\": 1982824,
        \"price\": 0,
        \"order_id\": 2021602,
        \"price_id\": 206098,
        \"quantity\": 1,
        \"test_mode\": true,
        \"created_at\": \"2024-02-01T13:48:42.000000Z\",
        \"product_id\": 154629,
        \"updated_at\": \"2024-02-01T13:48:42.000000Z\",
        \"variant_id\": 192214,
        \"product_name\": \"Mastering Nova - Orion\",
        \"variant_name\": \"Default\"
      },
      \"status_formatted\": \"Paid\",
      \"discount_total_usd\": 0,
      \"subtotal_formatted\": \"$0.00\",
      \"setup_fee_formatted\": \"$0.00\",
      \"discount_total_formatted\": \"$0.00\"
    },
    \"relationships\": {
      \"store\": {
        \"links\": {
          \"self\": \"https://api.lemonsqueezy.com/v1/orders/2021602/relationships/store\",
          \"related\": \"https://api.lemonsqueezy.com/v1/orders/2021602/store\"
        }
      },
      \"customer\": {
        \"links\": {
          \"self\": \"https://api.lemonsqueezy.com/v1/orders/2021602/relationships/customer\",
          \"related\": \"https://api.lemonsqueezy.com/v1/orders/2021602/customer\"
        }
      },
      \"order-items\": {
        \"links\": {
          \"self\": \"https://api.lemonsqueezy.com/v1/orders/2021602/relationships/order-items\",
          \"related\": \"https://api.lemonsqueezy.com/v1/orders/2021602/order-items\"
        }
      },
      \"license-keys\": {
        \"links\": {
          \"self\": \"https://api.lemonsqueezy.com/v1/orders/2021602/relationships/license-keys\",
          \"related\": \"https://api.lemonsqueezy.com/v1/orders/2021602/license-keys\"
        }
      },
      \"subscriptions\": {
        \"links\": {
          \"self\": \"https://api.lemonsqueezy.com/v1/orders/2021602/relationships/subscriptions\",
          \"related\": \"https://api.lemonsqueezy.com/v1/orders/2021602/subscriptions\"
        }
      },
      \"discount-redemptions\": {
        \"links\": {
          \"self\": \"https://api.lemonsqueezy.com/v1/orders/2021602/relationships/discount-redemptions\",
          \"related\": \"https://api.lemonsqueezy.com/v1/orders/2021602/discount-redemptions\"
        }
      }
    }
  },
  \"meta\": {
    \"test_mode\": true,
    \"event_name\": \"order_created\",
    \"webhook_id\": \"e3351675-f89f-4620-8b1a-9ec9bb06b141\",
    \"custom_data\": {
      \"token\": \"CZbiU8tdwxzez6DNGkIv4wTW9CFjgJ1oExIDBQWB\"
    }
  }
}
";

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
