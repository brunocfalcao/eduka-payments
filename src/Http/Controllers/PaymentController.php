<?php

namespace Eduka\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Brunocfalcao\Cerebrus\Cerebrus;
use Eduka\Cube\Actions\Coupon\FindCoupon;
use Eduka\Cube\Models\Coupon;
use Eduka\Cube\Models\Course;
use Eduka\Cube\Models\Order;
use Eduka\Cube\Models\User;
use Eduka\Cube\Models\Variant;
use Eduka\Nereus\Facades\Nereus;
use Eduka\Payments\Actions\LemonSqueezyCoupon;
use Eduka\Payments\Actions\LemonSqueezyWebhookPayloadExtractor;
use Eduka\Payments\Events\CallbackFromPaymentGateway;
use Eduka\Payments\Events\RedirectAwayToPaymentGateway;
use Eduka\Payments\Notifications\WelcomeExistingUserToCourseNotification;
use Eduka\Payments\Notifications\WelcomeNewUserToCourseNotification;
use Eduka\Payments\PaymentProviders\LemonSqueezy\LemonSqueezy;
use Eduka\Payments\PaymentProviders\LemonSqueezy\Responses\CreatedCheckoutResponse;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private string $lemonSqueezyApiKey;

    private Course $course;

    private Variant $variant;

    private Cerebrus $session;

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

        /**
         * @aryan: So, it's working now, you can take it from here.
         * The userCountry will have CH, PT, DE code, and it can be
         * matched on the country tables I hope. It uses this
         * nomenclature: https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
         * You might need to import the countries on that format to
         * have 100% match.
         */
        if (request()->header('cf-ipcountry')) {
            $userCountry = request()->header('cf-ipcountry');
        }

        $paymentsApi = new LemonSqueezy($this->lemonSqueezyApiKey);

        $nonceKey = Str::random();
        $trackingID = Str::random(10);

        $this->session->set('eduka:nereus:nonce', $nonceKey);

        /**
         * @bruno:
         * Logic should be a bit different. We need to receive a new variant
         * id from the course webpage (we can have like 2 offers from the
         * same course, early-access and full course) with different
         * pricing models. Changes:
         * - The webpage should return the variant ID, in case we would
         *   like to specify the variant directly.
         * - There should be a variants table/eloquent model.
         * - The product price can be removed from the db. I'll use
         *   directly the one from the product itself. Then apply
         *   specific coupons if needed. Or, if we don't have a specific
         *   variant price, then it would retrieve the one from LemonS.
         * - There should be a relationship between courses, and
         *   variants. Even if a visitor buys a variant, at the end it
         *   will be part of a course.
         *   In case a course only has one variant, then the landing
         *   page doesn't need to pass the variant itself, or if there
         *   are several variants, then eduka should pick the one that
         *   is marked as default.
         */
        $checkoutResponse = $this->createCheckout($paymentsApi, $this->variant, $nonceKey, $trackingID);

        $checkoutUrl = (new CreatedCheckoutResponse($checkoutResponse))->checkoutUrl();

        event(new RedirectAwayToPaymentGateway($trackingID, LemonSqueezy::GATEWAY_ID));

        return redirect()->away($checkoutUrl);
    }

    public function handleWebhook(HttpRequest $request)
    {
        /**
         * @aryan:
         * We need to validate the request. Check:
         * https://docs.lemonsqueezy.com/help/webhooks
         * on the chapter "Signing requests".
         *
         * @aryan:
         * The logic of the refund should also be implemented.
         * In case it's a refund, it should send a notification to the
         * user with the refund information automatically.
         */
        $json = $request->all();
        event(new CallbackFromPaymentGateway($json['meta']['custom_data']['tracking_id'], LemonSqueezy::GATEWAY_ID));

        $variantUuid = $json['meta']['custom_data']['course_variant_uuid'];
        $variant = Variant::firstWhere('uuid', $variantUuid);
        $course = Variant::firstWhere('uuid', $variantUuid)->course;

        if (! $course) {
            Log::error('could not find course for variant id '.$variantUuid);

            return response()->json(['status' => 'ok']);
        }

        // check if user exists or not
        $userEmail = $json['data']['attributes']['user_email'];

        [$user, $newUser] = $this->findOrCreateUser($userEmail, $json['data']['attributes']['user_name']);

        // save everything in the response
        $extracted = (new LemonSqueezyWebhookPayloadExtractor)->extract($json);

        $order = Order::create(array_merge($extracted, [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'variant_id' => $variant->id,
            'response_body' => $json,
        ]));

        /*
        $user->notify(
            $newUser ?
                new WelcomeNewUserToCourseNotification($course->name) :
                new WelcomeExistingUserToCourseNotification($course->name)
        );
        */

        // attach user to course
        $course->users()->sync([$user->id]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * @throws Exception
     */
    private function createCheckout(LemonSqueezy $paymentsApi, Variant $variant, string $nonceKey, string $trackingID): array
    {

        dd($variant->lemonsqueezy_variant_id);
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
    private function ensureCouponOnLemonSqueezy(string $country): void
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

    private function log(string $message, ?Exception $e, array $data = [])
    {
        if ($e) {
            $data[] = [
                'message' => $e->getMessage(),
            ];
        }

        Log::error($message, $data);
    }

    private function findOrCreateUser(string $email, string $name)
    {
        $user = User::where('email', $email)->first();
        $newUser = false;

        if (! $user) {
            $user = User::forceCreate([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random()),
                'uuid' => Str::uuid(),
                'created_at' => now(),
            ]);

            $newUser = true;
        }

        return [
            $user, $newUser,
        ];
    }
}
