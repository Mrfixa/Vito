<?php

namespace Modules\Gateways\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Entities\MartOrder;
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
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $stripeConfig = DB::table('settings')
            ->where('key_name', 'stripe')
            ->where('settings_type', PAYMENT_CONFIG)
            ->first();
        if (!$stripeConfig) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 500);
        }
        $stripeValues = $stripeConfig->mode === 'live'
            ? json_decode($stripeConfig->live_values, true)
            : json_decode($stripeConfig->test_values, true);
        $stripeSecret = $stripeValues['api_key'] ?? null;
        if (!$stripeSecret) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 500);
        }

        try {
            \Stripe\Stripe::setApiKey($stripeSecret);

            // Idempotency key: one PaymentIntent per user per day for wallet top-ups.
            // Using user_id + date means retrying within the same calendar day is safe
            // and won't create duplicate charges.
            $amountCents = (int) round($request->amount * 100);
            $idempotencyKey = 'pi_' . $request->user()->id . '_walletTopup_' . date('Ymd') . '_' . $amountCents;

            $paymentIntent = retry(3, function () use ($amountCents, $request, $idempotencyKey) {
                return \Stripe\PaymentIntent::create(
                    [
                        'amount'   => $amountCents,
                        'currency' => $request->input('currency', 'usd'),
                        'metadata' => [
                            'user_id' => $request->user()->id,
                            'type'    => 'wallet_topup',
                        ],
                    ],
                    ['idempotency_key' => $idempotencyKey],
                );
            }, 500);

            StripeEvent::firstOrCreate(
                ['stripe_event_id' => $paymentIntent->id],
                [
                    'type' => 'payment_intent.created',
                    'user_id' => $request->user()->id,
                    'amount' => $request->amount,
                    'currency' => $request->input('currency', 'usd'),
                    'status' => 'pending',
                    'payment_intent_id' => $paymentIntent->id,
                ]
            );

            return response()->json(responseFormatter(DEFAULT_200, [
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ]));

        } catch (\Exception $e) {
            return response()->json(responseFormatter(constant: DEFAULT_400), 400);
        }
    }

    public function createOrderPaymentIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $order = MartOrder::where('id', $request->order_id)
            ->where('customer_id', $request->user()->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if (!$order) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $stripeConfig = DB::table('settings')
            ->where('key_name', 'stripe')
            ->where('settings_type', PAYMENT_CONFIG)
            ->first();
        if (!$stripeConfig) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 500);
        }
        $stripeValues = $stripeConfig->mode === 'live'
            ? json_decode($stripeConfig->live_values, true)
            : json_decode($stripeConfig->test_values, true);
        $stripeSecret = $stripeValues['api_key'] ?? null;
        if (!$stripeSecret) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 500);
        }

        try {
            \Stripe\Stripe::setApiKey($stripeSecret);

            $amountCents = (int) round($order->total_amount * 100);
            $idempotencyKey = 'pi_order_' . $order->id;

            $paymentIntent = retry(3, function () use ($amountCents, $request, $order, $idempotencyKey) {
                return \Stripe\PaymentIntent::create(
                    [
                        'amount'   => $amountCents,
                        'currency' => 'usd',
                        'metadata' => [
                            'user_id'  => $request->user()->id,
                            'type'     => 'order_payment',
                            'order_id' => $order->id,
                        ],
                    ],
                    ['idempotency_key' => $idempotencyKey],
                );
            }, 500);

            StripeEvent::firstOrCreate(
                ['stripe_event_id' => $paymentIntent->id],
                [
                    'type'              => 'payment_intent.created',
                    'user_id'           => $request->user()->id,
                    'amount'            => $order->total_amount,
                    'currency'          => 'usd',
                    'status'            => 'pending',
                    'payment_intent_id' => $paymentIntent->id,
                    'metadata'          => ['type' => 'order_payment', 'order_id' => $order->id],
                ]
            );

            return response()->json(responseFormatter(DEFAULT_200, [
                'client_secret'     => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ]));

        } catch (\Exception $e) {
            return response()->json(responseFormatter(constant: DEFAULT_400), 400);
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        $stripeWebhookSecret = env('STRIPE_WEBHOOK_SECRET');
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            if ($stripeWebhookSecret) {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $stripeWebhookSecret);
            } else {
                return response()->json(responseFormatter(DEFAULT_400, null, null, 'Webhook secret not configured'), 500);
            }
        } catch (\Exception $e) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, 'Invalid signature'), 400);
        }

        // Check Stripe event ID idempotency first — if we have already stored a
        // 'succeeded' record for this exact Stripe event, return immediately so
        // retried webhook deliveries never double-credit the wallet.
        $stripeEventId = is_object($event) && property_exists($event, 'id') ? $event->id : ($event['id'] ?? null);

        if ($stripeEventId) {
            $alreadyProcessed = StripeEvent::where('stripe_event_id', $stripeEventId)
                ->where('status', 'succeeded')
                ->exists();
            if ($alreadyProcessed) {
                return response()->json(responseFormatter(DEFAULT_200, ['status' => 'already_processed']));
            }
        }

        $eventType = is_object($event) && property_exists($event, 'type') ? $event->type : ($event['type'] ?? '');
        $data = is_object($event) && property_exists($event, 'data') ? $event->data->object : ($event['data']['object'] ?? null);

        if ($eventType === 'payment_intent.succeeded' && $data) {
            $paymentIntentId = is_object($data) ? $data->id : ($data['id'] ?? '');
            $userId = is_object($data) && isset($data->metadata->user_id)
                ? $data->metadata->user_id
                : ($data['metadata']['user_id'] ?? null);
            $amount = is_object($data) ? $data->amount / 100 : (($data['amount'] ?? 0) / 100);
            $currency = is_object($data) ? ($data->currency ?? 'usd') : ($data['currency'] ?? 'usd');

            DB::transaction(function () use ($paymentIntentId, $stripeEventId, $userId, $amount, $currency) {
                $stripeEvent = StripeEvent::where('payment_intent_id', $paymentIntentId)
                    ->lockForUpdate()
                    ->first();

                if (!$stripeEvent) {
                    // Webhook arrived before createPaymentIntent stored the record.
                    // Create it now so we can proceed and ensure idempotency on retries.
                    $stripeEvent = StripeEvent::create([
                        'stripe_event_id' => $stripeEventId,
                        'type' => 'payment_intent.succeeded',
                        'user_id' => $userId,
                        'amount' => $amount,
                        'currency' => $currency,
                        'status' => 'pending',
                        'payment_intent_id' => $paymentIntentId,
                    ]);
                }

                if ($stripeEvent->status === 'succeeded') {
                    return;
                }

                // Record the Stripe event ID alongside the status so future duplicate
                // webhook deliveries are rejected by the idempotency check above.
                $stripeEvent->update([
                    'status' => 'succeeded',
                    'stripe_event_id' => $stripeEventId ?? $stripeEvent->stripe_event_id,
                ]);

                $meta = $stripeEvent->metadata ?? [];
                $metaType = $meta['type'] ?? 'wallet_topup';

                if ($metaType === 'order_payment' && !empty($meta['order_id'])) {
                    MartOrder::where('id', $meta['order_id'])->update(['payment_status' => 'paid']);
                } elseif ($userId) {
                    $user = \Modules\UserManagement\Entities\User::find($userId);
                    if ($user) {
                        $account = $user->userAccount ?? $user->userAccount()->create([
                            'payable_balance'   => 0,
                            'receivable_balance'=> 0,
                            'received_balance'  => 0,
                            'pending_balance'   => 0,
                            'wallet_balance'    => 0,
                            'total_withdrawn'   => 0,
                            'referral_earn'     => 0,
                        ]);
                        $account->increment('wallet_balance', $amount);
                    }
                }
            });
        }

        return response()->json(responseFormatter(DEFAULT_200));
    }
}
