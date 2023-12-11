<?php

namespace Eduka\Payments\Listeners;

use Eduka\Cube\Models\Variant;
use Eduka\Payments\Events\CallbackFromPaymentGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

        $email = $json['data']['attributes']['user_email'];

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
        $user = User::firstWhere('email', $email);

        if ($user) {
        } else {
            $user = User::create([

            ]);
        }

        // save everything in the response
        $extracted = (new LemonSqueezyWebhookPayloadExtractor)->extract($json);

        $order = Order::create(array_merge($extracted, [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'variant_id' => $variant->id,
            'response_body' => $json,
        ]));

        // attach user to course
        $course->users()->sync([$user->id]);
    }
}
