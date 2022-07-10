<?php

namespace Eduka\Payments\Commands;

use Brunocfalcao\Helpers\Traits\CanValidateArguments;
use Eduka\Abstracts\EdukaCommand;
use Eduka\Payments\Hashcode;
use Illuminate\Support\Str;
use ProtoneMedia\LaravelPaddle\Events\PaymentSucceeded;

class WebhookTest extends EdukaCommand
{
    use CanValidateArguments;

    protected $signature = 'eduka:webhook-test';

    protected $description = 'Creates an order example with some random data';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $data = [
            'alert_id' => rand(2000000000, 2999999999),
            'alert_name' => 'payment_succeeded',
            'balance_currency' => 'USD',
            'balance_earnings' => 355.66,
            'balance_fee' => 990.23,
            'balance_gross' => 139.18,
            'balance_tax' => 101.68,
            'checkout_id' => Str::uuid(),
            'country' => 'AU',
            'coupon' => 'Coupon 9',
            'currency' => 'EUR',
            'customer_name' => 'Bruno Falcao',
            'earnings' => 778.37,
            'email' => 'klittle@example.org',
            'event_time' => '2022-06-09 18:06:59',
            'fee' => 0.46,
            'ip' => '6.141.195.142',
            'marketing_consent' => 1,
            'order_id' => 1,
            'passthrough' => '{"visit_id":"1","auth_hashcode":"3gdf67d23yud"}',
            'payment_method' => 'paypal',
            'payment_tax' => 0.6,
            'product_id' => 1,
            'product_name' => 'Mastering Nova Official Course',
            'quantity' => 10,
            'receipt_url' => 'https://sandbox-my.paddle.com/receipt/9/3bc9762d1d8bd32-f8d7ba6deb',
            'sale_gross' => 626.88,
            'used_price_override' => false,
            'p_signature' => 'pUQbHSH9fr8qlE71McyrH//hioUirKx3qqHZIBFH2AKasTt7TnmV8FBGY21CS3JukBaCb6WzPj3xu2+h5sMAHzw/NjnvZyURnce++BG7p0jpr0K6cDsMEvQx97y+bf6AOs9jUEaSFAGiCUKKwyLYVMvv3OGqKcL7V2E5rzCcCI/zkej0Gt/QTEqVQQjfVUz3b2SCbp8mgFPt85swwOI+tpJcovYNRbsuUgnqRsJFLYoqaVW06TbASShG9iZnmwauyur1lyqVOdAfHXZhzPJ9K2hvOYV9vEStO6xBHoSorIMcEdlAzw8tlDIcfUSeS25O76x1IhMLLsRCADP7V3Km4FRk/TJYnzfr1lV7u3SpVguObBanp77z+zAZ2RCFCsy0cw4fdzTJtmhqQ0sRcf1ydlGcith3v/FrQeUfuYIJS9AfQIX+h3f1+jKhNt+wS5ey0S+12boA2SqpicnwriSAClAI1kzscrEHh/54gUNlDDIMcEKOnPTZcnjaJG4/0Y8iRGhG2OvMGXkK7Oq/gr8MsKEFVMK7H8cMI2uD210aOFDmDI3BD98swhndnU2TODo5pQgrZXCOyAP8nZcXNaw5PPDdiE7Ip3tqC+JcTQ92DrMqXnfsFWRkdoLiF+Xok2/YTFb7sQw28or3qY5VF6AV0LYQn2boLzLIdgkM2sd0pbo=',
        ];

        $code = Hashcode::create();

        $this->info('hashcode: '.$code);

        $this->info('Is burnt? '.bool_to_str(Hashcode::with($code)->isBurnt()));
        $this->info('Exists? '.bool_to_str(Hashcode::with($code)->exists()));
        $this->info('Existed? '.bool_to_str(Hashcode::with($code)->existed()));

        $this->info('burning '.$code);

        Hashcode::with($code)->burn();

        $this->info('Is burnt? '.bool_to_str(Hashcode::with($code)->isBurnt()));

        $this->info('Exists? '.bool_to_str(Hashcode::with($code)->exists()));
        $this->info('Existed? '.bool_to_str(Hashcode::with($code)->existed()));

        //PaymentSucceeded::fire($data, request());
    }
}
