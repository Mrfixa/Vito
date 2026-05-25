<?php

namespace Modules\TripManagement\Http\Controllers\Api\Driver;

use App\Events\AnotherDriverTripAcceptedEvent;
use App\Events\DriverTripAcceptedEvent;
use App\Events\DriverTripCancelledEvent;
use App\Events\DriverTripCompletedEvent;
use App\Events\DriverTripStartedEvent;
use App\Jobs\SendPushNotificationJob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Gateways\Traits\Payment;
use Modules\Gateways\Traits\SmsGatewayForMessage;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Lib\CommonTrait;
use Modules\TripManagement\Lib\CouponCalculationTrait;
use Modules\TripManagement\Service\Interfaces\FareBiddingServiceInterface;
use Modules\TripManagement\Service\Interfaces\RejectedDriverRequestServiceInterface;
use Modules\TripManagement\Service\Interfaces\TempTripNotificationServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestCoordinateServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestTimeServiceInterface;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;
use Modules\UserManagement\Service\Interfaces\DriverDetailServiceInterface;
use Modules\UserManagement\Service\Interfaces\UserLastLocationServiceInterface;
use Modules\UserManagement\Service\Interfaces\UserServiceInterface;

class TripRequestController extends Controller
{

    use CommonTrait, TransactionTrait, Payment, CouponCalculationTrait, LevelHistoryManagerTrait, SmsGatewayForMessage;

    protected $tripRequestService;
    protected $tripRequestTimeService;
    protected $tripRequestCoordinateService;
    protected $userLastLocationService;
    protected $userService;
    protected $driverDetailService;
    protected $tempTripNotificationService;
    protected $fareBiddingService;
    protected $rejectedDriverRequestService;

    public function __construct(
        TripRequestServiceInterface           $tripRequestService,
        TripRequestTimeServiceInterface       $tripRequestTimeService,
        TripRequestCoordinateServiceInterface $tripRequestCoordinateService,
        UserLastLocationServiceInterface      $userLastLocationService,
        UserServiceInterface                  $userService,
        DriverDetailServiceInterface          $driverDetailService,
        TempTripNotificationServiceInterface  $tempTripNotificationService,
        FareBiddingServiceInterface           $fareBiddingService,
        RejectedDriverRequestServiceInterface $rejectedDriverRequestService,
    )
    {
        $this->tripRequestService = $tripRequestService;
        $this->tripRequestTimeService = $tripRequestTimeService;
        $this->tripRequestCoordinateService = $tripRequestCoordinateService;
        $this->userLastLocationService = $userLastLocationService;
        $this->userService = $userService;
        $this->driverDetailService = $driverDetailService;
        $this->tempTripNotificationService = $tempTripNotificationService;
        $this->fareBiddingService = $fareBiddingService;
        $this->rejectedDriverRequestService = $rejectedDriverRequestService;
    }

    public function showRideDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $relations = ['tripStatus', 'customer', 'driver', 'time', 'coordinate', 'time', 'fee', 'parcelRefund'];
        $criteria = ['type' => 'ride_request', 'driver_id' => auth('api')->id(), 'id' => $request->trip_request_id];
        $orderBy = ['created_at' => 'desc'];
        $withAvgRelations = [['customerReceivedReviews', 'rating']];
        $trip = $this->tripRequestService->findOneBy(criteria: $criteria, withAvgRelations: $withAvgRelations, relations: $relations, orderBy: $orderBy);

