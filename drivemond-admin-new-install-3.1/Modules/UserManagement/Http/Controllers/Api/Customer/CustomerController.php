<?php

namespace Modules\UserManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Modules\PromotionManagement\Service\Interfaces\CouponSetupServiceInterface;
use Modules\UserManagement\Http\Requests\UserProfileUpdateApiRequest;
use Modules\UserManagement\Service\Interfaces\CustomerServiceInterface;
use Modules\UserManagement\Transformers\CustomerResource;

class CustomerController extends Controller
{
    protected $customerService;
    protected $couponSetupService;

    public function __construct(CustomerServiceInterface $customerService, CouponSetupServiceInterface $couponSetupService)
    {
        $this->customerService = $customerService;
        $this->couponSetupService = $couponSetupService;
    }

    public function profileInfo(Request $request): JsonResponse
    {
        if ($request->user()->user_type == CUSTOMER) {
            $withAvgRelations = [['receivedReviews', 'rating']];
            $customer = $this->customerService->findOne(id: auth()->id(), withAvgRelations: $withAvgRelations, relations: ['userAccount', 'level'], withCountQuery: ['customerTrips' => []]);
            $customer = new CustomerResource($customer);
            return response()->json(responseFormatter(DEFAULT_200, $customer), 200);
        }
        return response()->json(responseFormatter(DEFAULT_401), 401);
    }

    public function updateProfile(UserProfileUpdateApiRequest $request): JsonResponse
    {
        $this->customerService->update(id: $request->user()->id, data: $request->validated());
        return response()->json(responseFormatter(DEFAULT_UPDATE_200), 200);
    }

    public function applyCoupon(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'coupon_id' => 'required|exists:coupon_setups,id'
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $coupon = $this->couponSetupService->findOne($request->coupon_id);
        if (!$coupon) {
            return response()->json(responseFormatter(constant: COUPON_404), 403);
        }
        $user = auth('api')->user();

        // Check if the user already has an applied coupon
        if ($user->appliedCoupon && $user->appliedCoupon->coupon_setup_id == $coupon?->id) {
            // Remove the previously applied coupon
            $user->appliedCoupon->delete();
            return response()->json(responseFormatter(COUPON_REMOVED_200), 200);
        } else {
            if ($user->appliedCoupon) {
                $user->appliedCoupon->delete();
            }
            $appliedCoupon = $user->appliedCoupon()->create([
                'coupon_setup_id' => $coupon->id
            ]);
            return response()->json(responseFormatter(COUPON_APPLIED_200), 200);
        }
    }

    public function changeLanguage(Request $request): JsonResponse
    {
        if (auth('api')->user()) {
            $this->customerService->changeLanguage(id: auth('api')->user()->id, data: [
                'current_language_key' => $request->header('X-localization') ?? 'en'
            ]);
            return response()->json(responseFormatter(DEFAULT_200), 200);
        }
        return response()->json(responseFormatter(DEFAULT_404), 200);
    }

    public function referralDetails(Request $request): JsonResponse
    {
        if ($request->user()->user_type == CUSTOMER) {
            $useCodeEarning = referralEarningSetting('use_code_earning', CUSTOMER)?->value;
            $data = [
                'referral_code' => auth()->user()->ref_code,
                'share_code_earning' => (double)referralEarningSetting('share_code_earning', CUSTOMER)?->value,
                'first_ride_discount_status' => ($useCodeEarning && $useCodeEarning['first_ride_discount_status']) ? true : false,
                'discount_amount' => ($useCodeEarning && $useCodeEarning['discount_amount']) ? (double)$useCodeEarning['discount_amount'] : 0,
                'discount_amount_type' => ($useCodeEarning && $useCodeEarning['discount_amount_type']) ? $useCodeEarning['discount_amount_type'] : "",
                'discount_validity' => ($useCodeEarning && $useCodeEarning['discount_validity']) ? (int)$useCodeEarning['discount_validity'] : 0,
                'discount_validity_type' => ($useCodeEarning && $useCodeEarning['discount_validity_type']) ? $useCodeEarning['discount_validity_type'] : "",
            ];
            return response()->json(responseFormatter(DEFAULT_200, $data), 200);

        }
        return response()->json(responseFormatter(DEFAULT_401), 401);
    }


