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

class VitoMartController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $products = MartProduct::where('is_active', true)
            ->when($request->category, fn($q, $cat) => $q->where('category', $cat))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->paginate($request->input('limit', 20));

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

    public function createOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'delivery_address' => 'required|string',
            'delivery_lat' => 'required|numeric',
            'delivery_lng' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        try {
            $order = DB::transaction(function () use ($request) {
                $totalAmount = 0;
                $orderItems = [];

                foreach ($request->items as $item) {
                    $product = MartProduct::where('id', $item['product_id'])
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$product || $product->stock < $item['quantity']) {
                        throw new \RuntimeException('Product unavailable or insufficient stock');
                    }

                    $product->decrement('stock', $item['quantity']);
                    $itemTotal = $product->price * $item['quantity'];
                    $totalAmount += $itemTotal;

                    $orderItems[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price,
                        'total_price' => $itemTotal,
                    ];
                }

                $order = MartOrder::create([
                    'ref_id' => 'VM-' . strtoupper(Str::random(8)),
                    'customer_id' => $request->user()->id,
                    'status' => 'pending',
                    'total_amount' => $totalAmount,
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
        } catch (\RuntimeException $e) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 403);
        }

        return response()->json(responseFormatter(DEFAULT_200, $order));
    }

    public function orderList(Request $request): JsonResponse
    {
        $orders = MartOrder::where('customer_id', $request->user()->id)
            ->with('items.product')
            ->orderByDesc('created_at')
            ->paginate($request->input('limit', 20));

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

            $order->update(['status' => 'cancelled']);
            return $order;
        });

        if (!$order) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        return response()->json(responseFormatter(DEFAULT_200));
    }
}
