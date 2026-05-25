<?php

namespace Modules\UserManagement\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Entities\TripNavigation;
use Modules\TripManagement\Service\Interfaces\TripNavigationServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\UserManagement\Service\Interfaces\UserLastLocationServiceInterface;
use Modules\UserManagement\Transformers\LastLocationResource;

class LocationController extends Controller
{
    protected $userLastLocationService;
    protected $tripRequestService;
    protected $tripNavigationService;

    public function __construct(UserLastLocationServiceInterface $userLastLocationService, TripRequestServiceInterface $tripRequestService, TripNavigationServiceInterface $tripNavigationService)
    {
        $this->userLastLocationService = $userLastLocationService;
        $this->tripRequestService = $tripRequestService;
        $this->tripNavigationService = $tripNavigationService;
    }

    public function storeLastLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'type' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
            'zone_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $userLastLocation = $this->userLastLocationService->findOneBy(criteria: ['user_id' => $request->user_id], relations: ['user']);
        $attributes = [
            'user_id' => $request->user_id,
            'type' => $request->type,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'zone_id' => $request->zone_id
        ];

        if ($userLastLocation) {
            $this->userLastLocationService->update(id: $userLastLocation->id, data: $attributes);
        } else {
            $this->userLastLocationService->create($attributes);
        }
        $ongoingTrip = $userLastLocation->user->getDriverOngoingTrip();

        if ((businessConfig('enable_real_time_location_sharing', TRIP_SETTINGS)?->value ?? 0) && $request->type == DRIVER && $ongoingTrip && $ongoingTrip->type == RIDE_REQUEST) {
            DB::transaction(function () use ($userLastLocation, $attributes, $ongoingTrip) {
                    $tripNavigationId = $userLastLocation->user
                        ->getDriverOngoingTrip()
                        ?->tripNavigation
                        ?->id;

                    if ($tripNavigationId) {
                        $tripNavigation = $this->tripNavigationService->getLockedTrip(data: ['id' => $tripNavigationId]);
                        $intermediateCoordinates =
                            $tripNavigation->trip->coordinate->intermediate_coordinates
                                ? json_decode($tripNavigation->trip->coordinate->intermediate_coordinates, true)
                                : [];

                        $destinationCoordinates = [
                            explode(',', $tripNavigation->trip->coordinate->destination_coordinates ?? '0,0')[0],
                            explode(',', $tripNavigation->trip->coordinate->destination_coordinates ?? '0,0')[1],
                        ];

                        $lat = (float) $attributes['latitude'];
                        $lng = (float) $attributes['longitude'];

                        $isDeviated = isDriverDeviated(
                            $tripNavigation->encoded_polyline,
                            $lat,
                            $lng
                        );


                        if (!$isDeviated) {
                            $newEncodedPolyline = slicePolylineFromVehiclePosition(
                                $tripNavigation->encoded_polyline,
                                $lat,
                                $lng
                            );

                            $tripNavigation->update([
                                'encoded_polyline' => $newEncodedPolyline
                            ]);

                            return response()->json(responseFormatter(constant: DEFAULT_200, content: ''));
                        }

                        $response = getRoutes(
                            originCoordinates: [$lat,$lng],
                            destinationCoordinates: $destinationCoordinates,
                            intermediateCoordinates: $intermediateCoordinates,
                        );

                        if (!array_key_exists('error', $response)) {
                            $tripNavigation->update([
                                'encoded_polyline' => $response[0]['encoded_polyline']
                            ]);
                        }
                    }
            });
        }

        return response()->json(responseFormatter(constant: DEFAULT_200, content: ''));
    }

    public function getLastLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $trip = $this->tripRequestService->findOne(id: $request['trip_request_id']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }
        $userLastLocation = $this->userLastLocationService->findOneBy(criteria: ['user_id' => $trip->driver_id]);
        $latLocation = LastLocationResource::make($userLastLocation);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $latLocation));
    }
}
