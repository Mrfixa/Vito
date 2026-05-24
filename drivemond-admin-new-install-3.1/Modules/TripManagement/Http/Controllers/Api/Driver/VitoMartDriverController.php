<?php

namespace Modules\TripManagement\Http\Controllers\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Entities\MartOrder;
use Modules\TripManagement\Entities\MartOrderItem;
use Modules\TripManagement\Entities\MartPromoCode;

class VitoMartDriverController extends Controller
{
    public function pendingOrders(Request $request): JsonResponse
    {
        $orders = MartOrder::where('status', 'pending')
            ->with('items.product', 'customer')
            ->orderByDesc('created_at')
            ->paginate(min($request->input('limit', 20), 100));

        return response()->json(responseFormatter(DEFAULT_200, $orders));
    }

    public function acceptOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 422);
        }

        $order = DB::transaction(function () use ($request) {
            $order = MartOrder::where('id', $request->order_id)
                ->where('status', 'pending')
                ->whereNull('driver_id')
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
            return response()->json(responseFormatter(TRIP_REQUEST_404), 404);
        }

        // Notify customer their order was accepted
        $this->notifyCustomer(
            $order,
            title: 'Order Accepted',
            description: "Your mart order #{$order->ref_id} has been accepted by a driver.",
            action: 'mart_order_accepted',
        );

        return response()->json(responseFormatter(DEFAULT_200, $order));
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
            'status' => 'required|in:picked_up,delivered,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 422);
        }

        // Drivers may cancel an order only while it is still in 'accepted' state.
        $allowedTransitions = [
            'picked_up'  => ['accepted'],
            'delivered'  => ['picked_up'],
            'cancelled'  => ['accepted'],
        ];

        $order = DB::transaction(function () use ($request, $allowedTransitions) {
            $order = MartOrder::where('id', $request->order_id)
                ->where('driver_id', $request->user()->id)
                ->whereIn('status', $allowedTransitions[$request->status])
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return null;
            }

            $updateData = ['status' => $request->status];

            if ($request->status === 'delivered') {
                $updateData['payment_status'] = 'paid';
            }

            if ($request->status === 'cancelled') {
                // Restore product stock atomically under the same transaction lock.
                foreach ($order->items as $item) {
                    if ($item->product) {
                        $item->product()->lockForUpdate()->first();
                        $item->product->increment('stock', $item->quantity);
                    }
                }
                // If a promo code was used, decrement its global used_count.
                if ($order->promo_code) {
                    $promo = MartPromoCode::where('code', $order->promo_code)
                        ->lockForUpdate()
                        ->first();
                    if ($promo && $promo->used_count > 0) {
                        $promo->decrement('used_count');
                    }
                }
                // Release driver so they can accept another order.
                $updateData['driver_id'] = null;
            }

            $order->update($updateData);

            return $order->fresh();
        });

        if (!$order) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $messages = [
            'picked_up' => ['title' => 'Order Picked Up', 'description' => "Your mart order #{$order->ref_id} has been picked up and is on the way.", 'action' => 'mart_order_picked_up'],
            'delivered' => ['title' => 'Order Delivered', 'description' => "Your mart order #{$order->ref_id} has been delivered. Enjoy!", 'action' => 'mart_order_delivered'],
            'cancelled' => ['title' => 'Order Cancelled', 'description' => "Your mart order #{$order->ref_id} was cancelled by the driver. We are finding another driver.", 'action' => 'mart_order_cancelled'],
        ];

        $this->notifyCustomer(
            $order,
            title: $messages[$request->status]['title'],
            description: $messages[$request->status]['description'],
            action: $messages[$request->status]['action'],
        );

        return response()->json(responseFormatter(DEFAULT_200, $order));
    }

    public function uploadDeliveryProof(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
            // Accept image file upload for delivery photo (field can be delivery_photo or proof_photo)
            'delivery_photo' => 'nullable|image|max:2048',
            'proof_photo' => 'nullable|image|max:2048',
            // Accept base64 string for canvas-drawn signature
            'signature_base64' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 422);
        }

        $order = MartOrder::where('id', $request->order_id)
            ->where('driver_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $updateData = [];

        // Accept either field name for the delivery photo
        $photoFile = $request->file('delivery_photo') ?? $request->file('proof_photo');
        if ($photoFile) {
            $updateData['delivery_photo'] = $photoFile->store('mart/photos', 'public');
        }

        // Signature comes as base64 from the canvas painter
        if ($request->filled('signature_base64')) {
            $base64 = $request->input('signature_base64');
            // Strip data-URI prefix if present (data:image/png;base64,...)
            if (str_contains($base64, ',')) {
                $base64 = substr($base64, strpos($base64, ',') + 1);
            }
            $decoded = base64_decode($base64, strict: true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $filename = 'mart/signatures/' . $order->id . '_' . time() . '.png';
                Storage::disk('public')->put($filename, $decoded);
                $updateData['signature_image'] = $filename;
            }
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
            ->paginate(min($request->input('limit', 20), 100));

        return response()->json(responseFormatter(DEFAULT_200, $orders));
    }

    public function orderDetails(Request $request, string $id): JsonResponse
    {
        $order = MartOrder::where('id', $id)
            ->where('driver_id', $request->user()->id)
            ->with(['items.product', 'customer'])
            ->first();

        if (!$order) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        return response()->json(responseFormatter(DEFAULT_200, $order));
    }

    private function notifyCustomer(MartOrder $order, string $title, string $description, string $action): void
    {
        try {
            $customer = $order->customer;
            if ($customer && $customer->fcm_token) {
                sendDeviceNotification(
                    fcm_token: $customer->fcm_token,
                    title: $title,
                    description: $description,
                    status: $order->status,
                    ride_request_id: $order->id,
                    type: 'mart',
                    notification_type: 'mart',
                    action: $action,
                    user_id: $customer->id,
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Mart customer notify failed: ' . $e->getMessage());
        }
    }
}
