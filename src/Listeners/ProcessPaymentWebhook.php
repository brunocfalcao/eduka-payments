<?php

namespace Eduka\Payments\Listeners;

use Eduka\Cube\Models\Variant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProcessPaymentWebhook implements ShouldQueue
{
    public function handle(array $payload)
    {
        // $variantId = Lemonsqueezy variant ID.
        $variantId = $payload['data']['attributes']['first_order_item']['variant_id'];

        // Variant exists?
        $variant = Variant::where('lemon_squeezy_variant_id', $lsID)->firstOr(function () {
            throw new ModelNotFoundException();
        });

        // Course exists?



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

        // attach user to course
        $course->users()->sync([$user->id]);
    }
}
