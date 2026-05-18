<?php

namespace Modules\Gateways\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Entities\StripeEvent;

class VitoStripeController extends Controller
{
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1|max:10000',
            'currency' => 'nullable|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $stripeSecret = businessConfig('stripe_secret_key')?->value;
        if (!$stripeSecret) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 403);
        }

        try {
            \Stripe\Stripe::setApiKey($stripeSecret);

            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => (int) round($request->amount * 100),
                'currency' => $request->input('currency', 'usd'),
                'metadata' => [
                    'user_id' => $request->user()->id,
                    'type' => 'wallet_topup',
                ],
            ]);

            StripeEvent::create([
                'stripe_event_id' => $paymentIntent->id,
                'type' => 'payment_intent.created',
                'user_id' => $request->user()->id,
                'amount' => $request->amount,
                'currency' => $request->input('currency', 'usd'),
                'status' => 'pending',
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return response()->json(responseFormatter(DEFAULT_200, [
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ]));

        } catch (\Exception $e) {
            return response()->json(responseFormatter(constant: DEFAULT_400), 403);
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        $stripeWebhookSecret = businessConfig('stripe_webhook_secret')?->value;
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            if ($stripeWebhookSecret) {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $stripeWebhookSecret);
            } else {
                return response()->json(['error' => 'Webhook secret not configured'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $eventType = is_object($event) && property_exists($event, 'type') ? $event->type : ($event['type'] ?? '');
        $data = is_object($event) && property_exists($event, 'data') ? $event->data->object : ($event['data']['object'] ?? null);

        if ($eventType === 'payment_intent.succeeded' && $data) {
            $paymentIntentId = is_object($data) ? $data->id : ($data['id'] ?? '');
            $userId = is_object($data) && isset($data->metadata->user_id)
                ? $data->metadata->user_id
                : ($data['metadata']['user_id'] ?? null);

            DB::transaction(function () use ($paymentIntentId, $userId, $data) {
                $stripeEvent = StripeEvent::where('payment_intent_id', $paymentIntentId)
                    ->lockForUpdate()
                    ->first();

                if (!$stripeEvent || $stripeEvent->status === 'succeeded') {
                    return;
                }

                $stripeEvent->update(['status' => 'succeeded']);

                if ($userId) {
                    $user = \Modules\UserManagement\Entities\User::find($userId);
                    if ($user && $user->userAccount) {
                        $amount = is_object($data) ? $data->amount / 100 : (($data['amount'] ?? 0) / 100);
                        $user->userAccount()->increment('wallet_balance', $amount);
                    }
                }
            });
        }

        return response()->json(['status' => 'ok']);
    }
}
