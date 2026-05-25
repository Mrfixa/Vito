<?php

namespace Modules\BusinessManagement\Http\Controllers\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\CancellationReasonServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\ParcelCancellationReasonServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\QuestionAnswerServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\SafetyAlertReasonServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\SafetyPrecautionServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\SettingServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\UserManagement\Service\Interfaces\UserLastLocationServiceInterface;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;

class ConfigController extends Controller
{
    protected $businessSettingService;
    protected $settingService;
    protected $cancellationReasonService;
    protected $parcelCancellationReasonService;
    protected $zoneService;
    protected $userLastLocationService;
    protected $tripRequestService;
    protected $questionAnswerService;
    protected $safetyAlertReasonService;

    protected $safetyPrecautionService;

    public function __construct(BusinessSettingServiceInterface    $businessSettingService, SettingServiceInterface $settingService,
                                CancellationReasonServiceInterface $cancellationReasonService, ParcelCancellationReasonServiceInterface $parcelCancellationReasonService,
                                ZoneServiceInterface               $zoneService, UserLastLocationServiceInterface $userLastLocationService,
                                TripRequestServiceInterface        $tripRequestService, QuestionAnswerServiceInterface $questionAnswerService,
                                SafetyAlertReasonServiceInterface  $safetyAlertReasonService, SafetyPrecautionServiceInterface $safetyPrecautionService)
    {
        $this->businessSettingService = $businessSettingService;
        $this->settingService = $settingService;
        $this->cancellationReasonService = $cancellationReasonService;
        $this->parcelCancellationReasonService = $parcelCancellationReasonService;
        $this->zoneService = $zoneService;
        $this->userLastLocationService = $userLastLocationService;
        $this->tripRequestService = $tripRequestService;
        $this->questionAnswerService = $questionAnswerService;
        $this->safetyAlertReasonService = $safetyAlertReasonService;
        $this->safetyPrecautionService = $safetyPrecautionService;
    }