        if (!$trip || $trip->fee->cancelled_by == 'driver' || (!$trip->driver_id && $trip->current_status == 'cancelled') || ($trip->driver_id && $trip->payment_status == PAID)) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 404);
        }
        $trip = TripRequestResource::make($trip);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }

    public function allRideList()
    {
        $trips = $this->tripRequestService->allRideList();
        if (!$trips) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404, content: $trips));
        }
        $data = TripRequestResource::collection($trips);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $data));
    }

    public function rideWaiting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'waiting_status' => 'required|in:pause,resume'
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $time = $this->tripRequestTimeService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id], relations: ['customer']);
        if (!$time) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 404);
        }
        $this->tripRequestService->rideWaiting($trip, $time);
        $rideRequestType = $trip->ride_request_type == SCHEDULED ? 'schedule_ride_' : 'trip_';
        $waitingStatus = $request->waiting_status == 'resume' ? 'resumed' : 'paused';
        $push = getNotification($rideRequestType . $waitingStatus);
        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: translate(key: $push['title'], locale: $trip?->customer?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], tripId: $trip?->ref_id, locale: $trip?->customer?->current_language_key),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            notification_type: $trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
            action: $push['action'],
            user_id: $trip->customer->id
        );

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));
    }

    public function rideList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filter' => Rule::in([TODAY, PREVIOUS_DAY, THIS_WEEK, LAST_WEEK, LAST_7_DAYS, THIS_MONTH, LAST_MONTH, THIS_YEAR, ALL_TIME, CUSTOM_DATE]),
            'status' => Rule::in([ALL, PENDING, ONGOING, COMPLETED, CANCELLED, RETURNED, ACCEPTED, SCHEDULED]),
            'start' => 'required_if:filter,custom_date|required_with:end|date',
            'end' => 'required_if:filter,custom_date|required_with:start|date',
            'limit' => 'required|numeric|min:1',
            'offset' => 'required|numeric|min:0'
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $data = $this->tripRequestService->rideList(data: $validator->validated());
        $resource = TripRequestResource::setData('distance_wise_fare')::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $resource, limit: $request['limit'], offset: $request['offset']));
    }

    public function arrivalTime(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required'
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $time = $this->tripRequestTimeService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        if (!$time) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 404);
        }
        $this->tripRequestTimeService->updatedBy(criteria: ['trip_request_id' => $request->trip_request_id], data: ['driver_arrives_at' => now()]);

        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
    }

    public function lastRideDetails()
    {
        $relations = ['fee', 'parcelRefund'];
        $lastRide = $this->tripRequestService->findOneBy(criteria: ['driver_id' => auth('api')->id(), 'type' => RIDE_REQUEST], relations: $relations, orderBy: ['created_at' => 'desc']);
        if (!$lastRide) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404, content: $lastRide));
        }
        $data = [];
        $data[] = TripRequestResource::make($lastRide->append('distance_wise_fare'));

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $data));
    }

    public function coordinateArrival(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'is_reached' => 'required|in:coordinate_1,coordinate_2,destination',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $tripCoordinate = $this->tripRequestCoordinateService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        $data = match ($request->is_reached) {
            'coordinate_1' => ['is_reached_1' => true],
            'coordinate_2' => ['is_reached_2' => true],
            'destination' => ['is_reached_destination' => true],
        };
        $this->tripRequestCoordinateService->update(id: $tripCoordinate->id, data: $data);

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));
    }

    public function pendingParcelList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $data = $this->tripRequestService->getPendingParcel(data: array_merge($validator->validated(), ['user_column' => 'driver_id']));

        $trips = TripRequestResource::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }

    public function unpaidParcelRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $relations = ['customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
            'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo'];
        $criteria = [
            'driver_id' => auth('api')->id(),
            'type' => PARCEL,
            'payment_status' => UNPAID,
            ['driver_id', '!=', NULL]
        ];
        $whereHasRelations = [
            'parcel' => ['payer' => SENDER]
        ];
        $data = $this->tripRequestService->getBy(criteria: $criteria, whereHasRelations: $whereHasRelations, relations: $relations, limit: $request->limit, offset: $request->offset);
        $trips = TripRequestResource::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id], relations: ['customer']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }

        $push = getNotification('parcel_returning_otp');
        sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
            title: translate(key: $push['title'], locale: $trip?->customer?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], otp: $trip->otp, parcelId: $trip->ref_id, locale: $trip?->customer?->current_language_key),
            status: $push['status'],
            ride_request_id: $request->trip_request_id,
            type: $trip->type,
            notification_type: 'parcel',
            action: $push['action'],
            user_id: $trip->customer->id
        );

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
    }

    public function matchOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'otp' => Rule::requiredIf(function () {
                return (bool)businessConfig(key: 'driver_otp_confirmation_for_trip', settingsType: TRIP_SETTINGS)?->value == 1;
            }), 'min:4|max:4',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id], relations: ['customer', 'coordinate']);
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 404);
        }
        if ($trip->driver_id != auth('api')->id()) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        if (array_key_exists('otp', $request->all()) && $request['otp'] && $trip->otp !== $request['otp']) {

            return response()->json(responseFormatter(OTP_MISMATCH_404), 403);
        }

        $driverOngoingTrip = $this->tripRequestService->findOneBy(criteria: ['driver_id' => auth('api')->id(), 'type' => RIDE_REQUEST, 'current_status' => ONGOING]);
        if ($driverOngoingTrip) {
            return response()->json(responseFormatter(constant: TRIP_STATUS_ONGOING_403), 403);
        }

        $data = [
            'current_status' => ONGOING
        ];

        DB::beginTransaction();
        $this->tripRequestService->updatedBy(criteria: ['id' => $request->trip_request_id], data: $data);
        $trip->tripStatus()->update(['ongoing' => now()]);
        if ((businessConfig('enable_real_time_location_sharing', TRIP_SETTINGS)?->value ?? 0) && $trip->type == RIDE_REQUEST) {
            $trip->tripNavigation()->firstOrCreate(
                [],
                ['encoded_polyline' => $trip->encoded_polyline]
            );
        }
        DB::commit();

        if ($trip->customer->fcm_token) {
            $push = $trip->ride_request_type == SCHEDULED ? getNotification('schedule_ride_started') : getNotification('trip_started');
            sendDeviceNotification(
                fcm_token: $trip->customer->fcm_token,
                title: translate(key: $push['title'], locale: $trip?->customer?->current_language_key),
                description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, dropOffLocation: $trip->coordinate->destination_address, locale: $trip?->customer?->current_language_key),
                status: $push['status'],
                ride_request_id: $request['trip_request_id'],
                type: $trip['type'],
                notification_type: $trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
                action: $push['action'],
                user_id: $trip->customer->id
            );
        }
        try {
            checkReverbConnection() && DriverTripStartedEvent::broadcast($trip);
        } catch (\Exception $exception) {

        }

        return response()->json(responseFormatter(DEFAULT_STORE_200));
    }

    public function trackLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required',
            'longitude' => 'required',
            'zoneId' => 'required',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $data = [
            'type' => $request->route()->getPrefix() == "api/customer/ride" ? 'customer' : 'driver',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'zone_id' => $request->zoneId
        ];
        $this->userLastLocationService->updatedBy(criteria: ['user_id' => auth('api')->id()], data: $data);

        return response()->json(responseFormatter(DEFAULT_STORE_200));
    }

    public function rideDetails(Request $request, $trip_request_id): JsonResponse
    {
        $criteria = ['id' => $trip_request_id];
        $withAvgRelations = [['customerReceivedReviews', 'rating']];
        if (!is_null($request->type) && $request->type == 'overview') {
            $relations = ['customer' => [], 'vehicleCategory' => [], 'tripStatus' => [], 'time' => [], 'coordinate' => [], 'fee' => [], 'parcel' => [], 'parcelUserInfo' => [], 'parcelRefund' => [], 'fare_biddings' => [['driver_id', '=', auth('api')->id()]]];
            $overViewCriteria = array_merge($criteria, ['current_status' => PENDING]);
            $data = $this->tripRequestService->findOneBy(criteria: $overViewCriteria, withAvgRelations: $withAvgRelations, relations: $relations);
            if (!$data) {
                return response()->json(responseFormatter(TRIP_REQUEST_404), 404);
            }
            if (!is_null($data)) {
                $resource = TripRequestResource::make($data);

                return response()->json(responseFormatter(DEFAULT_200, $resource));
            }
        } else {
            $relations = ['customer', 'vehicleCategory', 'tripStatus', 'time', 'coordinate', 'fee', 'parcel', 'parcelUserInfo', 'parcelRefund'];
            $data = $this->tripRequestService->findOneBy(criteria: $criteria, withAvgRelations: $withAvgRelations, relations: $relations);
            if ($data && auth('api')->id() == $data->driver_id) {
                $resource = TripRequestResource::make($data->append('distance_wise_fare'));

                return response()->json(responseFormatter(DEFAULT_200, $resource));
            }
        }

        return response()->json(responseFormatter(DEFAULT_404), 403);
    }

    public function pendingRideList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $zoneId = $request->header('zoneId');
        if (empty($zoneId)) {
            return response()->json(responseFormatter(ZONE_404));
        }

        $user = $this->userService->findOneBy(
            criteria: ['id' => auth('api')->id()],
            relations: ['driverDetails', 'vehicle']
        );

        if ($user->driverDetails->is_online != 1) {
            return response()->json(responseFormatter(DRIVER_UNAVAILABLE_403), 403);
        }

        $vehicle = $user->vehicle;
        if (is_null($vehicle)) {
            return response()->json(responseFormatter(VEHICLE_NOT_REGISTERED_404, content: []), 403);
        }

        if ($vehicle->is_active == 0) {
            return response()->json(responseFormatter(VEHICLE_NOT_APPROVED_OR_ACTIVE_404, content: []), 403);
        }

        $maxParcel = businessConfig('maximum_parcel_request_accept_limit', DRIVER_SETTINGS);
        $parcelStatus = (bool)($maxParcel->value['status'] ?? false);
        $parcelLimit = (int)($maxParcel->value['limit'] ?? 0);
        $searchRadius = (double)get_cache('search_radius') ?? 5;

        $location = $this->userLastLocationService->findOneBy(['user_id' => $user->id]);
        if (!$location || !$vehicle) {
            return response()->json(responseFormatter(DEFAULT_200, content: ''));
        }

        $baseData = array_merge($validator->validated(), [
            'driver_locations' => $location,
            'distance' => $searchRadius * 1000,
            'zone_id' => $zoneId,
            'vehicle_category_id' => $vehicle->category_id,
            'ride_count' => $user->driverDetails->ride_count ?? 0,
            'parcel_count' => $user->driverDetails->parcel_count ?? 0,
            'parcel_follow_status' => $parcelStatus,
            'max_parcel_request_accept_limit_count' => $parcelLimit,
        ]);

        $acceptedTrip = $this->tripRequestService->findOneBy(criteria: [
            'driver_id' => $user->id,
            'type' => RIDE_REQUEST,
            'current_status' => OUT_FOR_PICKUP,
            'ride_request_type' => 'regular'
        ]);

        if ($acceptedTrip) {
            $data = array_merge($baseData, ['no_more_regular_ride' => true]);
            return $this->tripRequestService->pendingRideResponse($data, $request);
        }

        $ongoingTrip = $this->tripRequestService->findOneBy(criteria: ['driver_id' => $user->id, 'type' => RIDE_REQUEST, 'current_status' => ONGOING], relations: ['coordinate', 'driver.lastLocations']);

        if ($ongoingTrip) {
            $destParts = explode(',', $ongoingTrip->coordinate->destination_coordinates ?? '0,0');
            $distance = distanceCalculator([
                'from_longitude' => $ongoingTrip->driver->lastLocations->longitude,
                'from_latitude' => $ongoingTrip->driver->lastLocations->latitude,
                'to_longitude' => (float)($destParts[1] ?? 0),
                'to_latitude' => (float)($destParts[0] ?? 0),
            ]);

            $data = ($distance * 1.5) > 1
                ? array_merge($baseData, ['no_more_regular_ride' => true])
                : $baseData;

            return $this->tripRequestService->pendingRideResponse($data, $request);
        }

        return $this->tripRequestService->pendingRideResponse($baseData, $request);
    }

    public function returnedParcel(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'otp' => 'required|min:4|max:4',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id, 'type' => PARCEL], relations: ['driver', 'driver.driverDetails', 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }
        if ($trip->driver_id != auth('api')->id()) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        if ($trip->current_status == RETURNED) {
            return response()->json(responseFormatter(TRIP_STATUS_RETURNED_403), 403);
        }
        if ($trip->otp !== $request['otp']) {

            return response()->json(responseFormatter(OTP_MISMATCH_404), 403);
        }
        DB::beginTransaction();
        if (($trip?->fee?->cancelled_by == CUSTOMER || (businessConfig('do_not_charge_customer_return_fee')?->value ?? 0) == 0 && businessConfig('parcel_return_time_fee_status', PARCEL_SETTINGS)?->value ?? false) && $trip?->parcel?->payer == 'sender' && $trip->due_amount > 0) {
            $this->cashReturnFeeTransaction($trip);
        }

        if (($trip?->fee?->cancelled_by == CUSTOMER || (businessConfig('do_not_charge_customer_return_fee')?->value ?? 0) == 0 && businessConfig('parcel_return_time_fee_status', PARCEL_SETTINGS)?->value ?? false) && $trip?->parcel?->payer == 'receiver' && $trip->due_amount > 0) {
            $this->cashTransaction($trip, true);
            $this->cashReturnFeeTransaction($trip);
        }
        if ($trip?->fee?->cancelled_by == CUSTOMER) {
            $trip->payment_status = PAID;
        }
        $trip->due_amount = 0;
        $trip->current_status = RETURNED;
        $trip->save();
        $trip->tripStatus()->update([
            RETURNED => now()
        ]);
        $trip->lateReturnPenaltyNotification()->delete();
        DB::commit();
        $this->returnTimeExceedFeeTransaction($trip);
        //set driver availability_status as on_trip
        $driverDetails = $this->driverDetailService->findOneBy(criteria: ['user_id' => $trip->driver_id]);
        $driverDetails->parcel_count = max(0, $driverDetails->parcel_count - 1);
        $driverDetails->save();
        $push = getNotification('parcel_returned');
        sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
            title: translate(key: $push['title'], locale: $trip->customer->current_language_key),
            description: textVariableDataFormat(value: $push['description'], parcelId: $trip->ref_id, customerName: $trip->customer->first_name . ' ' . $trip->customer->last_name, locale: $trip->customer->current_language_key),
            status: $push['status'],
            ride_request_id: $request->trip_request_id,
            type: $trip->type,
            notification_type: 'parcel',
            action: $push['action'],
            user_id: $trip->customer->id
        );

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
    }

    public function tripOverview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filter' => ['required', Rule::in([TODAY, THIS_WEEK, LAST_WEEK])],
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        return $this->tripRequestService->tripOverview(data: $validator->validated());
    }

    public function ignoreTripNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $this->tempTripNotificationService->deleteBy(criteria: ['trip_request_id' => $request->trip_request_id, 'user_id' => auth('api')->id()]);

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));
    }

    public function rideStatusUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'trip_request_id' => 'required',
            'return_time' => 'sometimes',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id], relations: ['customer']);

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }
        if ($trip->driver_id != auth('api')->id()) {
            return response()->json(responseFormatter(DEFAULT_400), 400);
        }
        if ($trip->current_status == 'cancelled') {
            return response()->json(responseFormatter(TRIP_STATUS_CANCELLED_403), 403);
        }
        if ($trip->current_status == 'completed') {
            return response()->json(responseFormatter(TRIP_STATUS_COMPLETED_403), 403);
        }
        if ($trip->current_status == RETURNING) {
            return response()->json(responseFormatter(TRIP_STATUS_RETURNING_403), 403);
        }
        if ($trip->is_paused) {

            return response()->json(responseFormatter(TRIP_REQUEST_PAUSED_404), 403);
        }

        $data = $this->tripRequestService->updateRideStatus(data: array_merge($validator->validated(), ['trip' => $trip]));
        if (!$data) {
            return response()->json(responseFormatter(constant: [ 'response_code' => 'drop_off_location_not_found_404',
                'message' => translate('Drop off location not found')]), 403);
        }

        $tripType = $trip->type == PARCEL ? PARCEL : ($trip->ride_request_type == SCHEDULED ? 'schedule_ride' : 'trip');
        //Get status wise notification message
        if ($request->status == 'cancelled' && $trip->type == PARCEL) {
            $push = getNotification(key: 'parcel_canceled_after_trip_started', group: 'customer');
            sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
                title: translate(key: $push['title'], locale: $trip->customer->current_language_key),
                description: textVariableDataFormat(value: $push['description'], parcelId: $trip->ref_id, approximateAmount: $trip->paid_fare, locale: $trip->customer->current_language_key),
                status: $push['status'],
                ride_request_id: $request['trip_request_id'],
                type: $trip->type,
                notification_type: $trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
                action: $push['action'],
                user_id: $trip->customer->id
            );
        } else {
            $rideRequestType = $trip->ride_request_type == SCHEDULED ? 'schedule_ride_' : 'trip_';
            $action = $request->status == 'cancelled' ? $rideRequestType . 'canceled' : $rideRequestType . $request->status;
            $push = getNotification($action);
            sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
                title: translate(key: $push['title'], locale: $trip->customer->current_language_key),
                description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, sentTime: pushSentTime($trip->updated_at), locale: $trip->customer->current_language_key),
                status: $push['status'],
                ride_request_id: $request['trip_request_id'],
                type: $trip->type,
                notification_type: $trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
                action: $push['action'],
                user_id: $trip->customer->id
            );
        }

        if (checkReverbConnection()) {
            try {
                $request->status == COMPLETED && DriverTripCompletedEvent::broadcast($trip);
                $request->status == CANCELLED && DriverTripCancelledEvent::broadcast($trip);
            } catch (\Exception $exception) {

            }
        }


        return response()->json(responseFormatter(DEFAULT_UPDATE_200, $data));
    }

    public function requestAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'action' => 'required|in:accepted,rejected',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $user = auth('api')->user()->load(['driverDetails', 'lastLocations', 'userAccount']);
        if ($user->driverDetails->is_suspended)
        {
            return response()->json(responseFormatter(ACCOUNT_SUSPEND), 403);
        }

        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id], relations: ['driver.vehicle.category', 'coordinate']);
        $user_status = $user->driverDetails->availability_status;
        if ($user_status == 'unavailable' || !$user->driverDetails->is_online) {
            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }
        if (in_array($trip->current_status, [ACCEPTED, OUT_FOR_PICKUP]) && $trip->driver_id != $user->id) {
            return response()->json(responseFormatter(TRIP_REQUEST_DRIVER_403), 403);
        }
        if (in_array($trip->current_status, [ACCEPTED, OUT_FOR_PICKUP]) && $trip->driver_id == $user->id) {

            return response()->json(responseFormatter(DEFAULT_UPDATE_200));
        }
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }
        if ($trip->driver_id && $trip->driver_id != $user->id) {
            return response()->json(responseFormatter(TRIP_REQUEST_DRIVER_403), 403);
        }

        $estimatedTime = (float)$trip->time->estimated_time ?? 0;
        $estimatedDistance = (float)$trip->estimated_distance ?? 0;
        $avgKmPerMinute = $estimatedTime > 0 ? ($estimatedDistance / $estimatedTime) * 1.5 : 0;

        if (!$this->tripRequestService->canDriverAcceptRegularTrip(user: $user, trip: $trip, estimatedDistance: $estimatedDistance, avgKmPerMinute: $avgKmPerMinute)) {
            $scheduledAt = Carbon::parse($user->getDriverAcceptedScheduledTrip()->scheduled_at);
            return response()->json(responseFormatter(constant: [
                'response_code' => 'trip_cannot_be_accepted_403',
                'message' => translate(key: 'You have a scheduled trip in :timeDifference minutes', replace: ['timeDifference' => $scheduledAt->diffInMinutes(now())], locale: $user?->current_language_key),
            ]), 403);
        }

        if (!$this->tripRequestService->canDriverAcceptScheduledTrip(user: $user, trip: $trip, estimatedDistance: $estimatedDistance, avgKmPerMinute: $avgKmPerMinute)) {
            $scheduledAt = Carbon::parse($trip->scheduled_at);
            return response()->json(responseFormatter(constant: [
                'response_code' => 'trip_cannot_be_accepted_403',
                'message' => now()->lt($scheduledAt) ? translate(key: 'You will not be able to reach the pickup location in :timeDifference minutes', replace: ['timeDifference' => $scheduledAt->diffInMinutes(now())], locale: $user?->current_language_key) : translate('Your current trip has not ended yet'),
            ]), 403);
        }

        if ($request['action'] != ACCEPTED) {
            if (get_cache('bid_on_fare') ?? 0) {
                $allBidding = $this->fareBiddingService->getBy(criteria: ['trip_request_id' => $request['trip_request_id'], 'driver_id' => $user?->id]);
                if (count($allBidding) > 0) {
                    $push = getNotification('driver_canceled_ride_request');
                    sendDeviceNotification(
                        fcm_token: $trip->customer->fcm_token,
                        title: translate(key: $push['title'], locale: $trip->customer->current_language_key),
                        description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, sentTime: pushSentTime($trip->updated_at), locale: $trip->customer->current_language_key),
                        status: $push['status'],
                        ride_request_id: $trip->id,
                        type: $trip->type,
                        notification_type: 'trip',
                        action: $push['action'],
                        user_id: $trip->customer->id
                    );
                    $this->fareBiddingService->deleteBy(criteria: ['trip_request_id' => $request['trip_request_id'], 'driver_id' => $user?->id]);
                }
            }

            $data = $this->tempTripNotificationService->findOneBy(criteria: ['trip_request_id' => $request['trip_request_id'], 'user_id' => auth('api')->id()]);
            if ($data) {
                $data->delete();
            }
            $this->rejectedDriverRequestService->create([
                'trip_request_id' => $request['trip_request_id'],
                'user_id' => $user?->id
            ]);
            return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
        }
        $env = env('APP_MODE');
        $otp = $env != "live" ? '0000' : rand(1000, 9999);

        if (!($user_status == 'available' || $user_status == 'on_bidding')) {
            return response()->json(responseFormatter(DRIVER_403), 403);
        }
        if ($trip->current_status === "cancelled") {
            return response()->json(responseFormatter(DRIVER_REQUEST_ACCEPT_TIMEOUT_408), 403);
        }
        $bid_on_fare = get_cache('bid_on_fare') ?? 0;
        $attributes = [
            'driver_id' => $user->id,
            'otp' => $otp,
            'vehicle_id' => $user->vehicle->id,
            'vehicle_category_id' => $user->vehicle->category_id,
            'current_status' => ACCEPTED,
            'trip_status' => ACCEPTED,
        ];
        if ($bid_on_fare) {
            $bidding = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id, 'driver_id' => $user->id, 'is_ignored' => 0]);
            if ($bidding) {
                return response()->json(responseFormatter(constant: BIDDING_SUBMITTED_403), 403);
            }
            if ($trip->estimated_fare != $trip->actual_fare) {
                $this->fareBiddingService->create(data: [
                    'trip_request_id' => $request['trip_request_id'],
                    'driver_id' => $user->id,
                    'customer_id' => $trip->customer_id,
                    'bid_fare' => $trip->actual_fare
                ]);
                $attributes['actual_fare'] = $trip->actual_fare;
            }

        }
        Cache::put($trip->id, ACCEPTED, now()->addHour());
        $driverArrivalTime = getRoutes(
            originCoordinates: [
                explode(',', $trip->coordinate->pickup_coordinates ?? '0,0')[0],
                explode(',', $trip->coordinate->pickup_coordinates ?? '0,0')[1]
            ],
            destinationCoordinates: [
                $user->lastLocations->latitude,
                $user->lastLocations->longitude
            ],
        );

        if (array_key_exists('error', $driverArrivalTime)) {
            return response()->json(responseFormatter(constant: [ 'response_code' => 'route_not_found_404',
                'message' => translate('No available route from your current location to the pickup address.')]), 403);
        }
        $attributes['driver_arrival_time'] = (double)($driverArrivalTime[0]['duration']) / 60;
        $data = $this->tempTripNotificationService->getData(data: ['trip_request_id' => $request->trip_request_id]);
        if (!empty($data)) {
            $push = getNotification('another_driver_assigned');
            $notification = [
                'title' => $push['title'],
                'description' => $push['description'],
                'status' => $push['status'],
                'ride_request_id' => $trip->id,
                'type' => $trip->type,
                'notification_type' => $trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
                'action' => $push['action'],
                'replace' => ['tripId' => $trip->ref_id]
            ];
            dispatch(new SendPushNotificationJob($notification, $data))->onQueue('high');
            if (checkReverbConnection()){
                foreach ($data as $tempNotification) {
                    try {
                        AnotherDriverTripAcceptedEvent::broadcast($tempNotification->user, $trip);
                    } catch (\Exception $exception) {

                    }
                }
            }

            $this->tempTripNotificationService->deleteBy(criteria: ['trip_request_id' => $request->trip_request_id, 'user_id' => $user->id]);
        }
        DB::beginTransaction();
        try {
            $lockedTrip = $this->tripRequestService->getLockedTrip(data: ['id' => $request->trip_request_id]);

            if (!$lockedTrip) {
                return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
            }

            if ($lockedTrip->driver_id) {
                return response()->json(responseFormatter(constant: TRIP_REQUEST_DRIVER_403), 403);
            }

            $trip = $this->tripRequestService->updateTripRequestAction(attributes: $attributes, trip: $lockedTrip);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        if ($trip->type == PARCEL && $trip->parcelUserInfo?->firstWhere('user_type', RECEIVER)?->contact_number && businessConfig('parcel_tracking_message')?->value && businessConfig('parcel_tracking_status')?->value && businessConfig('parcel_tracking_status')?->value == 1) {
            $parcelTemplateMessage = businessConfig('parcel_tracking_message')?->value;
            $smsTemplate = smsTemplateDataFormat(
                value: $parcelTemplateMessage,
                customerName: $trip->parcelUserInfo?->firstWhere('user_type', RECEIVER)?->name,
                parcelId: $trip->ref_id,
                trackingLink: route('track-parcel', $trip->ref_id)
            );
            try {
                self::send($trip->parcelUserInfo?->firstWhere('user_type', RECEIVER)?->contact_number, $smsTemplate);
            } catch (\Exception $exception) {

            }
        }

        $this->rejectedDriverRequestService->deleteBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        $push = $trip->ride_request_type == SCHEDULED ? getNotification('schedule_trip_accepted_by_driver') : getNotification('driver_on_the_way');
        sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
            title: translate(key: $push['title'], locale: $trip->customer->current_language_key),
            description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, vehicleCategory: $trip->driver->vehicle->category->name, pickUpLocation: $trip->coordinate->pickup_address, locale: $trip->customer->current_language_key),
            status: $push['status'],
            ride_request_id: $request['trip_request_id'],
            type: $trip->type,
            notification_type: $trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
            action: $push['action'],
            user_id: $trip->customer->id
        );
        try {
            checkReverbConnection() && DriverTripAcceptedEvent::broadcast($trip);
        } catch (\Exception $exception) {

        }
        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
    }

    public function bid(Request $request): JsonResponse
    {
        $user = auth('api')->user()->load(['driverDetails', 'lastLocations']);
        if ($user->driverDetails->availability_status != 'available' || $user->driverDetails->is_online != 1) {

            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }

        if ($user->driverDetails->is_suspended)
        {
            return response()->json(responseFormatter(ACCOUNT_SUSPEND), 403);
        }

        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'bid_fare' => 'numeric|max:99999999',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id], relations: ['customer']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }
        if ($trip->driver_id) {

            return response()->json(responseFormatter(constant: TRIP_REQUEST_DRIVER_403), 403);
        }
        $bidding = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id, 'driver_id' => $user->id]);
        if ($bidding) {

            return response()->json(responseFormatter(constant: BIDDING_SUBMITTED_403), 403);
        }

        $estimatedTime = (float)$trip->time->estimated_time ?? 0;
        $estimatedDistance = (float)$trip->estimated_distance ?? 0;
        $avgKmPerMinute = $estimatedTime > 0 ? ($estimatedDistance / $estimatedTime) * 1.5 : 0;

        if (!$this->tripRequestService->canDriverAcceptRegularTrip(user: $user, trip: $trip, estimatedDistance: $estimatedDistance, avgKmPerMinute: $avgKmPerMinute)) {
            $scheduledAt = Carbon::parse($user->getDriverAcceptedScheduledTrip()->scheduled_at);
            return response()->json(responseFormatter(constant: [
                'response_code' => 'trip_cannot_be_accepted_403',
                'message' => translate(key: 'You have a scheduled trip in :timeDifference minutes', replace: ['timeDifference' => $scheduledAt->diffInMinutes(now())], locale: $user?->current_language_key),
            ]), 403);
        }

        if (!$this->tripRequestService->canDriverAcceptScheduledTrip(user: $user, trip: $trip, estimatedDistance: $estimatedDistance, avgKmPerMinute: $avgKmPerMinute)) {
            $scheduledAt = Carbon::parse($trip->scheduled_at);
            return response()->json(responseFormatter(constant: [
                'response_code' => 'trip_cannot_be_accepted_403',
                'message' => now()->lt($scheduledAt) ? translate(key: 'You will not be able to reach the pickup location in :timeDifference minutes', replace: ['timeDifference' => $scheduledAt->diffInMinutes(now())], locale: $user?->current_language_key) : translate('Your current trip has not ended yet'),
            ]), 403);
        }

        $this->fareBiddingService->create(data: [
            'trip_request_id' => $request['trip_request_id'],
            'driver_id' => $user->id,
            'customer_id' => $trip->customer_id,
            'bid_fare' => $request['bid_fare']
        ]);

        $push = getNotification('received_new_bid');
        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: translate(key: $push['title'], locale: $trip->customer->current_language_key),
            description: textVariableDataFormat(value: $push['description'], tripId: $trip->id, approximateAmount: getCurrencyFormat($request['bid_fare']), locale: $trip->customer->current_language_key),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: $push['action'],
            user_id: $trip->customer->id
        );

        return response()->json(responseFormatter(constant: BIDDING_ACTION_200));
    }

    public function storeScreenshot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'file' => 'required|mimes:jpg,png,webp'
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $this->tripRequestService->storeScreenshot($request->all());

        return response()->json(responseFormatter(DEFAULT_200));
    }

    public function updateToOutForPickup($tripId): JsonResponse
    {
        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $tripId, 'type' => RIDE_REQUEST, 'current_status' => ACCEPTED, 'ride_request_type' => SCHEDULED]);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }
        $driverOngoingTrip = $this->tripRequestService->findOneBy(criteria: ['driver_id' => auth('api')->id(), 'type' => RIDE_REQUEST, 'current_status' => ONGOING]);
        if ($driverOngoingTrip) {
            return response()->json(responseFormatter(constant: TRIP_STATUS_ONGOING_403), 403);
        }
        $attributes = [
            'current_status' => 'out_for_pickup',
        ];
        DB::beginTransaction();
        $this->tripRequestService->update(id: $tripId, data: $attributes);
        $trip->tripStatus()->update([
            'out_for_pickup' => now()
        ]);
        DB::commit();
        if ($trip->customer->fcm_token) {
            $push = getNotification('driver_on_the_way_to_pickup_location');
            sendDeviceNotification(
                fcm_token: $trip->customer->fcm_token,
                title: translate(key: $push['title'], locale: $trip?->customer?->current_language_key),
                description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, vehicleCategory: $trip->driver->vehicle->category->name, pickUpLocation: $trip->coordinate->pickup_address, locale: $trip?->customer?->current_language_key),
                status: $push['status'],
                ride_request_id: $tripId,
                type: $trip['type'],
                notification_type: $trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
                action: $push['action'],
                user_id: $trip->customer->id
            );
        }

        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
    }
}
