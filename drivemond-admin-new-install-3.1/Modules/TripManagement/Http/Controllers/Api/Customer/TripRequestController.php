<?php

namespace Modules\TripManagement\Http\Controllers\Api\Customer;

use App\Events\CustomerTripCancelledAfterOngoingEvent;
use App\Events\CustomerTripCancelledEvent;
use App\Jobs\ProcessPushNotifications;
use App\Jobs\SendPushNotificationJob;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\BusinessManagement\Http\Requests\RideListRequest;
use Modules\FareManagement\Service\Interfaces\ParcelFareServiceInterface;
use Modules\FareManagement\Service\Interfaces\ParcelFareWeightServiceInterface;
use Modules\FareManagement\Service\Interfaces\SurgePricingServiceInterface;
use Modules\FareManagement\Service\Interfaces\TripFareServiceInterface;
use Modules\Gateways\Traits\Payment;
use Modules\ParcelManagement\Service\Interfaces\ParcelWeightServiceInterface;
use Modules\PromotionManagement\Service\Interfaces\CouponSetupServiceInterface;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Http\Requests\GetEstimatedFaresOrNotRequest;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Http\Requests\RideRequestCreate;
use Modules\TripManagement\Lib\CommonTrait;
use Modules\TripManagement\Lib\CouponCalculationTrait;
use Modules\TripManagement\Service\Interfaces\FareBiddingServiceInterface;
use Modules\TripManagement\Service\Interfaces\RecentAddressServiceInterface;
use Modules\TripManagement\Service\Interfaces\RejectedDriverRequestServiceInterface;
use Modules\TripManagement\Service\Interfaces\TempTripNotificationServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestCoordinateServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestTimeServiceInterface;
use Modules\TripManagement\Transformers\FareBiddingResource;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;
use Modules\UserManagement\Service\Interfaces\DriverDetailServiceInterface;
use Modules\UserManagement\Service\Interfaces\UserServiceInterface;
use Modules\UserManagement\Transformers\LastLocationResource;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;

class TripRequestController extends Controller
{
    use CommonTrait, TransactionTrait, Payment, CouponCalculationTrait, LevelHistoryManagerTrait;

    protected $tripRequestService;
    protected $tempTripNotificationService;
    protected $fareBiddingService;
    protected $userService;
    protected $driverDetailService;
    protected $rejectedDriverRequestService;
    protected $couponService;
    protected $zoneService;
    protected $tripFareService;
    protected $parcelFareService;
    protected $parcelFareWeightService;
    protected $recentAddressService;
    protected $tripRequestTimeService;

    protected $tripRequestCoordinateService;

    protected $parcelWeightService;

    protected $surgePricingService;
    public function __construct(
        TripRequestServiceInterface           $tripRequestService,
        TempTripNotificationServiceInterface  $tempTripNotificationService,
        FareBiddingServiceInterface           $fareBiddingService,
        UserServiceInterface                  $userService,
        DriverDetailServiceInterface          $driverDetailService,
        RejectedDriverRequestServiceInterface $rejectedDriverRequestService,
        CouponSetupServiceInterface           $couponService,
        ZoneServiceInterface                  $zoneService,
        TripFareServiceInterface              $tripFareService,
        ParcelFareWeightServiceInterface      $parcelFareWeightService,
        ParcelFareServiceInterface            $parcelFareService,
        RecentAddressServiceInterface         $recentAddressService,
        TripRequestTimeServiceInterface       $tripRequestTimeService,
        TripRequestCoordinateServiceInterface $tripRequestCoordinateService,
        ParcelWeightServiceInterface          $parcelWeightService,
        SurgePricingServiceInterface $surgePricingService
    )
    {
        $this->tripRequestService = $tripRequestService;
        $this->tempTripNotificationService = $tempTripNotificationService;
        $this->fareBiddingService = $fareBiddingService;
        $this->userService = $userService;
        $this->driverDetailService = $driverDetailService;
        $this->rejectedDriverRequestService = $rejectedDriverRequestService;
        $this->couponService = $couponService;
        $this->zoneService = $zoneService;
        $this->tripFareService = $tripFareService;
        $this->parcelFareWeightService = $parcelFareWeightService;
        $this->parcelFareService = $parcelFareService;
        $this->recentAddressService = $recentAddressService;
        $this->tripRequestTimeService = $tripRequestTimeService;
        $this->tripRequestCoordinateService = $tripRequestCoordinateService;
        $this->parcelWeightService = $parcelWeightService;
        $this->surgePricingService = $surgePricingService;
    }


