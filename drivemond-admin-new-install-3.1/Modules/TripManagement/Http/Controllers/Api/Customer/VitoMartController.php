<?php

namespace Modules\TripManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\TripManagement\Entities\MartOrder;
use Modules\TripManagement\Entities\MartOrderItem;
use Modules\TripManagement\Entities\MartProduct;
use Modules\TripManagement\Entities\MartPromoCode;
use Modules\TripManagement\Entities\MartReview;
use Modules\TripManagement\Entities\StripeEvent;
use Modules\UserManagement\Entities\User;

class VitoMartController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $search = substr((string)($request->search ?? ''), 0, 100);
        $limit = min($request->input('limit', 20), 100);

        $products = MartProduct::where('is_active', true)
            ->when($request->category, fn($q, $cat) => $q->where('category', $cat))
            ->when($search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->zone_id, fn($q, $zoneId) => $q->where(function ($q) use ($zoneId) {
                $q->where('zone_id', $zoneId)->orWhereNull('zone_id');
            }))
            ->paginate($limit);

        return response()->json(responseFormatter(DEFAULT_200, $products));
    }

    public function productDetails(string $id): JsonResponse
    {
        $product = MartProduct::find($id);

        if (!$product) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        return response()->json(responseFormatter(DEFAULT_200, $product));
    }

    public function applyPromo(Request $request): JsonResponse
    {
        // Accept EITHER a full items array (subtotal recomputed server-side) OR a
        // client-provided subtotal for an in-cart preview. This is preview-only —
        // createOrder always recomputes the authoritative total from real items,
        // so trusting the preview subtotal here carries no pricing risk.
        $validator = Validator::make($request->all(), [
            'code'               => 'required|string|max:50',
            'items'              => 'required_without:subtotal|array|min:1',
            'items.*.product_id' => 'required_with:items|string',
            'items.*.quantity'   => 'required_with:items|integer|min:1|max:100',
            'subtotal'           => 'required_without:items|numeric|min:0|max:9999999.99',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 422);
        }

        if ($request->filled('items')) {
            // Compute subtotal server-side — never trust client-sent prices.
            $subtotal = 0;
            foreach ($request->items as $item) {
                $product = MartProduct::where('id', $item['product_id'])->where('is_active', true)->first();
                if (!$product) {
                    return response()->json(responseFormatter(constant: DEFAULT_404, errors: [['message' => 'One or more products not found']]), 404);
                }
                $subtotal += $product->price * (int) $item['quantity'];
            }
        } else {
            $subtotal = (float) $request->subtotal;
        }

        $promo = MartPromoCode::where('code', strtoupper(trim($request->code)))->first();

        if (!$promo || !$promo->isValid()) {
            return response()->json(responseFormatter(constant: DEFAULT_404, errors: [['message' => 'Invalid or expired promo code']]), 404);
        }

        $discount = $promo->computeDiscount((float) $subtotal);

        if ($discount <= 0) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['message' => 'Order does not meet the minimum amount for this promo']]), 400);
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'code'           => $promo->code,
            'discount'       => $discount,
            'discount_type'  => $promo->discount_type,
            'discount_value' => $promo->discount_value,
        ]));
    }

    public function createOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'delivery_address' => 'required|string|max:500',
            'delivery_lat' => 'nullable|numeric|between:-90,90',
            'delivery_lng' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:1000',
            'tip_amount' => 'nullable|numeric|min:0|max:9999.99',
            'promo_code' => 'nullable|string|max:50',
            'payment_method' => 'nullable|in:cash,card,wallet',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 422);
        }

        // Reject the "null island" (0,0) coordinate — almost always a client bug
        // (uninitialised location) and would break delivery routing.
        if ($request->filled('delivery_lat') && $request->filled('delivery_lng')
            && (float) $request->delivery_lat === 0.0 && (float) $request->delivery_lng === 0.0) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: [['message' => 'Invalid delivery location.']]
            ), 422);
        }

        try {
            $order = DB::transaction(function () use ($request) {
                $subtotal = 0;
                $orderItems = [];

                // Merge duplicate product_id entries to prevent double stock-decrement
                $merged = [];
                foreach ($request->items as $item) {
                    $pid = $item['product_id'];
                    if (isset($merged[$pid])) {
                        $merged[$pid]['quantity'] += (int) $item['quantity'];
                    } else {
                        $merged[$pid] = ['product_id' => $pid, 'quantity' => (int) $item['quantity']];
                    }
                }
                $items = array_values($merged);

                foreach ($items as $item) {
                    if ($item['quantity'] > 100) {
                        throw new \RuntimeException('Quantity exceeds maximum (100) per product.');
                    }

                    $product = MartProduct::where('id', $item['product_id'])
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$product || $product->stock < $item['quantity']) {
                        throw new \RuntimeException('One or more products are unavailable or out of stock.');
                    }

                    $product->decrement('stock', $item['quantity']);
                    $itemTotal = $product->price * $item['quantity'];
                    $subtotal += $itemTotal;

                    $orderItems[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price,
                        'total_price' => $itemTotal,
                    ];
                }

                // Cap tip to 30 % of subtotal to prevent price manipulation.
                $maxTip = $subtotal * 0.30;
                $tipAmount = min((float) ($request->tip_amount ?? 0), $maxTip);

                $discountAmount = 0.0;
                $appliedPromoCode = null;

                if ($request->promo_code) {
                    // Re-fetch with lockForUpdate inside the outer transaction so the
                    // isValid() check + increment is fully atomic under a row lock.
                    $promo = MartPromoCode::where('code', strtoupper(trim($request->promo_code)))
                        ->lockForUpdate()
                        ->first();

                    if ($promo && $promo->isValid()) {
                        // Per-user limit check: default 1 use per user per code.
                        $perUserLimit = $promo->per_user_limit ?? 1;
                        $userUsageCount = MartOrder::where('customer_id', $request->user()->id)
                            ->where('promo_code', $promo->code)
                            ->whereNotIn('status', ['cancelled'])
                            ->lockForUpdate()
                            ->count();

                        if ($userUsageCount < $perUserLimit) {
                            $discountAmount = $promo->computeDiscount($subtotal);
                            $appliedPromoCode = $promo->code;
                            $promo->increment('used_count');
                        }
                    }
                }

                $totalAmount = max(0, $subtotal - $discountAmount + $tipAmount);

                $order = MartOrder::create([
                    'ref_id' => 'VM-' . strtoupper(Str::random(8)),
                    'customer_id' => $request->user()->id,
                    'status' => 'pending',
                    'total_amount' => $totalAmount,
                    'tip_amount' => $tipAmount,
                    'discount_amount' => $discountAmount,
                    'promo_code' => $appliedPromoCode,
                    'payment_status' => 'unpaid',
                    'payment_method' => $request->input('payment_method', 'cash'),
                    'delivery_address' => $request->delivery_address,
                    'delivery_lat' => $request->delivery_lat,
                    'delivery_lng' => $request->delivery_lng,
                    'notes' => $request->notes,
                ]);

                foreach ($orderItems as $item) {
                    MartOrderItem::create(array_merge($item, ['order_id' => $order->id]));
                }

                return $order->load('items.product');
            });

            // Notify available drivers of new mart order
            $this->notifyDriversNewOrder($order);

        } catch (\RuntimeException $e) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['message' => $e->getMessage()]]), 400);
        }

        return response()->json(responseFormatter(DEFAULT_200, $order));
    }

    public function orderList(Request $request): JsonResponse
    {
        $orders = MartOrder::where('customer_id', $request->user()->id)
            ->with('items.product')
            ->orderByDesc('created_at')
            ->paginate(min($request->input('limit', 20), 100));

        return response()->json(responseFormatter(DEFAULT_200, $orders));
    }

    public function orderDetails(Request $request, string $id): JsonResponse
    {
        $order = MartOrder::where('id', $id)
            ->where('customer_id', $request->user()->id)
            ->with(['items.product', 'driver'])
            ->first();

        if (!$order) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        return response()->json(responseFormatter(DEFAULT_200, $order));
    }

    public function cancelOrder(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 422);
        }

        $result = DB::transaction(function () use ($request, $id) {
            $order = MartOrder::where('id', $id)
                ->where('customer_id', $request->user()->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return null;
            }

            $previousDriverId = $order->driver_id;
            $wasPaid = $order->payment_status === 'paid';

            foreach ($order->items as $item) {
                $lockedProduct = $item->product()->withTrashed()->lockForUpdate()->first();
                if ($lockedProduct) {
                    $lockedProduct->increment('stock', $item->quantity);
                }
            }

            if ($order->promo_code) {
                $promo = MartPromoCode::where('code', strtoupper($order->promo_code))
                    ->lockForUpdate()
                    ->first();
                if ($promo && $promo->used_count > 0) {
                    $promo->decrement('used_count');
                }
            }

            $order->update([
                'status' => 'cancelled',
                'driver_id' => null,
                'cancellation_reason' => $request->input('reason'),
                'cancelled_by' => 'customer',
                'cancelled_at' => now(),
            ]);
            return ['order' => $order, 'driver_id' => $previousDriverId, 'was_paid' => $wasPaid];
        });

        if (!$result) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        // Issue a refund for already-paid orders. The Stripe call happens after the
        // transaction commits so we never hold DB locks during an external request.
        // Failures never block cancellation — the order is flagged 'refund_pending'
        // for an operator/cron to retry.
        if (!empty($result['was_paid'])) {
            $newPaymentStatus = $this->refundOrderPayment($result['order']);
            try {
                $result['order']->update(['payment_status' => $newPaymentStatus]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Mart refund status update failed: ' . $e->getMessage());
            }
        }

        try {
            broadcast(new \App\Events\MartOrderStatusUpdatedEvent(
                $result['order']->id,
                'cancelled',
                $result['order']->customer_id,
            ))->toOthers();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Mart status broadcast failed: '.$e->getMessage());
        }

        // Notify driver if the order was already accepted before cancellation
        if ($result['driver_id']) {
            try {
                $driver = User::find($result['driver_id']);
                if ($driver && $driver->fcm_token) {
                    sendDeviceNotification(
                        fcm_token: $driver->fcm_token,
                        title: 'Order Cancelled',
                        description: "Mart order #{$result['order']->ref_id} was cancelled by the customer.",
                        status: 'cancelled',
                        type: 'mart',
                        notification_type: 'mart',
                        action: 'mart_order_cancelled',
                        user_id: $driver->id,
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Mart driver cancel notify failed: ' . $e->getMessage());
            }
        }

        return response()->json(responseFormatter(DEFAULT_200));
    }

    public function reviewOrder(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 422);
        }

        $order = MartOrder::where('id', $id)
            ->where('customer_id', $request->user()->id)
            ->where('status', 'delivered')
            ->whereNotNull('driver_id')
            ->first();

        if (!$order) {
            return response()->json(responseFormatter(
                constant: DEFAULT_404,
                errors: [['message' => 'Order not found or not eligible for review.']]
            ), 404);
        }

        if (MartReview::where('order_id', $order->id)->exists()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: [['message' => 'This order has already been reviewed.']]
            ), 400);
        }

        $review = MartReview::create([
            'order_id'    => $order->id,
            'customer_id' => $order->customer_id,
            'driver_id'   => $order->driver_id,
            'rating'      => (int) $request->rating,
            'comment'     => $request->comment,
        ]);

        return response()->json(responseFormatter(DEFAULT_200, $review));
    }

    /**
     * Attempt to refund a paid mart order through Stripe.
     * Returns the new payment_status: 'refunded' on success, 'refund_pending'
     * when Stripe is unconfigured/unreachable or no payment record is found.
     * Never throws — cancellation must always succeed.
     */
    private function refundOrderPayment(MartOrder $order): string
    {
        try {
            $stripeConfig = DB::table('settings')
                ->where('key_name', 'stripe')
                ->where('settings_type', PAYMENT_CONFIG)
                ->first();
            if (!$stripeConfig) {
                return 'refund_pending';
            }
            $stripeValues = $stripeConfig->mode === 'live'
                ? json_decode($stripeConfig->live_values, true)
                : json_decode($stripeConfig->test_values, true);
            $stripeSecret = $stripeValues['api_key'] ?? null;
            if (!$stripeSecret) {
                return 'refund_pending';
            }

            // Locate the succeeded PaymentIntent for this order.
            $event = StripeEvent::where('payment_intent_id', '!=', '')
                ->where('status', 'succeeded')
                ->whereJsonContains('metadata->order_id', $order->id)
                ->latest()
                ->first();

            if (!$event || !$event->payment_intent_id) {
                return 'refund_pending';
            }

            \Stripe\Stripe::setApiKey($stripeSecret);
            \Stripe\Refund::create(
                ['payment_intent' => $event->payment_intent_id],
                ['idempotency_key' => 'refund_order_' . $order->id]
            );

            StripeEvent::create([
                'stripe_event_id'   => 'refund_' . $order->id,
                'type'              => 'charge.refunded',
                'user_id'           => $order->customer_id,
                'amount'            => $order->total_amount,
                'currency'          => 'usd',
                'status'            => 'succeeded',
                'payment_intent_id' => $event->payment_intent_id,
                'metadata'          => ['type' => 'order_refund', 'order_id' => $order->id],
            ]);

            return 'refunded';
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Mart order refund failed: ' . $e->getMessage());
            return 'refund_pending';
        }
    }

    private function notifyDriversNewOrder(MartOrder $order): void
    {
        try {
            $drivers = User::whereHas('driverDetails')
                ->where('is_active', true)
                ->whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->limit(50)
                ->pluck('fcm_token')
                ->filter()
                ->values();

            foreach ($drivers as $token) {
                sendDeviceNotification(
                    fcm_token: $token,
                    title: 'New Mart Order',
                    description: 'A new mart delivery order is available near you.',
                    status: 'pending',
                    type: 'mart',
                    notification_type: 'mart',
                    action: 'new_mart_order',
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Mart FCM notify failed: ' . $e->getMessage());
        }
    }
}
