@extends('adminmodule::layouts.master')

@section('title', translate('order_details'))

@php
    $statusColors = [
        'pending' => 'warning', 'accepted' => 'info', 'picked_up' => 'primary',
        'delivered' => 'success', 'cancelled' => 'danger',
    ];
    // Build the list of statuses this order may transition into right now.
    $availableTargets = [];
    foreach (\Modules\TripManagement\Entities\MartOrder::STATUS_TRANSITIONS as $target => $allowedFrom) {
        if (in_array($order->status, $allowedFrom, true)) {
            $availableTargets[] = $target;
        }
    }
    $subtotal = $order->items->sum(fn($i) => $i->total_price);
@endphp

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center mt-30 mb-3 gap-3">
                <h2 class="fs-22">{{translate('order')}} #{{$order->ref_id}}
                    <span class="badge bg-{{ $statusColors[$order->status] ?? 'secondary' }} ms-2">
                        {{ translate('order_status_'.$order->status) }}
                    </span>
                </h2>
                <div class="d-flex gap-2">
                    <a href="{{route('admin.mart.orders.invoice', $order->id)}}" target="_blank" class="btn btn-outline-primary">
                        <i class="bi bi-printer"></i> {{translate('invoice')}}
                    </a>
                    <a href="{{route('admin.mart.orders.index', 'all')}}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> {{translate('back')}}
                    </a>
                </div>
            </div>

            <div class="row g-4">
                {{-- Left column --}}
                <div class="col-lg-8">
                    {{-- Items --}}
                    <div class="card mb-4">
                        <div class="card-header"><h5 class="mb-0">{{translate('order_items')}}</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{translate('product')}}</th>
                                            <th class="text-center">{{translate('quantity')}}</th>
                                            <th class="text-end">{{translate('unit_price')}}</th>
                                            <th class="text-end">{{translate('total')}}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($order->items as $item)
                                            <tr>
                                                <td>{{ $item->product->name ?? translate('not_available') }}</td>
                                                <td class="text-center">{{ $item->quantity }}</td>
                                                <td class="text-end">{{ getCurrencyFormat($item->unit_price ?? 0) }}</td>
                                                <td class="text-end">{{ getCurrencyFormat($item->total_price ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr><td colspan="3" class="text-end">{{translate('subtotal')}}</td><td class="text-end">{{ getCurrencyFormat($subtotal) }}</td></tr>
                                        <tr><td colspan="3" class="text-end">{{translate('discount')}}</td><td class="text-end text-danger">- {{ getCurrencyFormat($order->discount_amount ?? 0) }}</td></tr>
                                        <tr><td colspan="3" class="text-end">{{translate('tip')}}</td><td class="text-end">{{ getCurrencyFormat($order->tip_amount ?? 0) }}</td></tr>
                                        <tr class="fw-bold"><td colspan="3" class="text-end">{{translate('total')}}</td><td class="text-end">{{ getCurrencyFormat($order->total_amount ?? 0) }}</td></tr>
                                    </tfoot>
                                </table>
                            </div>
                            @if($order->promo_code)
                                <p class="mb-0"><span class="text-muted">{{translate('promo_code')}}:</span> <span class="badge bg-light text-dark">{{$order->promo_code}}</span></p>
                            @endif
                        </div>
                    </div>

                    {{-- Customer & Driver --}}
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header"><h6 class="mb-0">{{translate('customer')}}</h6></div>
                                <div class="card-body">
                                    <p class="mb-1 fw-medium">{{ trim(($order->customer->first_name ?? '').' '.($order->customer->last_name ?? '')) ?: translate('not_available') }}</p>
                                    @if($order->customer?->phone)<p class="mb-1 text-muted"><i class="bi bi-telephone"></i> {{$order->customer->phone}}</p>@endif
                                    <hr>
                                    <p class="mb-1"><strong>{{translate('delivery_address')}}</strong></p>
                                    <p class="mb-1 text-muted">{{ $order->delivery_address ?: translate('not_available') }}</p>
                                    @if($order->notes)<p class="mb-0"><span class="text-muted">{{translate('notes')}}:</span> {{$order->notes}}</p>@endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header"><h6 class="mb-0">{{translate('driver')}}</h6></div>
                                <div class="card-body">
                                    @if($order->driver)
                                        <p class="mb-1 fw-medium">{{ trim(($order->driver->first_name ?? '').' '.($order->driver->last_name ?? '')) }}</p>
                                        @if($order->driver->phone)<p class="mb-0 text-muted"><i class="bi bi-telephone"></i> {{$order->driver->phone}}</p>@endif
                                    @else
                                        <p class="text-muted mb-0">{{translate('no_driver_assigned')}}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Delivery proof --}}
                    @if($order->delivery_photo || $order->signature_image)
                        <div class="card mt-4">
                            <div class="card-header"><h6 class="mb-0">{{translate('delivery_proof')}}</h6></div>
                            <div class="card-body d-flex flex-wrap gap-4">
                                @if($order->delivery_photo)
                                    <div>
                                        <small class="text-muted d-block mb-1">{{translate('delivery_photo')}}</small>
                                        <a href="{{ asset('storage/'.$order->delivery_photo) }}" target="_blank">
                                            <img src="{{ asset('storage/'.$order->delivery_photo) }}" style="max-width:180px;border-radius:8px;border:1px solid #dee2e6">
                                        </a>
                                    </div>
                                @endif
                                @if($order->signature_image)
                                    <div>
                                        <small class="text-muted d-block mb-1">{{translate('customer_signature')}}</small>
                                        <a href="{{ asset('storage/'.$order->signature_image) }}" target="_blank">
                                            <img src="{{ asset('storage/'.$order->signature_image) }}" style="max-width:180px;border-radius:8px;border:1px solid #dee2e6;background:#fff">
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Right column --}}
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0">{{translate('payment')}}</h6></div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{translate('payment_method')}}</span><span class="text-capitalize">{{ $order->payment_method ?: '-' }}</span></div>
                            <div class="d-flex justify-content-between"><span class="text-muted">{{translate('payment_status')}}</span>
                                <span class="badge bg-{{ $order->payment_status === 'paid' ? 'success' : 'secondary' }}">{{ translate($order->payment_status ?? 'unpaid') }}</span>
                            </div>
                        </div>
                    </div>

                    @if($order->status === 'cancelled' && $order->cancellation_reason)
                        <div class="alert alert-danger">
                            <strong>{{translate('cancellation_reason')}}:</strong> {{$order->cancellation_reason}}
                            @if($order->cancelled_by)<br><small>{{translate('cancelled_by')}}: {{ucfirst($order->cancelled_by)}}</small>@endif
                        </div>
                    @endif

                    @can('vito_mart_status')
                        @if(count($availableTargets))
                            <div class="card">
                                <div class="card-header"><h6 class="mb-0">{{translate('update_status')}}</h6></div>
                                <div class="card-body">
                                    <form action="{{route('admin.mart.orders.status', $order->id)}}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="mb-3">
                                            <label class="form-label">{{translate('new_status')}}</label>
                                            <select name="status" class="form-control" id="mart-status-select" required>
                                                @foreach($availableTargets as $target)
                                                    <option value="{{$target}}">{{ translate('order_status_'.$target) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3" id="mart-reason-wrap" style="display:none">
                                            <label class="form-label">{{translate('cancellation_reason')}}</label>
                                            <textarea name="reason" class="form-control" rows="2" maxlength="255"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100" onsubmit="return confirm('{{translate('are_you_sure')}}')">
                                            {{translate('update_status')}}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
<script>
    (function(){
        var sel = document.getElementById('mart-status-select');
        var wrap = document.getElementById('mart-reason-wrap');
        if(sel && wrap){
            var toggle = function(){ wrap.style.display = (sel.value === 'cancelled') ? 'block' : 'none'; };
            sel.addEventListener('change', toggle);
            toggle();
        }
    })();
</script>
@endpush
