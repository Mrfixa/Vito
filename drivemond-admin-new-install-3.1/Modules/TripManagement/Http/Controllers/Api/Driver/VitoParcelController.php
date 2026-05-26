<?php

namespace Modules\TripManagement\Http\Controllers\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Entities\TempTripNotification;
use Modules\TripManagement\Entities\TripRequest;

class VitoParcelController extends Controller
{
    public function atomicAcceptParcel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $result = DB::transaction(function () use ($request) {
            $trip = TripRequest::where('id', $request->trip_request_id)
                ->where('current_status', 'pending')
                ->where('type', 'parcel')
                ->whereNull('driver_id')
                ->lockForUpdate()
                ->first();

            if (!$trip) {
                return null;
            }

            $trip->driver_id = $request->user()->id;
            $trip->current_status = 'accepted';
            $trip->save();

            return $trip;
        });

        if (!$result) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }

        TempTripNotification::where('trip_request_id', $result->id)->delete();

        return response()->json(responseFormatter(DEFAULT_200, $result));
    }
}
