@push('css_or_js')
    <style>
        #map-layer {
            max-width: 706px;
            min-height: 230px;
        }
    </style>

@endpush
<div class="col-lg-4">
    @if(count($safetyAlerts) > 0)
        <div class="max-h-340px overflow-auto mb-3">
            @foreach($safetyAlerts as $safetyAlert)
                @php
                    $userType = match (true) {
                        $safetyAlert?->sentBy?->user_type == 'driver' && ($safetyAlert?->trip?->current_status == 'ongoing' || $safetyAlert?->trip?->current_status == 'completed') => 'driver-on-trip',
                        $safetyAlert?->sentBy?->user_type == 'driver' => 'driver-idle',
                        default => 'all-customer',
                    };
                    $route = route('admin.fleet-map', ['type' => $userType]) . '?zone_id=' . $safetyAlert?->trip?->zone_id;
                @endphp
                <div class="card {{ $loop->last ? '' : 'mb-3' }}">
                    <div class="card-body">
                        <h5 class="text-center mb-3 text-capitalize">{{translate('Safety_Alert')}} <span
                                class="fw-medium">({{ $safetyAlert?->number_of_alert }})</span></h5>
                        <hr>
                        <div class="d-flex gap-3 justify-content-between flex-wrap fs-12 mb-3">
                            <div class="d-flex flex-column gap-10px">
                                <h6 class="fs-12">{{ translate('Sent By') }} : <span
                                        class="fw-normal">{{ $safetyAlert->sentBy?->full_name ?? $safetyAlert->sentBy?->first_name . $safetyAlert->sentBy?->last_name }}</span>
                                </h6>
                                @if($safetyAlert?->resolved_by)
                                    <h6 class="fs-12">{{ translate('Resolved By') }}: <span class="fw-normal">
                                   {{ $safetyAlert?->solvedBy?->user_type == 'admin-employee' ? 'Employee' : $safetyAlert?->solvedBy?->user_type }}
                                            {{ $safetyAlert?->solvedBy?->user_type == 'admin-employee' && $safetyAlert?->solvedBy?->id ? '(' . $safetyAlert?->solvedBy?->first_name. ' ' . $safetyAlert?->solvedBy?->last_name . ')': ' ' }}
                                </span></h6>
                                @endif
                            </div>
                            <span>{{date('d F Y', strtotime($safetyAlert->created_at))}}, {{date('h:i a', strtotime($safetyAlert->created_at))}}</span>
                        </div>
                        @if($safetyAlert?->reason || $safetyAlert?->comment)
                            <div class="bg-danger-light rounded  mb-3 px-2 py-3">
                                <ol class="d-flex flex-column gap-2 mb-0">
                                    @if($safetyAlert?->reason)
                                        @foreach($safetyAlert?->reason as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    @endif
                                    @if($safetyAlert?->comment)
                                        <li>{{  $safetyAlert?->comment }}</li>
                                    @endif
                                </ol>
                            </div>
                        @endif
                        <div class="mb-3">
                            <h6 class="fs-12">{{ translate('Alert Location') }}</h6>
                            <p class="fs-12">{{ $safetyAlert?->alert_location }}</p>
                        </div>
                        @if($safetyAlert?->resolved_location)
                            <div class="{{ $safetyAlert?->status == PENDING ? 'mb-3' : '' }}">
                                <h6 class="fs-12">{{ translate('Resolved Location') }}</h6>
                                <p class="fs-12">{{ $safetyAlert?->resolved_location }}</p>
                            </div>
                        @endif
                        @if($safetyAlert?->status == PENDING)
                            <div class="d-flex gap-2 justify-content-between flex-wrap">
                                <a href="{{ $route }}"
                                   class="btn btn-secondary flex-grow-1 w-100px justify-content-center fw-semibold show-safety-alert-user-details"
                                   data-user-id="{{ $safetyAlert?->sentBy?->id }}">
                                    {{ translate('Fleet View') }}
                                </a>
                                <form action="{{ route('admin.safety-alert.mark-as-solved', $safetyAlert->id) }}"
                                      method="post"
                                      class="btn btn-primary fw-semibold flex-grow-1 w-100px justify-content-center">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit"
                                            class="btn btn-primary m-0 p-0">
                                        {{ translate('Mark as Solved') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    @if($trip?->parcelRefund)
        <div class="d-flex gap-10px mb-10px">
            @if($trip->parcelRefund->status == PENDING || $trip->parcelRefund->status == APPROVED )
                <button class="btn btn--cancel flex-grow-1 w-100px justify-content-center fw-semibold"
                        type="button"
                        id="deniedButtonParcelRefund"
                        data-url="{{route('admin.trip.refund.denied', [$trip->parcelRefund->id])}}"
                        data-icon="{{ dynamicAsset('public/assets/admin-module/img/denied-icon.png') }}"
                        data-title="{{ translate('Are you sure to Deny the Refund Request')."?" }}"
                        data-sub-title="{{translate("Once you deny the request, the customer will not be refunded the amount he asked for.")}}"
                        data-confirm-btn="{{translate("Deny")}}"
                        data-input-title="{{translate("Deny Note")}}"
                        class="btn btn-outline-danger btn-action d-flex justify-content-center align-items-center"
                >{{ translate('Deny') }}</button>
            @endif
            @if($trip->parcelRefund->status == PENDING || $trip->parcelRefund->status == DENIED )
                <button class="btn btn-primary cmn_focus flex-grow-1 w-100px justify-content-center fw-semibold"
                        type="button"
                        id="approvalButtonParcelRefund"
                        data-url="{{route('admin.trip.refund.approved', [$trip->parcelRefund->id])}}"
                        data-icon="{{ dynamicAsset('public/assets/admin-module/img/approval-icon.png') }}"
                        data-title="{{ translate('Are you sure to Approve the Refund Request')."?" }}"
                        data-sub-title="{{translate("The customer has requested a refund of")}}  <strong>{{set_currency_symbol($trip->parcelRefund->parcel_approximate_price)}}</strong> {{translate("for this parcel.")}}"
                        data-confirm-btn="{{translate("Approve")}}"
                        data-input-title="{{translate("Approval Note")}}"
                        class="btn btn-outline-success btn-action d-flex justify-content-center align-items-center"
                >{{ translate('Approve') }}</button>
            @endif

            @if($trip->parcelRefund->status == APPROVED )
                <button class="btn btn-primary cmn_focus flex-grow-1 w-100px justify-content-center fw-semibold"
                        id="parcelRefundButton"
                        data-amount="{{$trip->parcelRefund->parcel_approximate_price}}"
                        data-url="{{route('admin.trip.refund.store', [$trip->parcelRefund->id])}}"
                        type="button">{{ translate('Make Refund') }}</button>
            @endif
        </div>
    @endif
    <div class="card">
        <div class="card-body">
            <h5 class="text-center mb-3 text-capitalize">{{translate('trip_status')}}</h5>

            <div class="mb-3">
                <label for="trip_status" class="mb-2">{{translate('trip_status')}}</label>
                <select name="trip_status" id="trip_status" class="js-select cmn_focus" disabled>
                    <option selected>{{translate($trip->current_status == PENDING && $trip->ride_request_type == SCHEDULED ? SCHEDULED : $trip->current_status)}}</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="payment_status" class="mb-2">{{translate('payment_status')}}</label>
                <select name="payment_status" id="payment_status" class="js-select cmn_focus" disabled>
                    <option selected>{{translate($trip->payment_status)}}</option>
                </select>
            </div>
            <div class="mb-4">
                <div class="map-wrapper rounded-10 overflow-hidden" id="mapWrapper">

                    <div class="map-overlay" id="mapOverlay"></div>
                    <div id="map-layer" class="rounded-10 overflow-hidden"></div>
                    <button id="mapToggleBtn" class="map-toggle-btn">
                        View In Map
                    </button>

                </div>
            </div>

            <div>
                <ul class="list-icon">
                    <li>
                        <div class="media gap-2">
                            <img width="18" src="{{dynamicAsset('public/assets/admin-module/img/svg/gps.svg')}}" class="svg"
                                 alt="">
                            <div class="media-body">{{$trip->coordinate->pickup_address}}</div>
                        </div>
                    </li>
                    <li>
                        <div class="media gap-2">
                            <img width="18" src="{{dynamicAsset('public/assets/admin-module/img/svg/map-nav.svg')}}"
                                 class="svg" alt="">
                            <div class="media-body">
                                <div>{{$trip->coordinate->destination_address}}</div>
                                @if($trip->entrance)
                                    <a href="#" class="text-primary d-flex">{{$trip->entrance}}</a>

                                @endif
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="media gap-2">
                            <img width="18" src="{{dynamicAsset('public/assets/admin-module/img/svg/distance.svg')}}"
                                 class="svg" alt="">
                            @if($trip->current_status == 'completed')
                                <div class="media-body text-capitalize">{{translate('total_distance')}}
                                    - {{$trip->actual_distance}} {{translate('km')}}</div>
                            @else
                                <div class="media-body text-capitalize">{{translate('total_distance')}}
                                    - {{$trip->estimated_distance}} {{translate('km')}}</div>
                            @endif
                        </div>
                    </li>
                    @if($trip->pickup_note)
                        <li>
                            <div class="pickup-note p-10px fs-14 title-color bg-info-5  rounded-10">
                                <span class="text-info">{{ translate('Pickup Note') }}:</span> {{ $trip->pickup_note }}
                            </div>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
    <div class="modal fade" id="make-refund">
        <div class="modal-dialog modal-lg extra-fare-setup-modal">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Make Refund</h5>
                    <button type="submit" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-4">
                            <label for="refund_amount" class="form-label">{{translate('Refund Amount')}} ($) <i
                                    class="bi bi-info-circle-fill text-primary"></i></label>
                            <input type="text" class="form-control" id="refund_amount"
                                   placeholder="{{translate("Ex : 10")}}">
                        </div>
                        <label class="form-label">{{translate('Refund Method')}} <i
                                class="bi bi-info-circle-fill text-primary"></i></label>
                        <div class="border rounded border-ced4da p-3 mb-4">
                            <div class="d-flex flex-wrap gap-5">
                                <div>
                                    <input type="radio" name="refund_method" id="pay-manually" checked>
                                    <label class="form-check-label" for="pay-manually">Pay Manually</label>
                                </div>
                                <div>
                                    <input type="radio" name="refund_method" id="pay-in-wallet">
                                    <label class="form-check-label" for="pay-in-wallet">Pay in Wallet</label>
                                </div>
                                <div>
                                    <input type="radio" name="refund_method" id="create-refund-coupon">
                                    <label class="form-check-label" for="create-refund-coupon">Create a refund
                                        Coupon</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="refund_reason" class="form-label">{{translate('Refund Note')}}</label>
                            <textarea class="form-control" id="refund_reason" rows="3"
                                      placeholder="{{translate('Type a refund note for your customer')}}"></textarea>
                        </div>
                        <div class="d-flex gap-10px justify-content-end">
                            <button class="btn btn-secondary cmn_reset" data-bs-dismiss="modal"
                                    type="button">{{ translate('Cancel') }}</button>
                            <button class="btn btn-primary cmn_focus" data-bs-dismiss="modal"
                                    type="button">{{ translate('Make Refund') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('script')
    <script>
        let map;
        let waypoints;

        function initMap() {
            const mapLayer = document.getElementById("map-layer");
            const defaultOptions = {zoom: 9};
            map = new google.maps.Map(mapLayer, defaultOptions);

            const directionsService = new google.maps.DirectionsService;
            const directionsDisplay = new google.maps.DirectionsRenderer;
            directionsDisplay.setMap(map);

            const start = ({
                lat: {{ (float)(explode(',', $trip->coordinate->pickup_coordinates ?? '0,0')[0] ?? 0) }},
                lng: {{ (float)(explode(',', $trip->coordinate->pickup_coordinates ?? '0,0')[1] ?? 0) }}
            });
            const end = ({
                lat: {{ (float)(explode(',', $trip->coordinate->destination_coordinates ?? '0,0')[0] ?? 0) }},
                lng: {{ (float)(explode(',', $trip->coordinate->destination_coordinates ?? '0,0')[1] ?? 0) }}
            });
            drawPath(directionsService, directionsDisplay, start, end);
        }

        function drawPath(directionsService, directionsDisplay, start, end) {

            directionsService.route({
                    origin: start,
                    destination: end,
                    travelMode: "DRIVING"
                },
                function (response, status) {
                    if (status === 'OK') {
                        directionsDisplay.setDirections(response);
                    } else {
                        toastr.error('{{translate('problem_in_showing_direction._status:_')}}' + status);
                    }
                });
        }

        const wrapper = document.getElementById("mapWrapper");
const btn = document.getElementById("mapToggleBtn");
const overlay = document.getElementById("mapOverlay");

btn.addEventListener("click", () => {

    // Google Map fullscreen button find
    const fullscreenBtn = document.querySelector(".gm-fullscreen-control");

    if (fullscreenBtn) {
        fullscreenBtn.click(); // default fullscreen trigger
    }

});

    </script>
    <script async defer
            src="https://maps.googleapis.com/maps/api/js?key={{businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null}}&callback=initMap">
    </script>

    <script>
        $(document).ready(function () {
            let showSafetyAlertUserDetails = $('.show-safety-alert-user-details');

            showSafetyAlertUserDetails.on('click', function () {
                localStorage.setItem('safetyAlertUserDetailsStatus', true);
                localStorage.setItem('safetyAlertUserIdFromTrip', $(this).data('user-id'));
            });
        })
    </script>
@endpush
