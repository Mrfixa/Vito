<?php

namespace Modules\TripManagement\Http\Controllers\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Entities\MartOrder;

class VitoMartDriverController extends Controller
{
    public function pendingOrders(Request $request): JsonResponse
    {
        $orders = MartOrder::where('status', 'pending')
            ->with('items.product', 'customer')
            ->orderByDesc('created_at')
            ->paginate($request->input('limit', 20));

        return response()->json(responseFormatter(DEFAULT_200, $orders));
    }

    public function acceptOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $order = DB::transaction(function () use ($request) {
            $order = MartOrder::where('id', $request->order_id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return null;
            }

            $order->update([
                'driver_id' => $request->user()->id,
                'status' => 'accepted',
            ]);

            return $order->load('items.product', 'customer');
        });

        if (!$order) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }

        return response()->json(responseFormatter(DEFAULT_200, $order));
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
            'status' => 'required|in:picked_up,delivered',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $allowedTransitions = [
            'picked_up' => ['accepted'],
            'delivered' => ['picked_up'],
        ];

        $order = MartOrder::where('id', $request->order_id)
            ->where('driver_id', $request->user()->id)
            ->whereIn('status', $allowedTransitions[$request->status])
            ->first();

        if (!$order) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $updateData = ['status' => $request->status];

        if ($request->status === 'delivered') {
            $updateData['payment_status'] = 'paid';
        }

        $order->update($updateData);

        return response()->json(responseFormatter(DEFAULT_200, $order));
    }

    public function uploadDeliveryProof(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
            'signature_image' => 'nullable|image|max:2048',
            'delivery_photo' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $order = MartOrder::where('id', $request->order_id)
            ->where('driver_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $updateData = [];

        if ($request->hasFile('signature_image')) {
            $updateData['signature_image'] = $request->file('signature_image')->store('mart/signatures', 'public');
        }

        if ($request->hasFile('delivery_photo')) {
            $updateData['delivery_photo'] = $request->file('delivery_photo')->store('mart/photos', 'public');
        }

        if (!empty($updateData)) {
            $order->update($updateData);
        }

        return response()->json(responseFormatter(DEFAULT_200, $order));
    }

    public function myOrders(Request $request): JsonResponse
    {
        $orders = MartOrder::where('driver_id', $request->user()->id)
            ->with('items.product', 'customer')
            ->orderByDesc('created_at')
            ->paginate($request->input('limit', 20));

        return response()->json(responseFormatter(DEFAULT_200, $orders));
    }
}