    public function configuration()
    {
        $info = $this->businessSettingService->getAll(limit: 999, offset: 1);
        $loyaltyPoints = $info
            ->where('key_name', 'loyalty_points')
            ->firstWhere('settings_type', 'driver_settings')?->value;
        $appVersions = $this->businessSettingService->getBy(criteria: ['settings_type' => APP_VERSION]);
        $dataValues = $this->settingService->getBy(criteria: ['settings_type' => SMS_CONFIG]);
        if ($dataValues->where('live_values.status', 1)->isEmpty()) {
            $smsConfiguration = 0;
        } else {
            $smsConfiguration = 1;
        }
        $driverVerifyIdentity = (((int) ($info->firstWhere('key_name', 'face_verification_api')?->value['status'] ?? 0))
            && ((int) ($info->firstWhere('key_name', 'driver_identity_verification_status')?->value ?? 0))
        );
        $chooseVerificationWhenToTrigger = $driverVerifyIdentity
        && in_array('at_intervals', $info->firstWhere('key_name', 'initiate_face_verification')?->value ?? [])
            ? $info->firstWhere('key_name', 'choose_verification_when_to_trigger')?->value : null;

        $configs = [
            'is_demo' => env('APP_MODE') != 'live',
            'maintenance_mode' => checkMaintenanceMode(),
            'required_pin_to_start_trip' => (bool)$info->firstWhere('key_name', 'required_pin_to_start_trip')?->value ?? false,
            'add_intermediate_points' => (bool)$info->firstWhere('key_name', 'add_intermediate_points')?->value ?? false,
            'business_name' => $info->firstWhere('key_name', 'business_name')?->value ?? null,
            'logo' => $info->firstWhere('key_name', 'header_logo')?->value ?? null,
            'bid_on_fare' => (bool)$info->firstWhere('key_name', 'bid_on_fare')?->value ?? 0,
            'driver_completion_radius' => $info->firstWhere('key_name', 'driver_completion_radius')?->value ?? 10,
            'country_code' => $info->firstWhere('key_name', 'country_code')?->value ?? null,
            'business_address' => $info->firstWhere('key_name', 'business_address')->value ?? null,
            'business_contact_phone' => $info->firstWhere('key_name', 'business_contact_phone')?->value ?? null,
            'business_contact_email' => $info->firstWhere('key_name', 'business_contact_email')?->value ?? null,
            'business_support_phone' => $info->firstWhere('key_name', 'business_support_phone')?->value ?? null,
            'business_support_email' => $info->firstWhere('key_name', 'business_support_email')?->value ?? null,
            'conversion_status' => (bool)($loyaltyPoints['status'] ?? false),
            'conversion_rate' => (double)($loyaltyPoints['points'] ?? 0),
            'base_url' => url('/') . '/api/v1/',
            'websocket_url' => $info->firstWhere('key_name', 'websocket_url')?->value ?? null,
            'websocket_port' => (string)$info->firstWhere('key_name', 'websocket_port')?->value ?? 6001,
            'websocket_key' => env('PUSHER_APP_KEY'),
            'websocket_scheme' => env('PUSHER_SCHEME'),
            'review_status' => (bool)$info->firstWhere('key_name', DRIVER_REVIEW)?->value ?? null,
            'level_status' => (bool)$info->firstWhere('key_name', DRIVER_LEVEL)?->value ?? null,
            'image_base_url' => [
                'profile_image_customer' => dynamicStorage('storage/app/public/customer/profile'),
                'profile_image_admin' => dynamicStorage('storage/app/public/employee/profile'),
                'banner' => dynamicStorage('storage/app/public/promotion/banner'),
                'vehicle_category' => dynamicStorage('storage/app/public/vehicle/category'),
                'vehicle_model' => dynamicStorage('storage/app/public/vehicle/model'),
                'vehicle_brand' => dynamicStorage('storage/app/public/vehicle/brand'),
                'profile_image' => dynamicStorage('storage/app/public/driver/profile'),
                'identity_image' => dynamicStorage('storage/app/public/driver/identity'),
                'documents' => dynamicStorage('storage/app/public/driver/document'),
                'pages' => dynamicStorage('storage/app/public/business/pages'),
                'conversation' => dynamicStorage('storage/app/public/conversation'),
                'parcel' => dynamicStorage('storage/app/public/parcel/category'),
            ],
            'otp_resend_time' => (int)$info->firstWhere('key_name', 'otp_resend_time')?->value ?? 60,
            'currency_decimal_point' => $info->firstWhere('key_name', 'currency_decimal_point')?->value ?? null,
            'currency_code' => $info->firstWhere('key_name', 'currency_code')?->value ?? null,
            'currency_symbol' => $info->firstWhere('key_name', 'currency_symbol')->value ?? '$',
            'currency_symbol_position' => $info->firstWhere('key_name', 'currency_symbol_position')?->value ?? null,
            'about_us' => $info->firstWhere('key_name', 'about_us')?->value ?? null,
            'privacy_policy' => $info->firstWhere('key_name', 'privacy_policy')?->value ?? null,
            'terms_and_conditions' => $info->firstWhere('key_name', 'terms_and_conditions')?->value ?? null,
            'legal' => $info->firstWhere('key_name', 'legal')?->value,
            'refund_policy' => $info->firstWhere('key_name', 'refund_policy')?->value,
            'verification' => (bool)$info->firstWhere('key_name', 'driver_verification')?->value ?? 0,
            'sms_verification' => (bool)$info->firstWhere('key_name', 'sms_verification')?->value ?? 0,
            'email_verification' => (bool)$info->firstWhere('key_name', 'email_verification')?->value ?? 0,
            'facebook_login' => (bool)$info->firstWhere('key_name', 'facebook_login')?->value['status'] ?? 0,
            'google_login' => (bool)$info->firstWhere('key_name', 'google_login')?->value['status'] ?? 0,
            'self_registration' => (bool)$info->firstWhere('key_name', 'driver_self_registration')?->value ?? 0,
            'referral_earning_status' => (bool)referralEarningSetting('referral_earning_status', DRIVER)?->value,
            'parcel_return_time_fee_status' => (bool)businessConfig('parcel_return_time_fee_status', PARCEL_SETTINGS)?->value ?? false,
            'return_time_for_driver' => (int)businessConfig('return_time_for_driver', PARCEL_SETTINGS)?->value ?? 0,
            'return_time_type_for_driver' => businessConfig('return_time_type_for_driver', PARCEL_SETTINGS)?->value ?? "day",
            'return_fee_for_driver_time_exceed' => (double)businessConfig('return_fee_for_driver_time_exceed', PARCEL_SETTINGS)?->value ?? 0,
            'do_not_charge_customer_return_fee' => (bool)businessConfig('do_not_charge_customer_return_fee', PARCEL_SETTINGS)?->value ?? true,
            'app_minimum_version_for_android' => (double)$appVersions->firstWhere('key_name', 'driver_app_version_control_for_android')?->value['minimum_app_version'] ?? 0,
            'app_url_for_android' => $appVersions->firstWhere('key_name', 'driver_app_version_control_for_android')?->value['app_url'] ?? null,
            'app_minimum_version_for_ios' => (double)$appVersions->firstWhere('key_name', 'driver_app_version_control_for_ios')?->value['minimum_app_version'] ?? 0,
            'app_url_for_ios' => $appVersions->firstWhere('key_name', 'driver_app_version_control_for_ios')?->value['app_url'] ?? null,
            'firebase_otp_verification' => (bool)$info->firstWhere('key_name', 'firebase_otp_verification_status')?->value == 1,
            'sms_gateway' => (bool)$smsConfiguration,
            'chatting_setup_status' => (bool)$info->firstWhere('key_name', 'chatting_setup_status')?->value == 1,
            'driver_question_answer_status' => (bool)$info->firstWhere('key_name', 'chatting_setup_status')?->value == 1 && (bool)$info->firstWhere('key_name', 'driver_question_answer_status')?->value == 1,
            'maximum_parcel_request_accept_limit_status_for_driver' => (bool)$info->firstWhere('key_name', 'maximum_parcel_request_accept_limit')?->value['status'] == 1,
            'maximum_parcel_request_accept_limit_for_driver' => (int)$info->firstWhere('key_name', 'maximum_parcel_request_accept_limit')?->value['limit'] ?? 0,
            'parcel_weight_unit' => businessConfig(key: 'parcel_weight_unit', settingsType: PARCEL_SETTINGS)?->value ?? 'kg',
            'safety_feature_status' => (bool)$info->firstWhere('key_name', 'safety_feature_status')?->value == 1,
            'safety_feature_minimum_trip_delay_time' => $info->firstWhere('key_name', 'safety_feature_status')?->value == 1 ? convertTimeToSecond(
                $info->firstWhere('key_name', 'for_trip_delay')?->value['minimum_delay_time'],
                $info->firstWhere('key_name', 'for_trip_delay')?->value['time_format']
            ) : null,
            'safety_feature_minimum_trip_delay_time_type' => $info->firstWhere('key_name', 'safety_feature_status')?->value == 1 ? $info->firstWhere('key_name', 'for_trip_delay')?->value['time_format'] : null,
            'after_trip_completed_safety_feature_active_status' => (bool)$info->firstWhere('key_name', 'safety_feature_status')?->value == 1 && (bool)$info->firstWhere('key_name', 'after_trip_complete')?->value['safety_feature_active_status'] == 1,
            'after_trip_completed_safety_feature_set_time' => $info->firstWhere('key_name', 'after_trip_complete')?->value['safety_feature_active_status'] == 1 ? convertTimeToSecond(
                $info->firstWhere('key_name', 'after_trip_complete')?->value['set_time'],
                $info->firstWhere('key_name', 'after_trip_complete_time_format')?->value
            )
                : null,
            'after_trip_completed_safety_feature_set_time_type' => $info->firstWhere('key_name', 'after_trip_complete')?->value['safety_feature_active_status'] == 1 ? $info->firstWhere('key_name', 'after_trip_complete_time_format')?->value : null,
            'safety_feature_emergency_govt_number' => $info->firstWhere('key_name', 'safety_feature_status')?->value == 1 ? $info->firstWhere('key_name', 'emergency_govt_number_for_call')?->value : null,
            'otp_confirmation_for_trip' => (bool)$info->firstWhere('key_name', 'driver_otp_confirmation_for_trip')?->value == 1,
            'fuel_types' => array_keys(FUEL_TYPES),
            'cash_in_hand_setup_status' => (bool)$info->firstWhere('key_name', 'cash_in_hand_setup_status')?->value ?? false,
            'cash_in_hand_max_amount_to_hold_cash' =>  $info->firstWhere('key_name', 'max_amount_to_hold_cash')?->value ?? 0,
            'cash_in_hand_min_amount_to_pay' =>  $info->firstWhere('key_name', 'min_amount_to_pay')?->value ?? 0,
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_image_size' => maxUploadSize('image'),
            'upload_max_file_size' => maxUploadSize('file'),
            'verify_driver_identity' => $driverVerifyIdentity,
            'initiate_face_verification_during_sign_up' => $driverVerifyIdentity &&in_array('during_sign_up', $info->firstWhere('key_name', 'initiate_face_verification')?->value ?? []),
            'trigger_verification_at_intervals' => $driverVerifyIdentity && in_array('at_intervals', $info->firstWhere('key_name', 'initiate_face_verification')?->value ?? []),
            'choose_verification_when_to_trigger' => $chooseVerificationWhenToTrigger,
            'frequency_within_time_period' => $chooseVerificationWhenToTrigger == 'within_a_time_period' ? (int) convertTimeToSecond(
                $info->firstWhere('key_name', 'trigger_frequency_time_within_a_time_period')?->value,
                $info->firstWhere('key_name', 'trigger_frequency_time_type_within_a_time_period')?->value
            )
                : null,
            'trips_required_before_triggering_verification' => $chooseVerificationWhenToTrigger == 'randomly_during_trips' ?
                (int) $info->firstWhere('key_name', 'trips_required_before_random_verification')?->value : null,
            'is_otp_enabled' => isOtpEnabled(),
            'driver_login_options' => $info->firstWhere(key: 'key_name', operator: 'driver_login_options')?->value ?? ['manual_login' => 1, 'otp_login' => 0],
            'is_real_time_location_sharing_enabled' => (bool) (businessConfig('enable_real_time_location_sharing', TRIP_SETTINGS)?->value ?? 0)
        ];

        return response()->json($configs);
    }

