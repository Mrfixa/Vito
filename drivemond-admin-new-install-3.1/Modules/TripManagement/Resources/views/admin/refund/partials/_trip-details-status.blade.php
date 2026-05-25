@push('css_or_js')
    <style>
        #map-layer {
            max-width: 706px;
            min-height: 430px;
        }
    </style>

@endpush
<div class="col-lg-4">
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
                    <option selected>{{translate($trip->current_status)}}</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="payment_status" class="mb-2">{{translate('payment_status')}}</label>
                <select name="payment_status" id="payment_status" class="js-select cmn_focus" disabled>
                    <option selected>{{translate($trip->payment_status)}}</option>
                </select>
            </div>
            <div class="mb-4">
                <div id="map-layer"></div>
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
                </ul>
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
    </script>
    <script async defer
            src="https://maps.googleapis.com/maps/api/js?key={{businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null}}&callback=initMap">
    </script>
@endpush
