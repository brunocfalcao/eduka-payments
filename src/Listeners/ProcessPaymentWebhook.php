<?php

namespace Eduka\Payments\Listeners;

use Eduka\Cube\Models\Order;
use Eduka\Cube\Models\User;
use Eduka\Cube\Models\Variant;
use Eduka\Payments\Actions\LemonSqueezyWebhookPayloadExtractor;
use Eduka\Payments\Events\CallbackFromPaymentGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class ProcessPaymentWebhook implements ShouldQueue
{
    public function handle(CallbackFromPaymentGateway $event)
    {
        $payload = $event->payload;

        // $variantId = Lemonsqueezy variant ID.
        $variantId = $payload['data']['attributes']['first_order_item']['variant_id'];

        // Variant exists?
        $variant = Variant::where('lemon_squeezy_variant_id', $variantId)->firstOr(function () {
            throw new ModelNotFoundException('Lemon Squeezy variant from webhook not found');
        });

        // Course exists?
        $course = $variant->course->firstOr(function () {
            throw new ModelNotFoundException('Course instance not found');
        });

        $email = $payload['data']['attributes']['user_email'];

        /**
         * Email doesn't exist?
         * -> Create user.
         * -> Send thanks for buying & please reset your password here.
         * -> Assign user to variant & course.
         *
         * Email exist?
         * -> Do not create user.
         * -> Send thanks for buying (without reset password).
         * -> Assign user to variant & course.
         */
        $user = User::create([
            'email' => $email,
            'password' => bcrypt(Str::random(20)),
            'receives_notifications' => true,
        ]);

        // save everything in the response
        $extracted = (new LemonSqueezyWebhookPayloadExtractor)->extract($payload);

        $order = Order::create(array_merge($extracted, [
            'user_id' => $user->id,
            'response_body' => $payload,
        ]));

        /**
         * Send notification to user. If it's a new user it should
         * generate a password reset link. If it's an existing user
         * then just thank for buying the course.
         */
        if ($user->wasRecentlyCreated) {
            // Generate a reset password link.
            $token = Password::broker()->createToken($user);

            return url(route('eduka.dev.reset-password', [
                'token' => $token,
                'email' => $user->email,
            ], false));

            var_dump($token);
        }
    }
}
