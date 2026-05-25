<?php

namespace Modules\BusinessManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\BusinessManagement\Http\Requests\UserLocationStore;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\CancellationReasonServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\ParcelCancellationReasonServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\ParcelRefundReasonServiceInterface;
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
    protected $parcelRefundReasonService;
    protected $safetyAlertReasonService;
    protected $safetyPrecautionService;

    public function __construct(BusinessSettingServiceInterface          $businessSettingService, SettingServiceInterface $settingService,
                                CancellationReasonServiceInterface       $cancellationReasonService, ZoneServiceInterface $zoneService,
                                UserLastLocationServiceInterface         $userLastLocationService, TripRequestServiceInterface $tripRequestService,
                                ParcelCancellationReasonServiceInterface $parcelCancellationReasonService, ParcelRefundReasonServiceInterface $parcelRefundReasonService,
                                SafetyAlertReasonServiceInterface        $safetyAlertReasonService, SafetyPrecautionServiceInterface $safetyPrecautionService)
    {
        $this->businessSettingService = $businessSettingService;
        $this->settingService = $settingService;
        $this->cancellationReasonService = $cancellationReasonService;
        $this->parcelCancellationReasonService = $parcelCancellationReasonService;
        $this->zoneService = $zoneService;
        $this->userLastLocationService = $userLastLocationService;
        $this->tripRequestService = $tripRequestService;
        $this->parcelRefundReasonService = $parcelRefundReasonService;
        $this->safetyAlertReasonService = $safetyAlertReasonService;
        $this->safetyPrecautionService = $safetyPrecautionService;
    }

    public function configuration()
    {
        $info = $this->businessSettingService->getAll(limit: 999, offset: 1);

        $loyaltyPoints = $info
            ->where('key_name', 'loyalty_points')
            ->firstWhere('settings_type', 'customer_settings')?->value;
        $martExternalSetting = false;
        if (checkSelfExternalConfiguration()) {
            $martBaseUrl = externalConfig('mart_base_url')?->value;
            $systemSelfToken = externalConfig('system_self_token')?->value;
            $martToken = externalConfig('mart_token')?->value;
            try {
                $response = Http::get($martBaseUrl . '/api/v1/configurations/get-external',
                    [
                        'mart_token' => $martToken,
                        'vito_base_url' => url('/'),
                        'vito_token' => $systemSelfToken,
                    ]);
                if ($response->successful()) {
                    $martResponse = $response->json();
                    $martExternalSetting = $martResponse['status'];
                }
            } catch (\Exception $exception) {

            }

        }
        $appVersions = $this->businessSettingService->getBy(criteria: ['settings_type' => APP_VERSION]);
        $dataValues = $this->settingService->getBy(criteria: ['settings_type' => SMS_CONFIG]);
        if ($dataValues->where('live_values.status', 1)->isEmpty()) {
            $smsConfiguration = 0;
        } else {
            $smsConfiguration = 1;
        }
        $zoneExtraFare = $this->zoneService->getBy(criteria: ['is_active' => 1, 'extra_fare_status' => 1]);
        $zoneExtraFare = $zoneExtraFare->map(function ($query) {
            return [
                'status' => $query->extra_fare_status,
                'zone_id' => $query->id,
                'reason' => $query->extra_fare_reason,
            ];
        });
        $configs = [
            'is_demo' => env('APP_MODE') != 'live',
            'maintenance_mode' => checkMaintenanceMode(),
            'required_pin_to_start_trip' => (bool)$info->firstWhere('key_name', 'required_pin_to_start_trip')?->value ?? false,
            'add_intermediate_points' => (bool)$info->firstWhere('key_name', 'add_intermediate_points')?->value ?? false,
            'business_name' => (string)$info->firstWhere('key_name', 'business_name')?->value ?? null,
            'logo' => $info->firstWhere('key_name', 'header_logo')->value ?? null,
            'bid_on_fare' => (bool)$info->firstWhere('key_name', 'bid_on_fare')?->value ?? 0,
            'country_code' => (string)$info->firstWhere('key_name', 'country_code')?->value ?? null,
            'business_address' => (string)$info->firstWhere('key_name', 'business_address')?->value ?? null,
            'business_contact_phone' => (string)$info->firstWhere('key_name', 'business_contact_phone')?->value ?? null,
            'business_contact_email' => (string)$info->firstWhere('key_name', 'business_contact_email')?->value ?? null,
            'business_support_phone' => (string)$info->firstWhere('key_name', 'business_support_phone')?->value ?? null,
            'business_support_email' => (string)$info->firstWhere('key_name', 'business_support_email')?->value ?? null,
            'conversion_status' => (bool)($loyaltyPoints['status'] ?? false),
            'conversion_rate' => (double)($loyaltyPoints['points'] ?? 0),
            'websocket_url' => $info->firstWhere('key_name', 'websocket_url')?->value ?? null,
            'websocket_port' => (string)$info->firstWhere('key_name', 'websocket_port')?->value ?? 6001,
            'websocket_key' => env('PUSHER_APP_KEY'),
            'websocket_scheme' => env('PUSHER_SCHEME'),
            'base_url' => url('/') . '/api/v1/',
            'review_status' => (bool)$info->firstWhere('key_name', CUSTOMER_REVIEW)?->value ?? null,
            'level_status' => (bool)$info->firstWhere('key_name', CUSTOMER_LEVEL)?->value ?? null,
            'search_radius' => $info->firstWhere('key_name', 'search_radius')?->value ?? 10000,
            'popular_tips' => $this->tripRequestService->getPopularTips()?->tips ?? 5,
            'driver_completion_radius' => $info->firstWhere('key_name', 'driver_completion_radius')?->value ?? 1000,
            'image_base_url' => [
                'profile_image_driver' => dynamicStorage('storage/app/public/driver/profile'),
                'profile_image_admin' => dynamicStorage('storage/app/public/employee/profile'),
                'banner' => dynamicStorage('storage/app/public/promotion/banner'),
                'vehicle_category' => dynamicStorage('storage/app/public/vehicle/category'),
                'vehicle_model' => dynamicStorage('storage/app/public/vehicle/model'),
                'vehicle_brand' => dynamicStorage('storage/app/public/vehicle/brand'),
                'profile_image' => dynamicStorage('storage/app/public/customer/profile'),
                'identity_image' => dynamicStorage('storage/app/public/customer/identity'),
                'documents' => dynamicStorage('storage/app/public/customer/document'),
                'level' => dynamicStorage('storage/app/public/customer/level'),
                'pages' => dynamicStorage('storage/app/public/business/pages'),
                'conversation' => dynamicStorage('storage/app/public/conversation'),
                'parcel' => dynamicStorage('storage/app/public/parcel/category'),
                'payment_method' => dynamicStorage('storage/app/public/payment_modules/gateway_image')
            ],
            'currency_decimal_point' => $info->firstWhere('key_name', 'currency_decimal_point')?->value ?? null,
            'trip_request_active_time' => (int)$info->firstWhere('key_name', 'trip_request_active_time')?->value ?? 10,
            'currency_code' => $info->firstWhere('key_name', 'currency_code')?->value ?? null,
            'currency_symbol' => $info->firstWhere('key_name', 'currency_symbol')?->value ?? '$',
            'currency_symbol_position' => $info->firstWhere('key_name', 'currency_symbol_position')?->value ?? null,
            'about_us' => $info->firstWhere('key_name', 'about_us')?->value,
            'privacy_policy' => $info->firstWhere('key_name', 'privacy_policy')?->value,
            'refund_policy' => $info->firstWhere('key_name', 'refund_policy')?->value,
            'terms_and_conditions' => $info->firstWhere('key_name', 'terms_and_conditions')?->value,
            'legal' => $info->firstWhere('key_name', 'legal')?->value,
            'verification' => (bool)$info->firstWhere('key_name', 'customer_verification')?->value ?? 0,
            'sms_verification' => (bool)$info->firstWhere('key_name', 'sms_verification')?->value ?? 0,
            'email_verification' => (bool)$info->firstWhere('key_name', 'email_verification')?->value ?? 0,
            'facebook_login' => (bool)$info->firstWhere('key_name', 'facebook_login')?->value['status'] ?? 0,
            'google_login' => (bool)$info->firstWhere('key_name', 'google_login')?->value['status'] ?? 0,
            'otp_resend_time' => (int)($info->firstWhere('key_name', 'otp_resend_time')?->value ?? 60),
            'vat_tax' => (double)get_cache('vat_percent') ?? 1,
            'payment_gateways' => collect($this->getPaymentMethods()),
            'referral_earning_status' => (bool)referralEarningSetting('referral_earning_status', CUSTOMER)?->value,
            'external_system' => $martExternalSetting,
            'mart_business_name' => $martExternalSetting ? externalConfig('mart_business_name')?->value ?? "6amMart" : "",
            'mart_app_url_android' => $martExternalSetting ? externalConfig('mart_app_url_android')?->value : "",
            'mart_app_minimum_version_android' => $martExternalSetting ? externalConfig('mart_app_minimum_version_android')?->value : null,
            'mart_app_url_ios' => $martExternalSetting ? externalConfig('mart_app_url_ios')?->value : "",
            'mart_app_minimum_version_ios' => $martExternalSetting ? externalConfig('mart_app_minimum_version_ios')?->value : null,
            'app_minimum_version_for_android' => (double)$appVersions->firstWhere('key_name', 'customer_app_version_control_for_android')?->value['minimum_app_version'] ?? 0,
            'app_url_for_android' => $appVersions->firstWhere('key_name', 'customer_app_version_control_for_android')?->value['app_url'] ?? null,
            'app_minimum_version_for_ios' => (double)$appVersions->firstWhere('key_name', 'customer_app_version_control_for_ios')?->value['minimum_app_version'] ?? 0,
            'app_url_for_ios' => $appVersions->firstWhere('key_name', 'customer_app_version_control_for_ios')?->value['app_url'] ?? null,
            'parcel_refund_status' => (bool)$info->firstWhere('key_name', 'parcel_refund_status')?->value ?? false,
            'parcel_refund_validity' => (int)$info->firstWhere('key_name', 'parcel_refund_validity')?->value ?? 0,
            'parcel_refund_validity_type' => $info->firstWhere('key_name', 'parcel_refund_validity_type')?->value ?? 'day',
            'firebase_otp_verification' => (bool)$info->firstWhere('key_name', 'firebase_otp_verification_status')?->value == 1,
            'sms_gateway' => (bool)$smsConfiguration,
            'zone_extra_fare' => $zoneExtraFare,
            'maximum_parcel_weight_status' => (bool)$info->firstWhere('key_name', 'max_parcel_weight_status')?->value == 1,
            'maximum_parcel_weight_capacity' => $info->firstWhere('key_name', 'max_parcel_weight_status')?->value == 1 ? (double)$info->firstWhere('key_name', 'max_parcel_weight')?->value : null,
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

            'safety_feature_emergency_govt_number' => $info->firstWhere('key_name', 'emergency_number_for_call_status')?->value == 1 ? $info->firstWhere('key_name', 'emergency_govt_number_for_call')?->value : null,
            'otp_confirmation_for_trip' => (bool)$info->firstWhere('key_name', 'driver_otp_confirmation_for_trip')?->value == 1,
            'schedule_trip_status' => (bool)$info->firstWhere('key_name', 'schedule_trip_status')?->value == 1,
            'minimum_schedule_book_time' => $info->firstWhere('key_name', 'schedule_trip_status')?->value ? convertTimeToSecond(
                (int)$info->firstWhere('key_name', 'minimum_schedule_book_time')?->value ?? 0,
                (string)$info->firstWhere('key_name', 'minimum_schedule_book_time_type')?->value ?? 'minute'
            ) : null,
            'minimum_schedule_book_time_type' => $info->firstWhere('key_name', 'minimum_schedule_book_time_type')?->value ?? 'minute',
            'advance_schedule_book_time' =>
                $info->firstWhere('key_name', 'schedule_trip_status')?->value ? convertTimeToSecond(
                    (int)$info->firstWhere('key_name', 'advance_schedule_book_time')?->value ?? 0,
                    (string)$info->firstWhere('key_name', 'advance_schedule_book_time_type')?->value ?? 'minute'
                ) : null,
            'advance_schedule_book_time_type' => $info->firstWhere('key_name', 'advance_schedule_book_time_type')?->value ?? 'minute',
            'wallet_add_fund_status' => (bool)$info->firstWhere('key_name', 'customer_wallet')?->value['add_fund_status'] ?? false,
            'wallet_minimum_deposit_limit' => $info->firstWhere('key_name', 'customer_wallet')?->value['min_deposit_limit'] ?? 1,
            'do_not_charge_customer_return_fee' => (bool)businessConfig('do_not_charge_customer_return_fee', PARCEL_SETTINGS)?->value ?? true,
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_image_size' => maxUploadSize('image'),
            'upload_max_file_size' => maxUploadSize('file'),
            'is_otp_enabled' => isOtpEnabled(),
            'customer_login_options' => $info->firstWhere(key: 'key_name', operator: 'customer_login_options')?->value ?? ['manual_login' => 1, 'otp_login' => 0],
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

    public function pages($page_name)
    {
        $validated = in_array($page_name, ['about_us', 'privacy_and_policy', 'terms_and_conditions', 'legal']);

        if (!$validated) {
            return response()->json(responseFormatter(DEFAULT_400), 400);
        }

        $data = businessConfig(key: $page_name, settingsType: PAGES_SETTINGS);
        return response(responseFormatter(DEFAULT_200, [$data]));

    }

    public function placeApiAutocomplete(Request $request): JsonResponse
    {
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

    public function placeApiDetails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'placeid' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, null, null, null, errorProcessor($validator)), 400);
        }
        $mapApiKey = businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;
        // API Headers
        $headers = [
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $mapApiKey,
            'X-Goog-FieldMask' => '*'
        ];
        $response = Http::withHeaders($headers)->get('https://places.googleapis.com/v1/places/' . $request['placeid']);

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

    #
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
            $trip->driver?->lastLocations->latitude,
            $trip->driver?->lastLocations->longitude,
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

        return getRoutes(
            originCoordinates: $pickupCoordinates,
            destinationCoordinates: $destinationCoordinates,
            intermediateCoordinates: $intermediateCoordinates,
        ); //["DRIVE", "TWO_WHEELER"]

        $result = [];
        foreach ($getRoutes as $route) {
            if ($route['drive_mode'] == $drivingMode) {
                $result['is_picked'] = $trip->current_status == ONGOING;
                return [array_merge($result, $route)];
            }
        }

    }

    #
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
//        $response = Http::get(MAP_API_BASE_URI . '/geocode/json?latlng=' . $request->lat . ',' . $request->lng . '&key=' . $mapApiKey . '&components=country:IN');

        $response = Http::get(MAP_API_BASE_URI . '/geocode/json?latlng=' . $request->lat . ',' . $request->lng . '&key=' . $mapApiKey);
        return response()->json(responseFormatter(DEFAULT_200, $response->json()), 200);
    }

    #
    public function userLastLocation(UserLocationStore $request)
    {

        if (empty($request->header('zoneId'))) {

            return response()->json(responseFormatter(ZONE_404), 200);
        }

        $zone_id = $request->header('zoneId');
        $user = auth('api')->user();
        $request->merge([
            'user_id' => $user->id,
            'type' => $user->user_type,
            'zone_id' => $zone_id,
        ]);
        $userLastLocation = $this->userLastLocationService->findOneBy(criteria: ['user_id' => $user->id]);
        if ($userLastLocation) {
            return $this->userLastLocationService->update(id: $userLastLocation->id, data: $request->all());
        }
        return $this->userLastLocationService->create(data: $request->all());
    }

    #
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

    #
    public function cancellationReasonList()
    {
        $ongoingRide = $this->cancellationReasonService->getBy(criteria: ['cancellation_type' => 'ongoing_ride', 'user_type' => 'customer', 'is_active' => 1])->pluck('title')->toArray();
        $acceptedRide = $this->cancellationReasonService->getBy(criteria: ['cancellation_type' => 'accepted_ride', 'user_type' => 'customer', 'is_active' => 1])->pluck('title')->toArray();
        $data = [
            'ongoing_ride' => $ongoingRide,
            'accepted_ride' => $acceptedRide,
        ];
        return response(responseFormatter(DEFAULT_200, $data));
    }

    public function parcelCancellationReasonList()
    {
        $ongoingRide = $this->parcelCancellationReasonService->getBy(criteria: ['cancellation_type' => 'ongoing_ride', 'user_type' => 'customer', 'is_active' => 1])->pluck('title')->toArray();
        $acceptedRide = $this->parcelCancellationReasonService->getBy(criteria: ['cancellation_type' => 'accepted_ride', 'user_type' => 'customer', 'is_active' => 1])->pluck('title')->toArray();
        $data = [
            'ongoing_ride' => $ongoingRide,
            'accepted_ride' => $acceptedRide,
        ];
        return response(responseFormatter(DEFAULT_200, $data));
    }

    public function parcelRefundReasonList()
    {
        $parcelRefundReasonList = $this->parcelRefundReasonService->getBy(criteria: ['is_active' => 1])->pluck('title')->toArray();
        return response(responseFormatter(DEFAULT_200, $parcelRefundReasonList));
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
            'reason_for_whom' => CUSTOMER
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
            ['for_whom', 'like', '%' . CUSTOMER . '%'],
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

    public function calculateDistance(Request $request)
    {
        $trip = $this->tripRequestService->findOneBy(criteria: [
            'id' => $request->trip_request_id,
        ]);
        $destParts = explode(',', $trip?->coordinate?->destination_coordinates ?? '0,0');

        $data = [];
        $data['from_longitude'] = (float)$request->driver_last_longitude;
        $data['from_latitude'] = (float)$request->driver_last_latitude;
        $data['to_longitude'] = (float)($destParts[1] ?? 0);
        $data['to_latitude'] = (float)($destParts[0] ?? 0);
        $distanceToReach = number_format(distanceCalculator($data) * 1.3, 2);

        return response()->json(['distance' => $distanceToReach], 200);
    }


}
