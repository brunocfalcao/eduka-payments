<?php

namespace Eduka\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Brunocfalcao\Cerebrus\Cerebrus;
use Eduka\Cube\Actions\Coupon\FindCoupon;
use Eduka\Cube\Models\Coupon;
use Eduka\Cube\Models\Course;
use Eduka\Cube\Models\Order;
use Eduka\Cube\Models\User;
use Eduka\Nereus\Facades\Nereus;
use Eduka\Payments\Actions\LemonSqueezyCoupon;
use Eduka\Payments\Actions\LemonSqueezyWebhookPayloadExtractor;
use Eduka\Payments\Actions\UserCountryFromIP;
use Eduka\Payments\Events\CallbackFromPaymentGateway;
use Eduka\Payments\Events\RedirectAwayToPaymentGateway;
use Eduka\Payments\Notifications\WelcomeExistingUserToCourseNotification;
use Eduka\Payments\Notifications\WelcomeNewUserToCourseNotification;
use Eduka\Payments\PaymentProviders\LemonSqueezy\LemonSqueezy;
use Eduka\Payments\PaymentProviders\LemonSqueezy\Responses\CreatedCheckoutResponse;
use Exception;
use Hibit\Country\CountryRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private string $lemonSqueezyApiKey;

    private Course $course;

    private Cerebrus $session;

    public function __construct()
    {
        $this->session = new Cerebrus();
        $this->lemonSqueezyApiKey = env('LEMON_SQUEEZY_API_KEY', '');
        $this->course = Nereus::course();
    }

    public function redirectToCheckoutPage(HttpRequest $request): RedirectResponse
    {
        if (! $this->course) {
            return redirect()->back();
        }

        dd(request()->headers);

        $userCountry = json_decode(file_get_contents('https://api.country.is/'.request()->public_ip()));

        if ($userCountry?->country) {
            $this->ensureCouponOnLemonSqueezy($userCountry->country);
        }

        $paymentsApi = new LemonSqueezy($this->lemonSqueezyApiKey);

        $nonceKey = Str::random();
        $trackingID = Str::random(10);

        $this->session->set('eduka:nereus:nonce', $nonceKey);

        $checkoutResponse = $this->createCheckout($paymentsApi, $this->course, $nonceKey, $trackingID);

        $checkoutUrl = (new CreatedCheckoutResponse($checkoutResponse))->checkoutUrl();

        event(new RedirectAwayToPaymentGateway($trackingID, LemonSqueezy::GATEWAY_ID));

        return redirect()->away($checkoutUrl);
    }

    public function handleWebhook(HttpRequest $request)
    {
        $json = $request->all();
        event(new CallbackFromPaymentGateway($json['meta']['custom_data']['tracking_id'], LemonSqueezy::GATEWAY_ID));

        $courseId = $json['meta']['custom_data']['course_id'];
        $course = Course::find($courseId);

        if (! $course) {
            Log::error('could not find course with id '.$courseId);

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
            'response_body' => json_encode($json),
        ]));

        $user->notify(
            $newUser ?
                new WelcomeNewUserToCourseNotification($course->name) :
                new WelcomeExistingUserToCourseNotification($course->name)
        );

        // attach user to course
        $course->users()->sync([$user->id]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * @throws Exception
     */
    private function createCheckout(LemonSqueezy $paymentsApi, Course $course, string $nonceKey, string $trackingID): array
    {
        try {
            $responseString = $paymentsApi
                ->setRedirectUrl(route('purchase.callback', $nonceKey))
                ->setExpiresAt(now()->addHours(2)->toString())
                ->setCustomData([
                    'course_id' => (string) $course->id,
                    'tracking_id' => $trackingID,
                ])
                ->setCustomPrice($course->priceInCents())
                ->setStoreId($course->paymentProviderStoreId())
                ->setVariantId($course->paymentProviderVariantId())
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
