<?php

namespace Eduka\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Brunocfalcao\Cerebrus\Cerebrus;
use Brunocfalcao\Tokenizer\Models\Token;
use Eduka\Cube\Models\Course;
use Eduka\Cube\Models\Order;
use Eduka\Cube\Models\Variant;
use Eduka\Nereus\Facades\Nereus;
use Eduka\Payments\PaymentProviders\LemonSqueezy\LemonSqueezy;
use Eduka\Payments\PaymentProviders\LemonSqueezy\Responses\CreatedCheckoutResponse;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;

class PaymentController extends Controller
{
    private string $lemonSqueezyApiKey;

    private Course $course;

    private Variant $variant;

    private Cerebrus $session;

    private $request;

    public function __construct()
    {
        $this->session = new Cerebrus();
        $this->lemonSqueezyApiKey = env('LEMON_SQUEEZY_API_KEY', '');
    }

    public function thanksForBuying()
    {
        return view('course::thanks-for-buying');
    }

    public function redirectToCheckoutPage(HttpRequest $request): RedirectResponse
    {
        $this->course = Nereus::course();

        if (! $this->course) {
            return redirect()->back();
        }

        $this->variant = Variant::firstWhere('uuid', $request->input('variant'));

        $userCountry ??= request()->header('cf-ipcountry');

        $paymentsApi = new LemonSqueezy($this->lemonSqueezyApiKey);

        $checkoutResponse = $this->createCheckout($paymentsApi, $this->variant, Token::createToken());

        $checkoutUrl = (new CreatedCheckoutResponse($checkoutResponse))->checkoutUrl();

        return redirect()->away($checkoutUrl);
    }

    public function handleWebhook(HttpRequest $request)
    {
        $this->request = $request;

        /**
         * Controller itself will:
         * 1. Validate the token from the webhook payload.
         * 2. Add the order into the database.
         *
         * The remaining activities are called via the event
         * that will be triggered on the order.created
         * observer.
         */
        try {
            // Validates and burns token.
            $this->validateWebhookToken();

            // The variant UUID should be the same as the LS variant id instance.
            $this->checkVariantUuid();

            // Store the order and start the course assignment process.
            $this->storeOrder($request);

            // We can return ok. Any exception needs to be treated later.
            return response()->json();
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function checkVariantUuid()
    {
        if (! array_key_exists('variant_uuid', $this->request->all()['meta']['custom_data'])) {
            throw new Exception('Variant UUID not present. Your IP was blacklisted');
        }

        $uuid = $this->request->all()['meta']['custom_data']['variant_uuid'];
        $variantId = $this->request->all()['data']['attributes']['first_order_item']['variant_id'];

        if (! Variant::firstWhere('lemon_squeezy_variant_id', $variantId)?->uuid == $uuid) {
            throw new Exception('Variant UUID vs LS Variant UUID mismatch');
        }
    }

    protected function validateWebhookToken()
    {
        if (! array_key_exists('token', $this->request->all()['meta']['custom_data'])) {
            throw new Exception('Invalid token. Your IP was blacklisted');
        }

        $token = $this->request->all()['meta']['custom_data']['token'];

        if (! $token) {
            throw new Exception('Invalid token. Your IP was blacklisted');
        }

        if (! Token::isValid($token)) {
            throw new Exception('Invalid token hash code');
        }

        // Burn token so it cannot be used again.
        Token::burn($token);
    }

    protected function storeOrder()
    {
        $payload = $this->request->all();

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

        $data['response_body'] = $this->request->all();

        Order::create($data);
    }

    protected function createCheckout(LemonSqueezy $paymentsApi, Variant $variant, Token $token): array
    {
        try {
            $responseString = $paymentsApi
                ->setRedirectUrl(route('purchase.callback', $token->token))
                ->setExpiresAt(now()->addHours(2)->toString())
                ->setCustomData([
                    'variant_uuid' => $variant->uuid,
                    'token' => Token::createToken()->token,
                ]);

            // Conditionally applying setCustomPrice.
            if ($variant->lemon_squeezy_price_override) {
                $responseString = $responseString
                    ->setCustomPrice(
                        $variant->lemon_squeezy_price_override * 100
                    );
            }

            // Eager load the course.
            $variant = Variant::with('course')->find($variant->id);

            $responseString = $responseString
                ->setStoreId($variant->course->lemon_squeezy_store_id)
                ->setVariantId($variant->lemon_squeezy_variant_id)
                ->createCheckout();

            $raw = json_decode($responseString, true);

            if (isset($raw['errors'])) {
                throw new Exception(reset($raw['errors'][0]));
            }

            return $raw;
        } catch (Exception $e) {
            throw new Exception('could not create checkout - '.$e->getMessage());
        }
    }
}
