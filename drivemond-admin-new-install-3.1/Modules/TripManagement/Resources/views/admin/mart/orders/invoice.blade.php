@php($businessName = businessConfig('business_name', 'business_information')?->value)
@php($businessContactEmail = businessConfig('business_contact_email', 'business_information')?->value)
@php($businessContactPhone = businessConfig('business_contact_phone', 'business_information')?->value)
@php($subtotal = $order->items->sum(fn($i) => $i->total_price))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $businessName }} {{ translate('invoice') }} #{{ $order->ref_id }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #2b2b2b; padding: 24px; }
        .inv-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #eee; padding-bottom: 16px; margin-bottom: 20px; }
        .inv-head h1 { margin: 0; font-size: 22px; }
        .muted { color: #777; font-size: 13px; }
        .meta { margin: 16px 0; font-size: 14px; }
        .meta div { margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background: #f7f7f7; text-transform: uppercase; font-size: 12px; letter-spacing: .03em; }
        td.num, th.num { text-align: right; }
        tfoot td { border: none; }
        .totals td { font-size: 14px; }
        .totals .grand { font-weight: bold; font-size: 16px; border-top: 2px solid #eee; }
        .badge { padding: 2px 10px; border-radius: 12px; background: #eef; font-size: 12px; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
<div id="invoice">
    <div class="inv-head">
        <div>
            <h1>{{ $businessName ?? 'VitoMart' }}</h1>
            <div class="muted">
                @if($businessContactEmail){{ $businessContactEmail }}<br>@endif
                @if($businessContactPhone){{ $businessContactPhone }}@endif
            </div>
        </div>
        <div style="text-align:right">
            <h1>{{ translate('invoice') }}</h1>
            <div class="muted">#{{ $order->ref_id }}</div>
            <div class="muted">{{ date('d M Y, h:i a', strtotime($order->created_at)) }}</div>
            <div><span class="badge">{{ translate('order_status_'.$order->status) }}</span></div>
        </div>
    </div>

    <div class="meta">
        <div><strong>{{ translate('customer') }}:</strong>
            {{ trim(($order->customer->first_name ?? '').' '.($order->customer->last_name ?? '')) ?: translate('not_available') }}
            @if($order->customer?->phone) — {{ $order->customer->phone }} @endif
        </div>
        <div><strong>{{ translate('delivery_address') }}:</strong> {{ $order->delivery_address ?: translate('not_available') }}</div>
        @if($order->driver)
            <div><strong>{{ translate('driver') }}:</strong> {{ trim(($order->driver->first_name ?? '').' '.($order->driver->last_name ?? '')) }}</div>
        @endif
        <div><strong>{{ translate('payment') }}:</strong> {{ ucfirst($order->payment_method ?? '-') }} ({{ translate($order->payment_status ?? 'unpaid') }})</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ translate('product') }}</th>
                <th class="num">{{ translate('quantity') }}</th>
                <th class="num">{{ translate('unit_price') }}</th>
                <th class="num">{{ translate('total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product->name ?? translate('not_available') }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ getCurrencyFormat($item->unit_price ?? 0) }}</td>
                    <td class="num">{{ getCurrencyFormat($item->total_price ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="totals">
            <tr><td colspan="3" class="num">{{ translate('subtotal') }}</td><td class="num">{{ getCurrencyFormat($subtotal) }}</td></tr>
            <tr><td colspan="3" class="num">{{ translate('discount') }}</td><td class="num">- {{ getCurrencyFormat($order->discount_amount ?? 0) }}</td></tr>
            <tr><td colspan="3" class="num">{{ translate('tip') }}</td><td class="num">{{ getCurrencyFormat($order->tip_amount ?? 0) }}</td></tr>
            <tr class="grand"><td colspan="3" class="num">{{ translate('total') }}</td><td class="num">{{ getCurrencyFormat($order->total_amount ?? 0) }}</td></tr>
        </tfoot>
    </table>

    <p class="muted" style="margin-top:24px">{{ translate('note:_this_is_software_generated_copy') }}</p>

    <div class="no-print" style="margin-top:20px">
        <button onclick="window.print()">{{ translate('print') }}</button>
    </div>
</div>
</body>
</html>
