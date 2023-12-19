<?php

namespace Eduka\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Brunocfalcao\Cerebrus\Cerebrus;
use Brunocfalcao\LaravelHelpers\Classes\Token;
use Eduka\Cube\Actions\Coupon\FindCoupon;
use Eduka\Cube\Models\Coupon;
use Eduka\Cube\Models\Course;
use Eduka\Cube\Models\Order;
use Eduka\Cube\Models\Variant;
use Eduka\Nereus\Facades\Nereus;
use Eduka\Payments\Actions\LemonSqueezyCoupon;
use Eduka\Payments\PaymentProviders\LemonSqueezy\LemonSqueezy;
use Eduka\Payments\PaymentProviders\LemonSqueezy\Responses\CreatedCheckoutResponse;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    public function redirectToCheckoutPage(HttpRequest $request): RedirectResponse
    {
        $this->course = Nereus::course();

        if (! $this->course) {
            return redirect()->back();
        }

        $this->variant = $this->course->getVariantOrDefault(
            $request->input('variant')
        );

        if (request()->header('cf-ipcountry')) {
            $userCountry = request()->header('cf-ipcountry');
        }

        $paymentsApi = new LemonSqueezy($this->lemonSqueezyApiKey);

        $nonceKey = Str::random();
        $trackingID = Token::create();

        $this->session->set('eduka:nereus:nonce', $nonceKey);

        $checkoutResponse = $this->createCheckout($paymentsApi, $this->variant, $nonceKey, $trackingID);

        $checkoutUrl = (new CreatedCheckoutResponse($checkoutResponse))->checkoutUrl();

        return redirect()->away($checkoutUrl);
    }

    public function handleWebhook(HttpRequest $request)
    {
        $this->request = $request;

        /**
         * Controller itself will:
         * 1. Validate the signature of the request (in non-local envs).
         * 2. Add the order into the database.
         *
         * The remaining activities are called via the event
         * that will be triggered on the order.created
         * observer.
         */
        try {
            if (app()->environment() != 'local') {
                $this->validateWebhookSignature($request);
            }

            $this->storeOrder($request);

            return response()->json(['status' => 'roger that'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function validateWebhookSignature()
    {
        $secret = env('LEMON_SQUEEZY_SECRET', '');
        $payload = $request->getContent();
        $hash = hash_hmac('sha256', $payload, $secret);
        $signature = $request->header('X-Signature', '');

        if (! hash_equals($hash, $signature)) {
            throw new Exception('Invalid signature.');
        }
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
            'product_id' => 'data.attributes.first_order_item.product_id',
            'variant_id' => 'data.attributes.first_order_item.variant_id',
            'product_name' => 'data.attributes.first_order_item.product_name',
            'variant_name' => 'data.attributes.first_order_item.variant_name',
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

    protected function createCheckout(LemonSqueezy $paymentsApi, Variant $variant, string $nonceKey, string $trackingID): array
    {
        try {
            $responseString = $paymentsApi
                ->setRedirectUrl(route('purchase.callback', $nonceKey))
                ->setExpiresAt(now()->addHours(2)->toString())
                ->setCustomData([
                    'course_variant_uuid' => $variant->uuid,
                    'tracking_id' => $trackingID,
                ]);

            // Conditionally applying setCustomPrice.
            if ($variant->priceOverrideInCents()) {
                $responseString = $responseString
                    ->setCustomPrice(
                        $variant->priceOverrideInCents()
                    );
            }

            $responseString = $responseString
                ->setStoreId($variant->course->paymentProviderStoreId())
                ->setVariantId($variant->lemonsqueezy_variant_id)
                ->createCheckout();

            $raw = json_decode($responseString, true);

            if (isset($raw['errors'])) {
                throw new Exception(reset($raw['errors'][0]));
            }

            return $raw;
        } catch (\Exception $e) {
            $this->log('could not create checkout', $e);
            throw $e;
        }
    }

    /**
     * @aryan:
     * So, the logic should be:
     * 1. Visitor visits website.
     * 2. You grab the country header (already on the codebase).
     * 3. You check if you have the coupon created on our local
     *    coupons table. If not:
     *    3.1. You create a global coupon on the lemonsqueezy,
     *         but please trigger an async job queue=default
     *         because I don't the website to slow down because
     *         of that api request (can take sometime I guess).
     *    3.2. You add the coupon to our eduka database. No need
     *         to have a "per course" logic, because these are
     *         global product-wide coupons. You check the PPP.
     * 4. You always show on the website the coupon in case
     *    the courses.enable_purchase_power_parity = true. If
     *    false, we don't show the coupon (although, if they
     *    know how to use it, they can use it surely).
     *
     * At the end, you just add coupons as long as new visitors
     * countries are arriving. We don't create them all at once.
     */
    protected function ensureCouponOnLemonSqueezy(string $country): void
    {
        $coupon = FindCoupon::fromCountryRecord($country, Nereus::course()->id);

        // coupon does not exist in database
        if (! $coupon) {
            return;
        }

        // check if coupon has remote reference id, if yes, it means coupon also exists in lemon squeezy
        if ($coupon->hasRemoteReference()) {
            return;
        }

        // reaching here means coupon exists in database, but not on lemon squeezy.
        // create coupon on lemon squeezy and update remote reference id
        $code = $coupon->generateCodeForCountry(strtoupper($country->getName()), strtoupper($country->getIsoCode()));

        $couponApi = new LemonSqueezy($this->lemonSqueezyApiKey);

        $reference = (new LemonSqueezyCoupon)->create(
            $couponApi,
            $this->course->paymentProviderStoreId(),
            $code,
            $coupon->discount_amount,
            $coupon->is_flat_discount,
        );

        if (! $reference) {
            // could not create coupon in lemon squezzy
            return;
        }

        // coupon created, update $coupon in local db
        $coupon->update([
            'code' => $code,
            'remote_reference_id' => $reference,
        ]);
    }

    protected function log(string $message, ?Exception $e, array $data = [])
    {
        if ($e) {
            $data[] = [
                'message' => $e->getMessage(),
            ];
        }

        Log::error($message, $data);
    }
}
