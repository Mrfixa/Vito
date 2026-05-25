<?php

namespace Modules\TripManagement\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Service\Interfaces\SafetyAlertServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\TripManagement\Transformers\SafetyAlertResource;

class SafetyAlertController extends Controller
{
    protected $tripRequestService;
    protected $safetyAlertService;


    public function __construct(TripRequestServiceInterface $tripRequestService, SafetyAlertServiceInterface $safetyAlertService)
    {
        $this->tripRequestService = $tripRequestService;
        $this->safetyAlertService = $safetyAlertService;
    }


    public function storeSafetyAlert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required|uuid',
            'lat' => 'required',
            'lng' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => DRIVER
            ]
        ];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id], whereHasRelations: $whereHasRelations);
        if (!$safetyAlert) {
            $this->safetyAlertService->create(data: $request->all());
            $data = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id], relations: ['trip'], whereHasRelations: $whereHasRelations);
            $safetyAlertData = new SafetyAlertResource($data);
            sendTopicNotification(
                topic: 'admin_safety_alert_notification',
                title: translate('new_safety_alert'),
                description: translate('you_have_new_safety_alert'),
                type: 'driver',
                sentBy: auth('api')->user()?->id,
                tripReferenceId: $data?->trip?->ref_id,
                route: $this->safetyAlertService->safetyAlertLatestUserRoute()
            );
            return response()->json(responseFormatter(SAFETY_ALERT_STORE_200, $safetyAlertData));
        }
        return response()->json(responseFormatter(SAFETY_ALERT_ALREADY_EXIST_400), 400);
    }

    public function resendSafetyAlert($tripRequestId)
    {
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => DRIVER
            ]
        ];

        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $tripRequestId, 'status' => PENDING], relations: ['trip'], whereHasRelations: $whereHasRelations);
        if (!$safetyAlert) {
            return response()->json(responseFormatter(SAFETY_ALERT_NOT_FOUND_404), 404);
        }
        $safetyAlert->increment('number_of_alert');
        $safetyAlertData = new SafetyAlertResource($safetyAlert);
        sendTopicNotification(
            topic: 'admin_safety_alert_notification',
            title: translate('new_safety_alert'),
            description: translate('you_have_new_safety_alert'),
            type: 'driver',
            sentBy: auth('api')->user()?->id,
            tripReferenceId: $safetyAlert?->trip?->ref_id,
            route: $this->safetyAlertService->safetyAlertLatestUserRoute()
        );
        return response()->json(responseFormatter(SAFETY_ALERT_RESEND_200, $safetyAlertData));
    }

    public function markAsSolvedSafetyAlert($tripRequestId)
    {
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => DRIVER
            ]
        ];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $tripRequestId, 'status' => PENDING], whereHasRelations: $whereHasRelations);
        if (!$safetyAlert) {
            return response()->json(responseFormatter(SAFETY_ALERT_NOT_FOUND_404), 404);
        }
        $attributes = ['resolved_by' => auth('api')->user()?->id];
        $this->safetyAlertService->updatedBy(criteria: ['trip_request_id' => $tripRequestId, 'sent_by' => $safetyAlert->sent_by], data: $attributes);
        $data = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $tripRequestId, 'sent_by' => $safetyAlert->sent_by]);
        $safetyAlertData = new SafetyAlertResource($data);

        return response()->json(responseFormatter(SAFETY_ALERT_MARK_AS_SOLVED, $safetyAlertData));
    }

    public function showSafetyAlert($tripRequestId)
    {
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => DRIVER
            ]
        ];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $tripRequestId], whereHasRelations: $whereHasRelations);

        if (!$safetyAlert) {
            return response()->json(responseFormatter(SAFETY_ALERT_NOT_FOUND_404), 404);
        }

        $safetyAlertData = new SafetyAlertResource($safetyAlert);

        return response()->json(responseFormatter(DEFAULT_200, $safetyAlertData));
    }

    public function deleteSafetyAlert($tripRequestId)
    {
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => DRIVER
            ]
        ];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['trip_request_id' => $tripRequestId], whereHasRelations: $whereHasRelations);
        if (!$safetyAlert) {
            return response()->json(responseFormatter(SAFETY_ALERT_NOT_FOUND_404), 404);
        }
        $safetyAlert->delete();
        return response()->json(responseFormatter(SAFETY_ALERT_UNDO_200));
    }
}