    public function getPaymentMethods()
    {
        $methods = $this->settingService->getBy(criteria: ['settings_type' => PAYMENT_CONFIG]);
        $data = [];
        foreach ($methods as $method) {
            $additionalData = json_decode($method->additional_data, true);
            if ($method?->is_active == 1) {
                $data[] = [
                    'gateway' => $method->key_name,
                    'gateway_title' => $additionalData['gateway_title'],
                    'gateway_image' => $additionalData['gateway_image']
                ];
            }
        }
        return collect($data);
    }

    public function cancellationReasonList()
    {
        $ongoingRide = $this->cancellationReasonService->getBy(criteria: ['cancellation_type' => 'ongoing_ride', 'user_type' => 'driver', 'is_active' => 1])->pluck('title')->toArray();
        $acceptedRide = $this->cancellationReasonService->getBy(criteria: ['cancellation_type' => 'accepted_ride', 'user_type' => 'driver', 'is_active' => 1])->pluck('title')->toArray();
        $data = [
            'ongoing_ride' => $ongoingRide,
            'accepted_ride' => $acceptedRide,
        ];
        return response(responseFormatter(DEFAULT_200, $data));
    }

    public function parcelCancellationReasonList()
    {
        $ongoingRide = $this->parcelCancellationReasonService->getBy(criteria: ['cancellation_type' => 'ongoing_ride', 'user_type' => 'driver', 'is_active' => 1])->pluck('title')->toArray();
        $acceptedRide = $this->parcelCancellationReasonService->getBy(criteria: ['cancellation_type' => 'accepted_ride', 'user_type' => 'driver', 'is_active' => 1])->pluck('title')->toArray();
        $data = [
            'ongoing_ride' => $ongoingRide,
            'accepted_ride' => $acceptedRide,
        ];
        return response(responseFormatter(DEFAULT_200, $data));
    }


