<?php

namespace Modules\TripManagement\Http\Controllers\Web;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\TripManagement\Entities\MartOrder;
use Modules\TripManagement\Http\Controllers\Concerns\LogsVitoAudit;

class MartOrderAdminController extends Controller
{
    use AuthorizesRequests, LogsVitoAudit;

    /**
     * Statuses that can appear as the order-list submenu tabs.
     */
    private const FILTERABLE = ['pending', 'accepted', 'picked_up', 'delivered', 'cancelled'];

    public function orderList(Request $request, string $type = 'all'): View
    {
        $this->authorize('vito_mart_view');

        if ($type !== 'all' && !in_array($type, self::FILTERABLE, true)) {
            $type = 'all';
        }

        $search = $request->get('search');
        $from = null;
        $to = null;
        if ($request->filled('data')) {
            $range = getDateRange($request->data);
            $from = $range['start'] ?? null;
            $to = $range['end'] ?? null;
        }

        $orders = MartOrder::with(['items.product', 'customer', 'driver'])
            ->when($type !== 'all', fn ($q) => $q->where('status', $type))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('ref_id', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($c) use ($search) {
                            $c->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($from && $to, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->orderByDesc('created_at')
            ->paginate(paginationLimit())
            ->appends($request->all());

        $orderCounts = $type === 'all'
            ? $this->statusCounts($from, $to)
            : null;

        if ($request->ajax()) {
            return view('tripmanagement::admin.mart.orders.partials._order-list-stat', compact('orderCounts', 'type'));
        }

        return view('tripmanagement::admin.mart.orders.index', compact('orders', 'type', 'orderCounts', 'search'));
    }

    public function show(string $id): View|RedirectResponse
    {
        $this->authorize('vito_mart_view');

        $order = MartOrder::with(['items.product', 'customer', 'driver'])->find($id);
        if (!$order) {
            Toastr::error(translate('order_not_found'));
            return back();
        }

        return view('tripmanagement::admin.mart.orders.details', compact('order'));
    }

    public function invoice(string $id): View|RedirectResponse
    {
        $this->authorize('vito_mart_view');

        $order = MartOrder::with(['items.product', 'customer', 'driver'])->find($id);
        if (!$order) {
            Toastr::error(translate('order_not_found'));
            return back();
        }

        return view('tripmanagement::admin.mart.orders.invoice', compact('order'));
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $this->authorize('vito_mart_status');

        $request->validate([
            'status' => 'required|in:' . implode(',', MartOrder::STATUSES),
            'reason' => 'nullable|string|max:255',
        ]);

        $order = MartOrder::find($id);
        if (!$order) {
            Toastr::error(translate('order_not_found'));
            return back();
        }

        $target = $request->status;
        $allowedFrom = MartOrder::STATUS_TRANSITIONS[$target] ?? [];

        if (!in_array($order->status, $allowedFrom, true)) {
            Toastr::error(translate('invalid_status_transition'));
            return back();
        }

        $previous = $order->status;

        DB::transaction(function () use ($order, $target, $request) {
            $data = ['status' => $target];

            if ($target === 'cancelled') {
                // Restore product stock for each item.
                foreach ($order->items as $item) {
                    $product = $item->product()->withTrashed()->lockForUpdate()->first();
                    if ($product) {
                        $product->increment('stock', $item->quantity);
                    }
                }
                // Decrement promo usage if one was applied.
                if ($order->promo_code) {
                    $promo = \Modules\TripManagement\Entities\MartPromoCode::where('code', $order->promo_code)
                        ->lockForUpdate()->first();
                    if ($promo && $promo->used_count > 0) {
                        $promo->decrement('used_count');
                    }
                }
                $data['cancellation_reason'] = $request->input('reason');
                $data['cancelled_by'] = 'admin';
                $data['cancelled_at'] = now();
            }

            $order->update($data);
        });

        $this->auditLog(auth()->id(), 'status_change', MartOrder::class, $order->id, [
            'from' => $previous,
            'to' => $target,
            'reason' => $request->input('reason'),
        ]);

        try {
            broadcast(new \App\Events\MartOrderStatusUpdatedEvent($order->id, $target, $order->customer_id))->toOthers();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Mart admin status broadcast failed: ' . $e->getMessage());
        }

        Toastr::success(translate('order_status_updated'));
        return back();
    }

    public function export(Request $request)
    {
        $this->authorize('vito_mart_export');

        $type = $request->get('type', 'all');
        $orders = MartOrder::with(['customer', 'driver'])
            ->when($type !== 'all' && in_array($type, self::FILTERABLE, true), fn ($q) => $q->where('status', $type))
            ->orderByDesc('created_at')
            ->get();

        $data = $orders->map(fn ($item) => [
            'id' => $item->id,
            'Order ID' => $item->ref_id,
            'Date' => date('d F Y h:i a', strtotime($item->created_at)),
            'Customer' => trim(($item->customer?->first_name ?? '') . ' ' . ($item->customer?->last_name ?? '')) ?: translate('not_available'),
            'Driver' => $item->driver ? trim(($item->driver->first_name ?? '') . ' ' . ($item->driver->last_name ?? '')) : translate('no_driver_assigned'),
            'Total' => getCurrencyFormat($item->total_amount ?? 0),
            'Tip' => getCurrencyFormat($item->tip_amount ?? 0),
            'Discount' => getCurrencyFormat($item->discount_amount ?? 0),
            'Promo' => $item->promo_code ?: '-',
            'Payment Method' => ucfirst($item->payment_method ?? '-'),
            'Payment Status' => ucfirst($item->payment_status ?? '-'),
            'Status' => str_replace('_', ' ', ucfirst($item->status)),
        ]);

        return exportData($data, $request['file'], 'tripmanagement::admin.mart.orders.print');
    }

    /**
     * Status-wise counts (+ a "all" bucket and delivered revenue) for the stats card.
     */
    private function statusCounts(?string $from, ?string $to): array
    {
        $base = MartOrder::when($from && $to, fn ($q) => $q->whereBetween('created_at', [$from, $to]));

        $counts = ['all' => (clone $base)->count()];
        foreach (self::FILTERABLE as $status) {
            $counts[$status] = (clone $base)->where('status', $status)->count();
        }
        $counts['revenue'] = (clone $base)->where('status', 'delivered')->sum('total_amount');

        return $counts;
    }
}