    #handshake
    public function getCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'external_base_url' => 'required',
            'external_token' => 'required',
        ]);

        if ($validator->fails()) {
            $data = [
                'status' => false,
                'errors' => errorProcessor($validator),
            ];
            return response()->json(data: $data);
        }
        $customer = $this->customerService->findOne(Auth::id());
        if ($customer && $customer->user_type == DRIVER) {
            $data = [
                'status' => false,
                'data' => ['error_code' => 401, 'message' => translate("Sorry you are a driver, You can not login customer")]
            ];
            return response()->json(data: $data);
        }
        if (checkExternalConfiguration($request->external_base_url, $request->external_token, $request->token)) {
            $customer = Auth::user();

            if (!$customer) {
                $data = [
                    'status' => true,
                    'data' => ['error_code' => 404, 'message' => translate("Customer not found")]
                ];
                return response()->json(data: $data);
            }
            $data = [
                'status' => true,
                'data' => $customer
            ];
            return response()->json(data: $data);
        }
        $data = [
            'status' => false,
            'data' => ['error_code' => 402, 'message' => "Invalid token"]
        ];
        return response()->json($data);

    }

    public function externalUpdateCustomer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bearer_token' => 'required',
            'token' => 'required',
            'external_base_url' => 'required',
            'external_token' => 'required',
        ]);
        if ($validator->fails()) {
            $data = [
                'status' => false,
                'errors' => errorProcessor($validator),
            ];
            return response()->json(data: $data);
        }
        if (checkSelfExternalConfiguration() && checkExternalConfiguration($request->external_base_url, $request->external_token, $request->token)) {
            $martBaseUrl = externalConfig('mart_base_url')?->value;
            $systemSelfToken = externalConfig('system_self_token')?->value;
            $martToken = externalConfig('mart_token')?->value;
            try {
                $response = Http::withToken($request->bearer_token)->post($martBaseUrl . '/api/v1/customer/get-data',
                    [
                        'token' => $martToken,
                        'external_base_url' => url('/'),
                        'external_token' => $systemSelfToken,
                    ]);
                if ($response->successful()) {
                    $martCustomerResponse = $response->json();
                    if ($martCustomerResponse['status']) {
                        $martCustomer = $martCustomerResponse['data'];
                        $customer = $this->customerService->findOneBy(criteria: ['phone' => $martCustomer['phone'], 'user_type' => CUSTOMER]);
                        if ($customer) {
                            $customerData = [
                                'first_name' => $martCustomer['f_name'],
                                'last_name' => $martCustomer['l_name'],
                                'email' => $martCustomer['email'],
                                'phone' => $martCustomer['phone'],
                                'password' => $martCustomer['password'],
                                'phone_verified_at' => $martCustomer['is_phone_verified'] == 1 ? now() : null,
                            ];
                            $customer = $this->customerService->updateExternalCustomer($customer?->id, $customerData);
                            $data = [
                                'status' => true,
                                'data' => $customer
                            ];
                            return response()->json(data: $data);
                        }
                    }

                    $martCustomer = $martCustomerResponse['data'];
                    if ($martCustomer['error_code'] == 402) {
                        return response()->json(responseFormatter([
                            'response_code' => 'mart_external_configuration_402',
                            'message' => 'Mart external authentication failed',
                        ]), 403);
                    }

                } else {
                    $data = [
                        'status' => false,
                        'data' => ['error_code' => 402, 'message' => "Mart user not found"]
                    ];
                    return response()->json($data);
                }
            } catch (\Exception $exception) {

            }
        }
        $data = [
            'status' => false,
            'data' => ['error_code' => 402, 'message' => "Invalid token"]
        ];
        return response()->json($data);
    }
}