    public function createRideRequest(RideRequestCreate $request): JsonResponse
    {
        if ($request->type == "ride_request"){
            $trip = $this->tripRequestService->getIncompleteRide();
            if ($trip && $request->trip_request_id == null && $trip->ride_request_type == 'regular') {
                return response()->json(responseFormatter(INCOMPLETE_RIDE_403), 403);
            }
        }

        if ($request->trip_request_id) {
            $save_trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request['trip_request_id']]);
            $pickup_point = $save_trip->coordinate->pickup_coordinates;
            $destination_point = $save_trip->coordinate->destination_coordinates;
        } else {
            $pickup_coordinates = json_decode($request['pickup_coordinates'], true);
            $destination_coordinates = json_decode($request['destination_coordinates'], true);
            $pickup_point = $pickup_coordinates[0] . ',' . $pickup_coordinates[1];
            $destination_point = $destination_coordinates[0] . ',' . $destination_coordinates[1];
        }

        $zone = $this->zoneService->getByPoints($pickup_point)->where('is_active', 1)->first();
        if (!$zone) {
            return response()->json(responseFormatter(ZONE_404), 404);
        }
        $getTripZone = $this->zoneService->getZoneContainingBothPoints($zone->id, $pickup_point, $destination_point);
        if (!$getTripZone) {
            return response()->json(responseFormatter(ZONE_404), 404);
        }

        $extraFare = $this->checkZoneExtraFare($zone);
        $surgePrice = $this->surgePricingService->checkSurgePricing(zoneId: $zone->id, tripType: $request->type, vehicleCategoryId: $request->vehicle_category_id, scheduledAt: $request['scheduled_at']);

        // SERVER-SIDE FARE CALCULATION — never trust client-supplied fare values.
        // Re-compute the fare from the zone/fare table using the same estimatedFare()
        // logic used by getEstimatedFare() so both paths stay in sync.
        if (!$request->trip_request_id) {
            $pickupCoordinates  = json_decode($request['pickup_coordinates'], true);
            $destCoordinates    = json_decode($request['destination_coordinates'], true);
            $intermediateCoords = [];
            if ($request->filled('intermediate_coordinates')) {
                $intermediateCoords = json_decode($request->intermediate_coordinates, true);
            }

            if ($request->type == 'ride_request') {
                $tripFareData = $this->tripFareService->getBy(criteria: ['zone_id' => $zone->id], relations: ['vehicleCategory']);
                $tripFareData = $tripFareData->filter(fn($i) => $i->vehicleCategory !== null && $i->vehicleCategory->is_active != 0)->values();
                $availableCategories = $tripFareData->pluck('vehicleCategory.type')->unique()->toArray();
                $drivingModes = count($availableCategories) == 2 ? ['DRIVE', 'TWO_WHEELER'] : ($availableCategories[0] == 'car' ? ['DRIVE'] : ['TWO_WHEELER']);
            } else {
                $parcelWeights = $this->parcelWeightService->getBy(limit: 9999, offset: 1);
                $parcelWeight  = $parcelWeights->firstWhere(function ($pw) use ($request) {
                    return $request->parcel_weight >= $pw->min_weight && $request->parcel_weight <= $pw->max_weight;
                });
                if (!$parcelWeight) {
                    return response()->json(responseFormatter(PARCEL_WEIGHT_400), 400);
                }
                $relations = [
                    'fares' => [
                        ['parcel_weight_id', '=', $parcelWeight->id],
                        ['zone_id', '=', $zone->id],
                        ['parcel_category_id', '=', $request->parcel_category_id],
                    ],
                    'zone' => []
                ];
                $whereHasRelations = [
                    'fares' => [
                        'parcel_weight_id' => $parcelWeight->id,
                        'zone_id' => $zone->id,
                        'parcel_category_id' => $request->parcel_category_id,
                    ]
                ];
                $tripFareData  = $this->parcelFareService->findOneBy(criteria: ['zone_id' => $zone->id], whereHasRelations: $whereHasRelations, relations: $relations);
                $drivingModes  = ['TWO_WHEELER'];
            }

            $serverRoutes = getRoutes(
                originCoordinates: $pickupCoordinates,
                destinationCoordinates: $destCoordinates,
                intermediateCoordinates: $intermediateCoords,
                drivingMode: $drivingModes,
            );
            if (array_key_exists('error', $serverRoutes)) {
                return response()->json(responseFormatter(ROUTE_NOT_FOUND_404), 404);
            }

            $serverFares = $this->estimatedFare(
                tripRequest: $request->all(),
                routes: $serverRoutes,
                zone_id: $zone->id,
                zone: $zone,
                tripFare: $tripFareData,
                beforeCreate: false
            );
        }

        if (array_key_exists('bid', $request->all()) && $request['bid']) {
            // Bid mode: driver proposes a fare, accepted by customer — that fare is already
            // validated server-side before the bid was created, so keep it for now.
            $estimatedFare = $request['actual_fare'];
            $actualFare = $request['actual_fare'];
            $riseRequestCount = 1;
            $returnFee = $request->type == PARCEL ? $request->return_fee : 0;
            $cancellationFee = $request->type == PARCEL ? $request->cancellation_fee : 0;
        } elseif (!empty($extraFare) || !empty($surgePrice)) {
            // Use server-computed extra-fare values; ignore any client-sent figures.
            if (!$request->trip_request_id && isset($serverFares)) {
                if ($request->type == 'ride_request') {
                    $matchedFare = collect($serverFares)->firstWhere('vehicle_category_id', $request->vehicle_category_id) ?? $serverFares->first();
                    $estimatedFare = $matchedFare['extra_estimated_fare'] ?? $matchedFare['estimated_fare'];
                } else {
                    $estimatedFare = $serverFares['extra_estimated_fare'] ?? $serverFares['estimated_fare'];
                }
            } else {
                $estimatedFare = $request['extra_estimated_fare'];
            }
            $actualFare = $estimatedFare;
            $riseRequestCount = 0;
            $returnFee = $request->type == PARCEL ? ($serverFares['extra_return_fee'] ?? $request->extra_return_fee ?? 0) : 0;
            $cancellationFee = $request->type == PARCEL ? ($serverFares['extra_cancellation_fee'] ?? $request->extra_cancellation_fee ?? 0) : 0;
        } else {
            // Standard path — always use server-computed estimated fare.
            if (!$request->trip_request_id && isset($serverFares)) {
                if ($request->type == 'ride_request') {
                    $matchedFare = collect($serverFares)->firstWhere('vehicle_category_id', $request->vehicle_category_id) ?? $serverFares->first();
                    $estimatedFare = $matchedFare['estimated_fare'];
                } else {
                    $estimatedFare = $serverFares['estimated_fare'];
                }
            } else {
                // Re-trip (trip_request_id set): keep existing fare from DB record.
                $existingTrip = $this->tripRequestService->findOneBy(criteria: ['id' => $request['trip_request_id']]);
                $estimatedFare = $existingTrip->estimated_fare;
            }
            $actualFare = $estimatedFare;
            $riseRequestCount = 0;
            $returnFee = $request->type == PARCEL ? ($serverFares['return_fee'] ?? $request->return_fee ?? 0) : 0;
            $cancellationFee = $request->type == PARCEL ? ($serverFares['cancellation_fee'] ?? $request->cancellation_fee ?? 0) : 0;
        }
        DB::beginTransaction();
        try {
            if ($request->trip_request_id) {
                $save_trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request['trip_request_id']]);
                $attributes = [
                    'estimated_fare' => $estimatedFare,
                    'actual_fare' => $actualFare,
                    'rise_request_count' => $riseRequestCount,
                    'discount_id' => null,
                    'discount_amount' => null,
                ];
                $this->tripRequestService->updatedBy(criteria: ['id' => $save_trip->id], data: $attributes);
            } else {
                $customer_coordinates = json_decode($request['customer_coordinates'], true);
                $customer_point = $customer_coordinates[0] . ',' . $customer_coordinates[1];
                $request->merge([
                    'customer_id' => auth('api')->id(),
                    'zone_id' => $zone->id,
                    'pickup_coordinates' => $pickup_point,
                    'destination_coordinates' => $destination_point,
                    'estimated_fare' => $estimatedFare,
                    'actual_fare' => $actualFare,
                    'return_fee' => $returnFee,
                    'cancellation_fee' => $cancellationFee,
                    'customer_request_coordinates' => $customer_point,
                    'rise_request_count' => $riseRequestCount
                ]);
                $save_trip = $this->tripRequestService->createRideRequest(attributes: $request->all());
            }

            if ($request->bid) {
                $final = $this->tripRequestService->findOneBy(criteria: ['id' => $save_trip->id], relations: ['driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);
            } else {
                $tripDiscount = $this->tripRequestService->findOneBy(criteria: ['id' => $save_trip->id]);
                $vat_percent = (double)get_cache('vat_percent') ?? 1;
                $estimatedAmount = $tripDiscount->actual_fare / (1 + ($vat_percent / 100));
                $discount = $this->getEstimatedDiscount(user: $tripDiscount->customer, zoneId: $tripDiscount->zone_id, tripType: $tripDiscount->type, vehicleCategoryId: $tripDiscount->vehicle_category_id, estimatedAmount: $estimatedAmount);
                if ($discount['discount_amount'] != 0) {
                    $attributes = [
                        'discount_id' => $discount['discount_id'],
                        'discount_amount' => $discount['discount_amount'],
                    ];
                    $this->tripRequestService->updatedBy(criteria: ['id' => $tripDiscount->id], data: $attributes);
                }
                $final = $this->tripRequestService->findOneBy(criteria: ['id' => $tripDiscount->id], relations: ['driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);
            }
            DB::commit();

            $search_radius = (double)get_cache('search_radius') ?? 5;
            $final->ride_request_type != 'scheduled' && ProcessPushNotifications::dispatch(radius: $search_radius, parcelWeight: $request->weight ?? null, trip: $final);
            $trip = new TripRequestResource($final);

        } catch (Exception $exception) {
            DB::rollBack();
            Log::error(message: 'Trip Request Store Failed', context: [
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(responseFormatter(DEFAULT_FAIL_200), 403);
        }

        return response()->json(responseFormatter(TRIP_REQUEST_STORE_200, $trip));
    }

    public function getEstimatedFare(GetEstimatedFaresOrNotRequest $request): JsonResponse
    {
        if ($request->type === 'ride_request'){
            $trip = $this->tripRequestService->getIncompleteRide();
            if ($trip && $trip->ride_request_type === 'regular') {
                return response()->json(responseFormatter(INCOMPLETE_RIDE_403), 403);
            }
        }

        if (!$request->header('zoneId') || !$this->zoneService->findOne(id: $request->header('zoneId'))) {
            return response()->json(responseFormatter(ZONE_404), 404);
        }

        $user = auth('api')->user();
        $pickupCoordinates = json_decode($request->pickup_coordinates, true);
        $destinationCoordinates = json_decode($request->destination_coordinates, true);
        $pickupPoint = new Point($pickupCoordinates[0], $pickupCoordinates[1]);
        $destinationPoint = new Point($destinationCoordinates[0], $destinationCoordinates[1]);
        $intermediateCoordinates = [];
        if ($request->filled('intermediate_coordinates')) {
            $intermediateCoordinates = json_decode($request->intermediate_coordinates, true);
            $maximumIntermediatePoint = 2;
            if (count($intermediateCoordinates) > $maximumIntermediatePoint) {

                return response()->json(responseFormatter(MAXIMUM_INTERMEDIATE_POINTS_403), 403);
            }
        }

        $zone = $this->zoneService->getByPoints($pickupPoint)->where('is_active', 1)->first();
        if (!$zone) {
            return response()->json(responseFormatter(ZONE_404), 404);
        }
        $getTripZone = $this->zoneService->getZoneContainingBothPoints($zone->id, $pickupPoint, $destinationPoint);

        if (!$getTripZone) {
            return response()->json(responseFormatter(ZONE_404), 404);
        }

        if ($request->type == 'ride_request') {
            $tripFare = $this->tripFareService->getBy(criteria: ['zone_id' => $zone->id], relations: ['vehicleCategory']);
            $tripFare = $tripFare->filter(function ($item) {
                return $item->vehicleCategory !== null && $item->vehicleCategory->is_active != 0;
            })->values();
            $availableCategories = $tripFare->pluck('vehicleCategory.type')->unique()->toArray();
            if (empty($availableCategories)) {
                return response()->json(responseFormatter(NO_ACTIVE_CATEGORY_IN_ZONE_404), 404);
            }
        } else {
            $parcelWeights = $this->parcelWeightService->getBy(limit: 9999, offset: 1);
            $parcelCategoryId = $request->parcel_category_id;
            $parcelWeight = $parcelWeights->firstWhere(function($parcelWeight) use ($request) {
                return $request->parcel_weight >= $parcelWeight->min_weight && $request->parcel_weight <= $parcelWeight->max_weight;
            });
            if (!$parcelWeight) {
                return response()->json(responseFormatter(PARCEL_WEIGHT_400), 400);
            }
            $parcelWeightId = $parcelWeight->id;
            $relations = [
                'fares' => [
                    ['parcel_weight_id', '=', $parcelWeightId],
                    ['zone_id', '=', $zone->id],
                    ['parcel_category_id', '=', $parcelCategoryId],
                ],
                'zone' => []
            ];
            $whereHasRelations = [
                'fares' => [
                    'parcel_weight_id' => $parcelWeightId,
                    'zone_id' => $zone->id,
                    'parcel_category_id' => $parcelCategoryId,
                ]
            ];
            $tripFare = $this->parcelFareService->findOneBy(criteria: ['zone_id' => $zone->id], whereHasRelations: $whereHasRelations, relations: $relations);
        }
        $getRoutes = getRoutes(
            originCoordinates: $pickupCoordinates,
            destinationCoordinates: $destinationCoordinates,
            intermediateCoordinates: $intermediateCoordinates,
            drivingMode: $request->type == 'ride_request' ? (count($availableCategories) == 2 ? ["DRIVE", 'TWO_WHEELER'] : ($availableCategories[0] == 'car' ? ['DRIVE'] : ['TWO_WHEELER'])) : ['TWO_WHEELER'],
        );

        if (array_key_exists('error', $getRoutes)) {
            return response()->json(responseFormatter(ROUTE_NOT_FOUND_404), 404);
        }

        $estimatedFare = $this->estimatedFare(
            tripRequest: $request->all(),
            routes: $getRoutes,
            zone_id: $zone->id,
            zone: $zone,
            tripFare: $tripFare,
            beforeCreate: true
        );
        $this->recentAddressService->create(data: [
            'user_id' => $user?->id,
            'zone_id' => $zone->id,
            'pickup_coordinates' => $pickupCoordinates[0] . ',' . $pickupCoordinates[1],
            'destination_coordinates' => $destinationCoordinates[0] . ',' . $destinationCoordinates[1],
            'pickup_address' => $request->pickup_address,
            'destination_address' => $request->destination_address,
        ]);

        return response()->json(responseFormatter(DEFAULT_200, $estimatedFare), 200);
    }

    public function rideList(RideListRequest $request): JsonResponse
    {
        if (!is_null($request->filter) && $request->filter != CUSTOM_DATE) {
            $date = getDateRange($request->filter);
        } elseif (!is_null($request->filter)) {
            $date = getDateRange([
                'start' => $request->start,
                'end' => $request->end
            ]);
        }

        $criteria = ['customer_id' => auth('api')->id()];
        $whereBetweenCriteria = [];
        if (!empty($date)) {
            $whereBetweenCriteria = ['created_at' => [$date['start'], $date['end']]];
        }

        if (!is_null($request->status) && $request->status != ALL) {
            $criteria['current_status'] = [$request->status];
        }
        $relations = ['driver', 'vehicle.model', 'vehicleCategory', 'time', 'coordinate', 'fee', 'parcel.parcelCategory', 'parcelRefund'];
        $data = $this->tripRequestService->getWithAvg(
            criteria: $criteria,
            relations: $relations,
            orderBy: ['ref_id' => 'desc'],
            limit: $request['limit'],
            offset: $request['offset'],
            withAvgRelation: ['driverReceivedReviews', 'rating'],
            whereBetweenCriteria: $whereBetweenCriteria
        );
        $resource = TripRequestResource::setData('distance_wise_fare')::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $resource, limit: $request['limit'], offset: $request['offset']));
    }

    public function rideDetails($trip_request_id): JsonResponse
    {
        $criteria = ['id' => $trip_request_id, 'customer_id' => auth('api')->id()];
        $relations = ['driver', 'vehicle.model', 'vehicleCategory', 'tripStatus',
            'coordinate', 'fee', 'time', 'parcel', 'parcelUserInfo', 'parcelRefund'];
        $withAvgRelation = ['driverReceivedReviews', 'rating'];

        $data = $this->tripRequestService->findOneWithAvg(criteria: $criteria, relations: $relations, withAvgRelation: $withAvgRelation);
        if (!$data) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        $resource = TripRequestResource::make($data->append('distance_wise_fare'));
        return response()->json(responseFormatter(DEFAULT_200, $resource));
    }

    public function biddingList($trip_request_id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $tripRequest = $this->tripRequestService->findOneBy(criteria: ['id' => $trip_request_id]);
        if ($tripRequest->current_status == PENDING) {
            $bidding = $this->fareBiddingService->getWithAvg(
                criteria: ['trip_request_id' => $trip_request_id, 'is_ignored' => 0],
                relations: ['driver_last_location', 'driver', 'trip_request', 'driver.vehicle.model'],
                limit: $request['limit'],
                offset: $request['offset'],
                withAvgRelation: ['driverReceivedReviews', 'rating']
            );
            $bidding = FareBiddingResource::collection($bidding);

            return response()->json(responseFormatter(constant: DEFAULT_200, content: $bidding, limit: $request['limit'], offset: $request['offset']));
        }
        return response()->json(responseFormatter(constant: DEFAULT_200, content: []));
    }


    public function driversNearMe(Request $request): JsonResponse
    {
        if (is_null($request->header('zoneId'))) {

            return response()->json(responseFormatter(ZONE_404));
        }

        $driverList = $this->tripRequestService->allNearestDrivers(
            latitude: $request->latitude,
            longitude: $request->longitude,
            zoneId: $request->header('zoneId'),
            radius: (float)(get_cache('search_radius') ?? 5)
        );
        $lastLocationDriver = LastLocationResource::collection($driverList);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $lastLocationDriver));
    }

    public function finalFareCalculation(Request $request): JsonResponse
    {
        $trip = $this->tripRequestService->findOne(
            id: $request['trip_request_id'],
            relations: ['vehicleCategory.tripFares', 'customer', 'driver', 'coupon', 'discount', 'time', 'coordinate', 'fee', 'tripStatus', 'parcelRefund']
        );
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }
        if ($trip->current_status != 'completed' && $trip->current_status != 'cancelled' && $trip->type == 'ride_request') {
            return response()->json(responseFormatter(constant: TRIP_STATUS_NOT_COMPLETED_200));
        }
        if ($trip->customer_id != auth('api')->id() && $trip->driver_id != auth('api')->id()) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 403);
        }
        if (($trip->discount_amount != null && $trip->discount_amount > 0 && $trip->actual_fare == $trip->discount_amount) || $trip->paid_fare != 0) {
            $tripData = new TripRequestResource($trip->append('distance_wise_fare'));
            return response()->json(responseFormatter(constant: DEFAULT_200, content: $tripData));
        }
        if ($trip->type == 'ride_request') {
            $fare = $trip->vehicleCategory->tripFares->where('zone_id', $trip->zone_id)->first();
            if (!$fare) {
                return response()->json(responseFormatter(ZONE_404), 404);
            }
        } else {
            $fare = null;
        }
        DB::beginTransaction();
        $calculated_data = $this->calculateFinalFare($trip, $fare);
        $attributes = [
            'extra_fare_amount' => round($calculated_data['extra_fare_amount'], 2),
            'paid_fare' => round($calculated_data['final_fare'], 2),
            'actual_fare' => round($calculated_data['actual_fare'], 2),
            'actual_distance' => $calculated_data['actual_distance'],
        ];
        $this->tripRequestService->update(id: $request->trip_request_id, data: $attributes);
        $trip->refresh();
        $bidOnFare = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $trip->trip_request_id, 'is_ignored' => 0]);
        $response = $this->getFinalCouponDiscount(user: $trip->customer, trip: $trip);
        if ($response['discount'] != 0) {
            $admin_trip_commission = (double)get_cache('trip_commission') ?? 0;
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $final_fare_without_tax = ($trip->paid_fare - $trip->fee->vat_tax - $trip->fee->tips) - $response['discount'];
            $vat = ($vat_percent * $final_fare_without_tax) / 100;
            $admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
            $attributes = [
                'coupon_id' => $response['coupon_id'],
                'coupon_amount' => $response['discount'],
                'paid_fare' => $final_fare_without_tax + $vat + $trip->fee->tips,
            ];
            $trip->fee()->update([
                'vat_tax' => $vat,
                'admin_commission' => $admin_commission
            ]);
            $this->tripRequestService->update(id: $trip->id, data: $attributes);
            $trip->refresh();
            $this->updateCouponCount($response['coupon'], $response['discount']);
        }
        if (!(($bidOnFare || $trip->rise_request_count > 0) && $trip->type == 'ride_request')) {
            $attributes = [
                'discount_id' => null,
                'discount_amount' => null,
            ];
            $this->tripRequestService->update(id: $trip->id, data: $attributes);
            $trip->refresh();
            $response = $this->getFinalDiscount(user: $trip->customer, trip: $trip);
            if ($response['discount_amount'] != 0) {
                $admin_trip_commission = (double)get_cache('trip_commission') ?? 0;
                $vat_percent = (double)get_cache('vat_percent') ?? 1;
                $final_fare_without_tax = ($trip->paid_fare - $trip->fee->vat_tax - $trip->fee->tips) - $response['discount_amount'];
                $vat = ($vat_percent * $final_fare_without_tax) / 100;
                $admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
                $finalAttributes = [
                    'discount_id' => $response['discount_id'],
                    'discount_amount' => $response['discount_amount'],
                    'paid_fare' => $final_fare_without_tax + $vat + $trip->fee->tips,
                ];
                $trip->fee()->update([
                    'vat_tax' => $vat,
                    'admin_commission' => $admin_commission
                ]);
                $this->tripRequestService->update(id: $trip->id, data: $finalAttributes);
                $trip->refresh();
                if ($response['discount_id'] != null) {
                    $this->updateDiscountCount($response['discount_id'], $response['discount_amount']);
                }
            }
        }
        DB::commit();
        $amount = $trip->paid_fare + $trip->return_fee;
        if ($trip->type == PARCEL && $trip->current_status == RETURNING && $trip?->parcel?->payer == "receiver") {
            $this->tripRequestService->update(id: $trip->id, data: [
                'paid_fare' => $amount,
                'due_amount' => $amount
            ]);
            $trip->refresh();
        }
        if ($trip->customer->referralCustomerDetails && $trip->customer->referralCustomerDetails->is_used == 0) {
            $trip->customer->referralCustomerDetails()->update([
                'is_used' => 1
            ]);
            if ($trip->customer?->referralCustomerDetails?->ref_by_earning_amount && $trip->customer?->referralCustomerDetails?->ref_by_earning_amount > 0) {
                $shareReferralUser = $trip->customer?->referralCustomerDetails?->shareRefferalCustomer;
                $this->customerReferralEarningTransaction($shareReferralUser, $trip->customer?->referralCustomerDetails?->ref_by_earning_amount);

                $push = getNotification('referral_reward_received');
                sendDeviceNotification(fcm_token: $shareReferralUser?->fcm_token,
                    title: translate(key: $push['title'], locale: $shareReferralUser?->current_language_key),
                    description: textVariableDataFormat(value: $push['description'], referralRewardAmount: getCurrencyFormat($trip->customer?->referralCustomerDetails?->ref_by_earning_amount), locale: $shareReferralUser?->current_language_key),
                    status: $push['status'],
                    ride_request_id: $shareReferralUser?->id,
                    notification_type: 'referral_code',
                    action: $push['action'],
                    user_id: $shareReferralUser?->id
                );
            }
        }
        $trip = new TripRequestResource($trip->append('distance_wise_fare'));

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }


    public function requestAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'driver_id' => 'required',
            'action' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $trip = $this->tripRequestService->findOneBy(['id' => $request->trip_request_id], relations: ['coordinate']);
        $driver = $this->userService->findOneBy(criteria: ['id' => $request->driver_id], relations: ['vehicle', 'driverDetails', 'lastLocations']);
        if (Cache::get($request['trip_request_id']) == ACCEPTED && $trip->driver_id == $driver->id) {
            return response()->json(responseFormatter(DEFAULT_UPDATE_200));
        }
        $user_status = $driver->driverDetails->availability_status;
        if ($user_status != 'on_bidding' && $user_status != 'available') {
            return response()->json(responseFormatter(constant: DRIVER_403), 403);
        }
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }
        if (!$driver->vehicle) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 403);
        }
        if (get_cache('bid_on_fare') ?? 0) {
            $checkBid = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id, 'driver_id' => $request->driver_id]);

            if (!$checkBid) {
                return response()->json(responseFormatter(constant: DRIVER_BID_NOT_FOUND_403), 403);
            }

        }
        $env = env('APP_MODE');
        $otp = $env != "live" ? '0000' : rand(1000, 9999);

        $attributes = [
            'driver_id' => $driver->id,
            'otp' => $otp,
            'vehicle_id' => $driver->vehicle->id,
            'current_status' => OUT_FOR_PICKUP,
            'vehicle_category_id' => $driver->vehicle->category_id,
        ];

        if ($request['action'] == ACCEPTED) {
            DB::beginTransaction();
            Cache::put($trip->id, ACCEPTED, now()->addHour());
            $this->rejectedDriverRequestService->deleteBy(criteria: ['trip_request_id' => $trip->id]);
            if (get_cache('bid_on_fare') ?? 0) {
                $tripBidding = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id, 'is_ignored' => 0, 'driver_id' => $request->driver_id]);
                if (isset($tripBidding)) {
                    $attributes['actual_fare'] = $tripBidding->bid_fare;
                    $attributes['estimated_fare'] = $tripBidding->bid_fare;
                }
            }
            $data = $this->tempTripNotificationService->getBy(criteria: ['trip_request_id' => $request->trip_request_id, ['user_id', '!=', $driver->id]], relations: ['user']);
            $push = getNotification('another_driver_assigned');
            if (!empty($data)) {
                $notification['title'] = $push['title'];
                $notification['description'] = $push['description'];
                $notification['status'] = $push['status'];
                $notification['ride_request_id'] = $trip->id;
                $notification['type'] = $trip->type;
                $notification['notification_type'] = 'trip';
                $notification['action'] = $push['action'];
                $notification['replace'] = ['tripId' => $trip->ref_id];

                dispatch(new SendPushNotificationJob($notification, $data))->onQueue('high');
                $this->tempTripNotificationService->deleteBy(criteria: ['trip_request_id' => $trip->id]);
            }

            $driver_arrival_time = getRoutes(
                originCoordinates: [
                    explode(',', $trip->coordinate->pickup_coordinates ?? '0,0')[0],
                    explode(',', $trip->coordinate->pickup_coordinates ?? '0,0')[1]
                ],
                destinationCoordinates: [
                    $driver->lastLocations->latitude,
                    $driver->lastLocations->longitude
                ],
            );
            if (array_key_exists('error', $driver_arrival_time)) {
                return response()->json(responseFormatter(ROUTE_NOT_FOUND_404), 404);
            }
            if ($trip->type == 'ride_request') {
                $attributes['driver_arrival_time'] = (double)($driver_arrival_time[0]['duration']) / 60;
            }
            $this->tripRequestService->update(id: $trip->id, data: $attributes);
            $trip->refresh();
            $this->tripRequestService->update(id: $trip->id, data: ['discount_id' => null, 'discount_amount' => null]);
            $trip->tripStatus()->update([$attributes['current_status'] => now()]);
            $trip->time()->update(['driver_arrival_time' => $attributes['driver_arrival_time']]);
            DB::commit();
            if (get_cache('bid_on_fare') ?? 0) {
                $acceptDriverBid = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $request['trip_request_id'], 'driver_id' => $request['driver_id']]);
                $all_bidding = $this->fareBiddingService->getBy(criteria: ['trip_request_id' => $request['trip_request_id'], ['id', '!=', $acceptDriverBid->id]]);
                if ($all_bidding->isNotEmpty()) {
                    $this->fareBiddingService->deleteBy(criteria: ['trip_request_id' => $request['trip_request_id'], ['id' => $all_bidding->id]]);
                }
            }
            $push = getNotification('bid_accepted');
            sendDeviceNotification(fcm_token: $driver->fcm_token,
                title: translate(key: $push['title'], locale: $driver?->current_language_key),
                description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, approximateAmount: getCurrencyFormat($trip->estimated_fare), pickUpLocation: $trip->coordinate->pickup_address, locale: $driver?->current_language_key),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                notification_type: 'trip',
                action: $push['action'],
                user_id: $driver->id);
        } else {
            if (get_cache('bid_on_fare') ?? 0) {
                $all_bidding = $this->fareBiddingService->getBy(criteria: ['trip_request_id' => $request['trip_request_id']]);

                if (count($all_bidding) > 0) {
                    $this->fareBiddingService->deleteBy(criteria: ['trip_request_id' => $request['trip_request_id'], ['id' => $all_bidding->id]]);
                }

            }
        }
        return response()->json(responseFormatter(constant: BIDDING_ACTION_200));
    }


    public function rideResumeStatus(): JsonResponse
    {
        $criteria = ['ride_request_type' => 'regular'];
        $trip = $this->tripRequestService->getIncompleteRide(criteria: $criteria);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }
        $trip = TripRequestResource::make($trip);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }

    public function pendingParcelList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $data = $this->tripRequestService->getPendingParcel(data: array_merge($validator->validated(), ['user_column' => 'customer_id']));
        $trips = TripRequestResource::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }

    public function rideStatusUpdate($trip_request_id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $trip = $this->tripRequestService->findOne(id: $trip_request_id, relations: ['driver', 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }

        // TOCTOU guard: re-read the trip status under a row-level lock so two
        // concurrent cancel requests cannot both pass the terminal-status check
        // and both update the record.
        $lockedStatus = DB::transaction(function () use ($trip_request_id) {
            return TripRequest::where('id', $trip_request_id)
                ->lockForUpdate()
                ->value('current_status');
        });

        $response = match (true) {
            $lockedStatus === CANCELLED => TRIP_STATUS_CANCELLED_403,
            $lockedStatus === COMPLETED => TRIP_STATUS_COMPLETED_403,
            $lockedStatus === RETURNING => TRIP_STATUS_RETURNING_403,
            $trip->is_paused => TRIP_REQUEST_PAUSED_404,
            default => null,
        };

        if ($response) {
            return response()->json(responseFormatter(constant: $response), 403);
        }

        $attributes = [
            'trip_status' => $request['status'],
            'trip_cancellation_reason' => $request['cancel_reason'] ?? null
        ];

        $parcelReturnTimeFeeStatus = businessConfig('parcel_return_time_fee_status', PARCEL_SETTINGS)?->value ?? false;
        $time = (int)businessConfig('return_time_for_driver', PARCEL_SETTINGS)?->value;
        $timeType = businessConfig('return_time_type_for_driver', PARCEL_SETTINGS)?->value;

        if ($parcelReturnTimeFeeStatus) {
            if ($timeType === 'hour') {
                $returnTime = Carbon::now()->addHours($time)->second(0);
            } else {
                $returnTime = Carbon::now()->addDays($time)->second(0);
            }
        } else {
            $returnTime = Carbon::now();
        }

        if ($request->status == 'cancelled' && (in_array($trip->current_status, [OUT_FOR_PICKUP, PENDING, ACCEPTED]))) {
            //referral
            if ($trip->customer->referralCustomerDetails && $trip->customer->referralCustomerDetails->is_used == 0) {
                $trip->customer->referralCustomerDetails()->update([
                    'is_used' => 1
                ]);
                if ($trip->customer?->referralCustomerDetails?->ref_by_earning_amount && $trip->customer?->referralCustomerDetails?->ref_by_earning_amount > 0) {
                    $shareReferralUser = $trip->customer?->referralCustomerDetails?->shareRefferalCustomer;
                    $this->customerReferralEarningTransaction($shareReferralUser, $trip->customer?->referralCustomerDetails?->ref_by_earning_amount);

                    $push = getNotification('referral_reward_received');
                    sendDeviceNotification(fcm_token: $shareReferralUser?->fcm_token,
                        title: translate(key: $push['title'], locale: $shareReferralUser?->current_language_key),
                        description: textVariableDataFormat(value: $push['description'], referralRewardAmount: getCurrencyFormat($trip->customer?->referralCustomerDetails?->ref_by_earning_amount), locale: $shareReferralUser?->current_language_key),
                        status: $push['status'],
                        ride_request_id: $shareReferralUser?->id,
                        notification_type: 'referral_code',
                        action: $push['action'],
                        user_id: $shareReferralUser?->id
                    );
                }
            }

            $data = $this->tempTripNotificationService->getBy(criteria: [
                'trip_request_id' => $trip_request_id
            ], relations: ['user']);

            if (!empty($data)) {
                $push = $trip->ride_request_type == SCHEDULED ? getNotification('customer_canceled_the_trip') : getNotification('customer_canceled_trip');
                if ($trip->driver_id)
                {
                    if (!is_null($trip->driver->fcm_token)) {
                        sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
                            title: translate(key: $push['title'], locale: $trip?->driver?->current_language_key),
                            description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, sentTime: pushSentTime($trip->updated_at), locale: $trip?->driver?->current_language_key),
                            status: $push['status'],
                            ride_request_id: $trip->id,
                            type: $trip->type,
                            notification_type: $trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
                            action: $push['action'],
                            user_id: $trip->driver->id
                        );
                    }
                    try {
                        checkReverbConnection() && CustomerTripCancelledEvent::broadcast($trip->driver, $trip);
                    } catch (\Exception $exception) {
                    }
                    $this->driverDetailService->updateAvailability(data: ['user_id' => $trip->driver_id, 'trip_type' => $trip->type == PARCEL ? PARCEL : ($trip->ride_request_type == SCHEDULED ? SCHEDULED : RIDE_REQUEST)]);
                    $attributes['driver_id'] = $trip->driver_id;
                } else
                {
                    $notification = [
                        'title' => translate($push['title']),
                        'description' => translate($push['description']),
                        'status' => $push['status'],
                        'ride_request_id' => $trip->id,
                        'type' => $trip->type,
                        'notification_type' => 'trip',
                        'action' => $push['action'],
                        'replace' => ['tripId' => $trip->ref_id, 'sentTime' => pushSentTime($trip->updated_at)],
                    ];
                    dispatch(new SendPushNotificationJob($notification, $data))->onQueue('high');
                    if (checkReverbConnection())
                    {
                        foreach ($data as $tempNotification) {
                            try {
                                CustomerTripCancelledEvent::broadcast($tempNotification->user, $trip);
                            } catch (\Exception $exception) {
                            }
                        }
                    }
                }
                $this->tempTripNotificationService->deleteBy(criteria: ['trip_request_id' => $trip->id]);
            }
        }


        if ($trip->driver_id && ($request->status == 'completed' || $request->status == 'cancelled') && $trip->current_status == ONGOING) {
            if ($request->status == 'cancelled') {
                $attributes['fee']['cancelled_by'] = 'customer';
            }
            $attributes['coordinate']['drop_coordinates'] = $trip->driver->lastLocations->latitude . ',' . $trip->driver->lastLocations->longitude;
            $drivingMode = $trip?->vehicleCategory?->type === 'motor_bike' ? 'TWO_WHEELER' : 'DRIVE';
            $intermediate_coordinate = [];
            if ($trip->coordinate->is_reached_1) {
                if ($trip->coordinate->is_reached_2) {
                    $intermediate_coordinate[1] = [
                        explode(',', $trip->coordinate->int_coordinate_2)[0],
                        explode(',', $trip->coordinate->int_coordinate_2)[1]
                    ];
                }
                $intermediate_coordinate[0] = [
                    explode(',', $trip->coordinate->int_coordinate_1)[0],
                    explode(',', $trip->coordinate->int_coordinate_1)[1]
                ];
            }
            $getRoutes = getRoutes([
                explode(',', $trip->coordinate->pickup_coordinates)[0],
                explode(',', $trip->coordinate->pickup_coordinates)[1]
            ], [$trip->driver->lastLocations->latitude, $trip->driver->lastLocations->longitude], $intermediate_coordinate, [$drivingMode]);
            if (array_key_exists('error', $getRoutes)) {
                return response()->json(responseFormatter(constant: [ 'response_code' => 'drop_off_location_not_found_404',
                    'message' => translate('Drop off location not found')]), 403);
            }
            $this->driverDetailService->updateAvailability(data: ['user_id' => $trip->driver_id, 'trip_type' => $trip->type == PARCEL ? PARCEL : ($trip->ride_request_type == SCHEDULED ? SCHEDULED : RIDE_REQUEST)]);
            $tripType = $trip->type == PARCEL ? PARCEL : ($trip->ride_request_type == SCHEDULED ? 'schedule_ride' : 'trip');
            if ($request->status == 'cancelled' && $trip->type == PARCEL) {
                $push = getNotification(key: $tripType . '_canceled', group: ($parcelReturnTimeFeeStatus ? 'driver' : 'customer'));
                if (!is_null($trip->driver->fcm_token)) {
                    sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
                        title: translate(key: $push['title'], locale: $trip?->driver?->current_language_key),
                        description: textVariableDataFormat(value: $push['description'], parcelId: $trip->ref_id, sentTime: pushSentTime($trip->updated_at), dueTime: ($parcelReturnTimeFeeStatus ? $returnTime->format('d M, Y h:i a') : null), locale: $trip?->driver?->current_language_key),
                        status: $push['status'],
                        ride_request_id: $request['trip_request_id'],
                        type: $trip->type,
                        notification_type: 'parcel',
                        action: $push['action'],
                        user_id: $trip->driver->id
                    );
                }
            } else {
                $push = getNotification($tripType . '_completed');
                if (!is_null($trip->driver->fcm_token)) {
                    sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
                        title: translate(key: $push['title'], locale: $trip?->driver?->current_language_key),
                        description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, parcelId: $trip->ref_id, sentTime: pushSentTime($trip->updated_at), locale: $trip?->driver?->current_language_key),
                        status: $push['status'],
                        ride_request_id: $request['trip_request_id'],
                        type: $trip->type,
                        notification_type: $trip->type == RIDE_REQUEST ? 'trip' : 'parcel',
                        action: $push['action'],
                        user_id: $trip->driver->id
                    );
                }
            }

            try {
                checkReverbConnection() && CustomerTripCancelledAfterOngoingEvent::broadcast($trip);
            } catch (\Exception $exception) {
            }
        }
        try
        {
            DB::beginTransaction();
            if ($attributes['trip_status'] ?? null) {
                $this->tripRequestService->update(id: $trip->id, data: ['current_status' => $attributes['trip_status']]);
                $trip->tripStatus()->update([$attributes['trip_status'] => now()]);
                if ($attributes['trip_status'] == COMPLETED || $attributes['trip_status'] == CANCELLED) {
                    $trip?->tripNavigation()->delete();
                }
            }
            if ($attributes['trip_cancellation_reason'] ?? null) {
                $this->tripRequestService->update(id: $trip->id, data: ['trip_cancellation_reason' => $attributes['trip_cancellation_reason']]);
            }
            if ($attributes['driver_id'] ?? null) {
                $this->tripRequestService->update(id: $trip->id, data: ['driver_id' => null]);
            }
            if ($attributes['coordinate'] ?? null) {
                $coordinate = $trip->coordinate;
                if ($coordinate) {
                    $coordinate->update([
                        'drop_coordinates' => $attributes['coordinate']['drop_coordinates'],
                    ]);
                }
            }
            if ($attributes['fee'] ?? null) {
                $trip->fee()->update($attributes['fee']);
            }
            if (($request->status == 'cancelled' || $request->status == 'completed') && $trip->driver_id && $trip->current_status == ONGOING) {
                $this->customerLevelUpdateChecker(auth()->user());
                $this->driverLevelUpdateChecker($trip->driver);
            }


            if ($trip->driver_id && $request->status == 'cancelled' && $trip->current_status == ONGOING && $trip->type == PARCEL) {
                $env = env('APP_MODE');
                $otp = $env != "live" ? '0000' : rand(1000, 9999);
                $returningAttributes = [];
                $returningAttributes['otp'] = $otp;
                if ($trip?->parcel?->payer == SENDER) {
                    $returningAttributes['paid_fare'] = ($trip->paid_fare + $trip->return_fee);
                    $returningAttributes['due_amount'] = $trip->return_fee;
                    $returningAttributes['payment_status'] = PARTIAL_PAID;
                }
                $returningAttributes['current_status'] = RETURNING;
                $returningAttributes['return_time'] = $returnTime;
                $this->tripRequestService->update(id: $trip->id, data: $returningAttributes);
                $trip->refresh();
                $trip->tripStatus()->update([
                    RETURNING => now()
                ]);
                if (businessConfig('parcel_return_time_fee_status', PARCEL_SETTINGS)?->value ?? false) {
                    $trip->lateReturnPenaltyNotification()->create([
                        'sending_notification_at' => $trip->return_time
                    ]);
                }
            }

            DB::commit();

        } catch (Exception $exception)
        {
            DB::rollBack();
            Log::error(message: 'Ride status update error', context: [
                'exception' => $exception->getMessage(),
                'trip_request_id' => $trip_request_id,
                'attributes' => $attributes
            ]);
            return $response()->json(responseFormatter(constant: DEFAULT_FAIL_200), 403);
        }


        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
    }

    public function ignoreBidding(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bidding_id' => 'required',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $bidding = $this->fareBiddingService->findOneBy(criteria: ['id' => $request->bidding_id]);
        if (!$bidding) {

            return response()->json(responseFormatter(constant: DRIVER_BID_NOT_FOUND_403), 403);
        }

        $this->fareBiddingService->update(id: $request->bidding_id, data: ['is_ignored' => 1]);
        if ($bidding->driver_id) {
            if (!is_null($bidding->driver->fcm_token)) {
                $push = getNotification('customer_rejected_bid');
                sendDeviceNotification(fcm_token: $bidding->driver->fcm_token,
                    title: translate(key: $push['title'], locale: $bidding->driver->current_language_key),
                    description: textVariableDataFormat(value: $push['description'], tripId: $bidding->trip_request->ref_id, approximateAmount: getCurrencyFormat($bidding->bid_fare), locale:  $bidding->driver->current_language_key),
                    status: $push['status'],
                    ride_request_id: $bidding->trip_request_id,
                    type: $bidding->trip_request_id,
                    notification_type: 'trip',
                    action: $push['action'],
                    user_id: $bidding->driver->id
                );
            }
        }

        return response()->json(responseFormatter(constant: DEFAULT_200));
    }

    public function arrivalTime(Request $request): JsonResponse
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
        $this->tripRequestTimeService->update(id: $time->id, data: ['customer_arrives_at' => now()]);

        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
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

        $this->tripRequestService->update(id: $request->trip_request_id, data: [
            'map_screenshot' => $request->file,
        ],);

        return response()->json(responseFormatter(DEFAULT_200));
    }

    public function unpaidParcelRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $relations = ['customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
            'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo', 'parcelRefund'];

        $criteria = [
            'customer_id' => auth('api')->id(),
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

    public function coordinateArrival(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'is_reached' => 'required|in:coordinate_1,coordinate_2,destination',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $trip = $this->tripRequestCoordinateService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        $data = match ($request->is_reached) {
            'coordinate_1' => ['is_reached_1' => true],
            'coordinate_2' => ['is_reached_2' => true],
            'destination' => ['is_reached_destination' => true],
        };
        $this->tripRequestCoordinateService->update(id: $trip->id, data: $data);

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));

    }

    public function receivedReturningParcel($trip_request_id): JsonResponse
    {
        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $trip_request_id], relations: ['driver', 'driver.driverDetails', 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund', 'parcel']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }
        if ($trip->current_status == RETURNED) {
            return response()->json(responseFormatter(TRIP_STATUS_RETURNED_403), 403);
        }
        DB::beginTransaction();
        $attributes = [];

        if (($trip?->fee?->cancelled_by == CUSTOMER || (businessConfig('do_not_charge_customer_return_fee')?->value ?? 0) == 0 && businessConfig('parcel_return_time_fee_status', PARCEL_SETTINGS)?->value ?? false) && $trip?->parcel?->payer == 'sender' && $trip->due_amount > 0) {
            $this->cashReturnFeeTransaction($trip);
        }
        if (($trip?->fee?->cancelled_by == CUSTOMER || (businessConfig('do_not_charge_customer_return_fee')?->value ?? 0) == 0 && businessConfig('parcel_return_time_fee_status', PARCEL_SETTINGS)?->value ?? false) && $trip?->parcel?->payer == 'receiver' && $trip->due_amount > 0) {
            $this->cashTransaction($trip, true);
            $this->cashReturnFeeTransaction($trip);
        }
        if ($trip?->fee?->cancelled_by == CUSTOMER) {
            $attributes['payment_status'] = PAID;
        }
        $attributes['due_amount'] = 0;
        $attributes['current_status'] = RETURNED;
        $this->tripRequestService->update(id: $trip->id, data: $attributes);
        $trip->refresh();
        $trip->tripStatus()->update([
            RETURNED => now()
        ]);
        $trip->lateReturnPenaltyNotification()->delete();
        DB::commit();
        $this->returnTimeExceedFeeTransaction($trip);
        $driverDetails = $this->driverDetailService->findOneBy(criteria: ['user_id' => $trip->driver_id]);
        $this->driverDetailService->updatedBy(criteria: ['user_id' => $trip->driver_id], data: ['parcel_count' => max(0, $driverDetails->parcel_count - 1)]);
        $push = getNotification('parcel_returned');
        sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
            title: translate(key: $push['title'], locale: $trip->driver->current_language_key),
            description: textVariableDataFormat(value: $push['description'], parcelId: $trip->ref_id, customerName: $trip->customer->first_name, locale: $trip->driver->current_language_key),
            status: $push['status'],
            ride_request_id: $trip_request_id,
            type: $trip->type,
            notification_type: 'parcel',
            action: $push['action'],
            user_id: $trip->driver->id
        );

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
    }

    public function editScheduledTrip(Request $request, $trip_request_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $trip_request_id]);

        if (!$trip || $trip->ride_request_type == 'regular' || $trip->current_status === ACCEPTED) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }


        $driverRequestNotifyTime = businessConfig(key: 'driver_request_notify_time', settingsType: SCHEDULE_TRIP_SETTINGS)->value ?? 0;
        $driverRequestNotifyTimeType = businessConfig(key: 'driver_request_notify_time_type', settingsType: SCHEDULE_TRIP_SETTINGS)->value ?? 'minute';
        $scheduled_at = Carbon::parse($request->scheduled_at)->format('Y-m-d H:i:s');
        $scheduled_at = Carbon::createFromFormat('Y-m-d H:i:s', $scheduled_at);
        $sending_notification_at = match ($driverRequestNotifyTimeType) {
            'hour' => $scheduled_at->copy()->subHours($driverRequestNotifyTime),
            'minute' => $scheduled_at->copy()->subMinutes($driverRequestNotifyTime),
            'day' => $scheduled_at->copy()->subDays($driverRequestNotifyTime),
            default => $scheduled_at,
        };
        $sending_notification_at = $sending_notification_at->format('Y-m-d H:i:s');

        $criteria = [
            'scheduled_at' => $request->scheduled_at,
            'sending_notification_at' => $sending_notification_at,
        ];

        $trip = $this->tripRequestService->update(id: $trip_request_id, data: $criteria);
        $resource = TripRequestResource::make($trip->append('distance_wise_fare'));

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, $resource));
    }

    public function pendingRideList(Request $request) {
        $user = $this->userService->findOneBy(criteria: ['id' => auth('api')->id()]);
        $criteria = [
            'type' => RIDE_REQUEST,
            'customer_id' => $user->id,
        ];
        $relations = ['driver', 'vehicle.model', 'vehicleCategory', 'tripStatus',
            'coordinate', 'fee', 'time',];
        $trips = $this->tripRequestService->getCustomerPendingRideList(criteria: $criteria, relations: $relations, limit: $request->limit, offset: $request->offset);

        $transformedTrips = TripRequestResource::collection($trips);

        return response()->json(
            responseFormatter(
                constant: DEFAULT_200,
                content: $transformedTrips,
                limit: $request['limit'],
                offset: $request['offset'],
            )
        );
    }
}