    public function getZone(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required',
            'lng' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, errorProcessor($validator)), 400);
        }

        $point = new Point($request->lat, $request->lng);
        $zone = $this->zoneService->getByPoints($point)->where('is_active', 1)->first();

        if ($zone) {
            return response()->json(responseFormatter(DEFAULT_200, $zone), 200);
        }

        return response()->json(responseFormatter(ZONE_RESOURCE_404), 404);
    }

    /**
     * Summary of placeApiAutocomplete
     * @param Request $request
     * @return JsonResponse
     */
    public function placeApiAutocomplete(Request $request): JsonResponse
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'search_text' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, errorProcessor($validator)), 400);
        }

        $mapApiKey = businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;

        $url = 'https://places.googleapis.com/v1/places:autocomplete';
        $data = [
            'input' => $request->input('search_text'),
            // Optionally, you can add more parameters here
            // 'components' => 'country:IN', // Example: Restrict results to a specific country
        ];

        // API Headers
        $headers = [
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $mapApiKey,
            'X-Goog-FieldMask' => '*'
        ];

        // Send POST request
        $response = Http::withHeaders($headers)->post($url, $data);
        return response()->json(responseFormatter(DEFAULT_200, $response->json()), 200);
    }

    public function distanceApi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'origin_lat' => 'required',
            'origin_lng' => 'required',
            'destination_lat' => 'required',
            'destination_lng' => 'required',
            'mode' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, errorProcessor($validator)), 400);
        }

        $mapApiKey = businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;
        $response = Http::get(MAP_API_BASE_URI . '/distancematrix/json?origins=' . $request['origin_lat'] . ',' . $request['origin_lng'] . '&destinations=' . $request['destination_lat'] . ',' . $request['destination_lng'] . '&travelmode=' . $request['mode'] . '&key=' . $mapApiKey);

        return response()->json(responseFormatter(DEFAULT_200, $response->json()), 200);
    }

    public function geocodeApi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required',
            'lng' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, errorProcessor($validator)), 400);
        }
        $mapApiKey = businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;
        $response = Http::get(MAP_API_BASE_URI . '/geocode/json?latlng=' . $request->lat . ',' . $request->lng . '&key=' . $mapApiKey);
        return response()->json(responseFormatter(DEFAULT_200, $response->json()), 200);
    }

    public function getRoutes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $trip = $this->tripRequestService->findOne(id: $request->trip_request_id, relations: ['coordinate', 'vehicleCategory']);
        if (!$trip) {

            return response()->json(responseFormatter(constant: TRIP_REQUEST_404, errors: errorProcessor($validator)), 404);
        }

        $pickupCoordinates = [
            auth()->user()->lastLocations->latitude,
            auth()->user()->lastLocations->longitude,
        ];

        $intermediateCoordinates = [];
        if ($trip->current_status == ONGOING) {
            $destinationCoordinates = [
                explode(',', $trip->coordinate->destination_coordinates ?? '0,0')[0],
                explode(',', $trip->coordinate->destination_coordinates ?? '0,0')[1],
            ];
            $intermediateCoordinates = $trip->coordinate->intermediate_coordinates ? json_decode($trip->coordinate->intermediate_coordinates, true) : [];
        } else {
            $destinationCoordinates = [
                explode(',', $trip->coordinate->pickup_coordinates ?? '0,0')[0],
                explode(',', $trip->coordinate->pickup_coordinates ?? '0,0')[1],
            ];
        }

        $drivingMode = auth()->user()->vehicleCategory->category->type == 'motor_bike' ? 'TWO_WHEELER' : 'DRIVE';

        $getRoutes = getRoutes(
            originCoordinates: $pickupCoordinates,
            destinationCoordinates: $destinationCoordinates,
            intermediateCoordinates: $intermediateCoordinates,
        ); //["DRIVE", "TWO_WHEELER"]

        if ((businessConfig('enable_real_time_location_sharing', TRIP_SETTINGS)?->value ?? 0) && $trip->type == RIDE_REQUEST && $trip->current_status == ONGOING ) {
            $trip->tripNavigation()->firstOrCreate(
                [],
                ['encoded_polyline' => $getRoutes[0]['encoded_polyline'] ?? $trip->encoded_polyline]
            );
        }

        $result = [];
        foreach ($getRoutes as $route) {
            if ($route['drive_mode'] == $drivingMode) {
                if ($trip->current_status == 'completed' || $trip->current_status == 'cancelled') {
                    $result['is_dropped'] = true;
                } else {
                    $result['is_dropped'] = false;
                }
                if ($trip->current_status === PENDING || $trip->current_status === ACCEPTED) {
                    $result['is_picked'] = false;
                } else {
                    $result['is_picked'] = true;
                }
                return [array_merge($result, $route)];
            }
        }

    }

    public function predefinedQuestionAnswerList(): JsonResponse
    {
        $predefinedQAs = $this->questionAnswerService->getBy(criteria: ['is_active' => true], orderBy: ['created_at' => 'desc']);

        return response()->json(responseFormatter(DEFAULT_200, $predefinedQAs), 200);
    }

    public function otherEmergencyContactList(): JsonResponse
    {
        $criteria = [
            'settings_type' => SAFETY_FEATURE_SETTINGS,
            'key_name' => 'emergency_other_numbers_for_call'
        ];
        $emergencyOtherNumberList = businessConfig(key: 'emergency_number_for_call_status', settingsType: 'safety_feature_settings')?->value == 1 ? $this->businessSettingService->findOneBy(criteria: $criteria)?->value : null;
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $emergencyOtherNumberList));
    }

    public function safetyAlertReasonList(): JsonResponse
    {
        $criteria = [
            'is_active' => 1,
            'reason_for_whom' => DRIVER
        ];
        $safetyAlertReasons = businessConfig(key: 'safety_alert_reasons_status', settingsType: 'safety_feature_settings')?->value == 1
            ? $this->safetyAlertReasonService->getBy(criteria: $criteria)->pluck('reason')->map(function ($reason) {
                return ['reason' => $reason];
            })
            : null;
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $safetyAlertReasons));
    }

    public function safetyPrecautionList(): JsonResponse
    {
        $criteria = [
            'is_active' => 1,
            ['for_whom', 'like', '%' . DRIVER . '%'],
        ];
        $safetyPrecautions = $this->safetyPrecautionService->getBy(criteria: $criteria);
        $responseData = $safetyPrecautions->map(function ($item) {
            return [
                'title' => $item['title'],
                'description' => $item['description'],
            ];
        });
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $responseData));
    }
}
