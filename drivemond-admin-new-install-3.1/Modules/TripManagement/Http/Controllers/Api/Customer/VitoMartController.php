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
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 422);
        }

        $promo = MartPromoCode::where('code', strtoupper(trim($request->code)))->first();

        if (!$promo || !$promo->isValid()) {
            return response()->json(responseFormatter(constant: DEFAULT_404, errors: [['message' => 'Invalid or expired promo code']]), 404);
        }

        $discount = $promo->computeDiscount((float) $request->subtotal);

        if ($discount <= 0) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['message' => 'Order does not meet the minimum amount for this promo']]), 400);
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'code' => $promo->code,
            'discount' => $discount,
            'discount_type' => $promo->discount_type,
            'discount_value' => $promo->discount_value,
        ]));
    }

    public function createOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'delivery_address' => 'required|string',
            'delivery_lat' => 'nullable|numeric|between:-90,90',
            'delivery_lng' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string',
            'tip_amount' => 'nullable|numeric|min:0|max:9999.99',
            'promo_code' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 422);
        }

        try {
            $order = DB::transaction(function () use ($request) {
                $subtotal = 0;
                $orderItems = [];

                foreach ($request->items as $item) {
                    $product = MartProduct::where('id', $item['product_id'])
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$product || $product->stock < $item['quantity']) {
                        throw new \RuntimeException('Product unavailable or insufficient stock: ' . ($item['product_id'] ?? ''));
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
        $order = DB::transaction(function () use ($request, $id) {
            $order = MartOrder::where('id', $id)
                ->where('customer_id', $request->user()->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return null;
            }

            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            if ($order->promo_code) {
                $promo = MartPromoCode::where('code', $order->promo_code)
                    ->lockForUpdate()
                    ->first();
                if ($promo && $promo->used_count > 0) {
                    $promo->decrement('used_count');
                }
            }

            $order->update(['status' => 'cancelled']);
            return $order;
        });

        if (!$order) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        return response()->json(responseFormatter(DEFAULT_200));
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
