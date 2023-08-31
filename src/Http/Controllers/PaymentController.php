<?php

namespace Eduka\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Brunocfalcao\Cerebrus\Cerebrus;
use Eduka\Cube\Actions\Coupon\FindCoupon;
use Eduka\Cube\Models\Coupon;
use Eduka\Cube\Models\Course;
use Eduka\Cube\Models\Order;
use Eduka\Cube\Models\User;
use Eduka\Nereus\NereusServiceProvider;
use Eduka\Payments\Actions\LemonSqueezyCoupon;
use Eduka\Payments\Actions\UserCountryFromIP;
use Eduka\Payments\Events\CallbackFromPaymentGateway;
use Eduka\Payments\Events\RedirectAwayToPaymentGateway;
use Eduka\Payments\PaymentProviders\LemonSqueezy\LemonSqueezy;
use Eduka\Payments\PaymentProviders\LemonSqueezy\Responses\CreatedCheckoutResponse;
use Eduka\Payments\Notifications\WelcomeNewUserToCourseNotification;
use Eduka\Payments\Notifications\WelcomeExistingUserToCourseNotification;
use Exception;
use Illuminate\Http\Request as HttpRequest;
use Hibit\Country\CountryRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private Cerebrus $session;
    private string $lemonSqueezyApiKey;
    private Course $course;

    public function __construct(Cerebrus $session)
    {
        $this->lemonSqueezyApiKey = env('LEMON_SQUEEZY_API_KEY', '');
        $this->session = $session;
        $this->course = $this->session->get(NereusServiceProvider::COURSE_SESSION_KEY);
    }

    public function redirectToCheckoutPage(HttpRequest $request)
    {
        if (!$this->course) {
            return redirect()->back();
        }

        // @todo refactor
        $userCountry = UserCountryFromIP::get($request);

        if ($userCountry) {
            $this->ensureCouponOnLemonSqueezy($userCountry, $this->course->id);
        }

        $paymentsApi = new LemonSqueezy($this->lemonSqueezyApiKey);

        $nonceKey = Str::random();
        $trackingID = Str::random(10);

        $checkoutResponse = $this->createCheckout($paymentsApi, $this->course, $nonceKey, $trackingID);

        $checkoutUrl = (new CreatedCheckoutResponse($checkoutResponse))->checkoutUrl();

        $this->session->set(NereusServiceProvider::NONCE_KEY, $nonceKey);

        event(new RedirectAwayToPaymentGateway($trackingID, LemonSqueezy::GATEWAY_ID));

        return redirect()->away($checkoutUrl . "&aff=1234");
    }

    private function createCheckout(LemonSqueezy $paymentsApi, Course $course, string $nonceKey, string $trackingID)
    {
        try {
            return $paymentsApi
                ->setRedirectUrl(route('purchase.callback', $nonceKey))
                ->setExpiresAt(now()->addHours(2)->toString())
                ->setCustomData([
                    'course_id' => (string) $course->id,
                    'tracking_id' => $trackingID,
                ])
                ->setCustomPrice($course->priceInCents())
                ->setStoreId($course->paymentProviderStoreId())
                ->setVariantId($course->paymentProviderProductId())
                ->createCheckout();
        } catch (\Exception $e) {
            $this->log("could not create checkout", $e);
            throw $e;
        }
    }

    private function ensureCouponOnLemonSqueezy(CountryRecord $country, int $courseId)
    {
        $coupon = FindCoupon::fromCountryRecord(strtoupper($country->getIsoCode()), $courseId);
        // coupon does not exists in database
        if (!$coupon) {
            return false;
        }

        // check if coupon has remote reference id, if yes, it means coupon also exists in lemon squeezy
        if ($coupon->hasRemoteReference()) {
            return true;
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

        if (!$reference) {
            // could not create coupon in lemon squezzy
            return false;
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

    public function handleWebhook(HttpRequest $request)
    {
        $json = $request->all();
        event(new CallbackFromPaymentGateway($json['meta']['custom_data']['tracking_id'], LemonSqueezy::GATEWAY_ID));

        $courseId = $json['meta']['custom_data']['course_id'];
        $course = Course::find($courseId);

        if (! $course) {
            Log::error('could not find course with id ' . $courseId);
            return response()->json(['status' => 'ok']);
        }

        // check if user exists or not
        $userEmail = $json['data']['attributes']['user_email'];

        list($user, $newUser) = $this->findOrCreateUser($userEmail, $json['data']['attributes']['user_name']);

        // save everything in the response
        $order = Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'response_body' => json_encode($json),
            // @todo add more column
        ]);

        $user->notify(
            $newUser ?
                new WelcomeNewUserToCourseNotification($course->name) :
                new WelcomeExistingUserToCourseNotification($course->name)
        );

        // attach user to course
        $course->users()->sync([$user->id]);

        return response()->json(['status' => 'ok']);
    }

    private function findOrCreateUser(string $email, string $name)
    {
        $user = User::where('email', $email)->first();
        $newUser = false;

        if (!$user) {
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
