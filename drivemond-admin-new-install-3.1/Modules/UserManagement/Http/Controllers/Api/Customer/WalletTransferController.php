<?php

namespace Modules\UserManagement\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Service\Interfaces\CustomerAccountServiceInterface;
use Modules\UserManagement\Service\Interfaces\CustomerServiceInterface;

class WalletTransferController extends Controller
{
    protected $customerService;
    protected $customerAccountService;

    public function __construct(CustomerServiceInterface $customerService, CustomerAccountServiceInterface $customerAccountService)
    {
        $this->customerService = $customerService;
        $this->customerAccountService = $customerAccountService;
    }

    public function transferDrivemondToMartWallet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $customer = Auth::user();
        if ($customer && $customer?->userAccount?->wallet_balance < $request->amount) {
            return response()->json(responseFormatter(constant: INSUFFICIENT_FUND_403), 403);
        }
        if (checkSelfExternalConfiguration()) {
            $martBaseUrl = externalConfig('mart_base_url')?->value;
            $systemSelfToken = externalConfig('system_self_token')?->value;
            $martToken = externalConfig('mart_token')?->value;
            try {
                $response = Http::post($martBaseUrl . '/api/v1/customer/wallet/transfer-mart-from-vito',
                    [
                        'bearer_token' => $request->bearerToken(),
                        'currency' => businessConfig('currency_code')?->value??"USD",
                        'amount' => $request->amount,
                        'token' => $martToken,
                        'external_base_url' => url('/'),
                        'external_token' => $systemSelfToken,
                    ]);
                if ($response->successful()) {
                    $martCustomerResponse = $response->json();
                    if ($martCustomerResponse['status']) {
                        $martCustomer = $martCustomerResponse['data'];
                        $customer = $this->customerService->findOneBy(criteria: ['phone' => $martCustomer['phone'], 'user_type' => CUSTOMER]);
                        if ($customer && $customer->userAccount) {
                            $this->customerService->walletTransfer(customer: $customer, data: [
                                'amount' => $request->amount,
                                'type' => 'debit'
                            ]);
                            $customer = $this->customerService->findOneBy(criteria: ['phone' => $martCustomer['phone'], 'user_type' => CUSTOMER]);
                            return response()->json(responseFormatter(constant: FUND_TRANSFER_200,content: $customer));
                        }
                    }
                    $martCustomer = $martCustomerResponse['errors'];
                    if ($martCustomer['error_code'] == 405) {
                        return response()->json(responseFormatter([
                            'response_code' => 'currency_not_match_403',
                            'message' => 'Currency not matched, Please contact support',
                        ]), 403);

                    }
                }else{
                    return response()->json(responseFormatter([
                        'response_code' => 'account_not_found_403',
                        'message' => 'mart account not found',
                    ]), 403);
                }
            }catch (\Exception $exception){

            }


        }
        return response()->json(responseFormatter([
            'response_code' => 'account_not_found_403',
            'message' => 'mart account not found',
        ]), 403);

    }

    public function transferDrivemondFromMartWallet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required',
            'amount' => 'required',
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
        if (strcasecmp(str_replace('"','',$request->currency), str_replace('"', '', businessConfig('currency_code')?->value??"USD")) !== 0) {
            $data = [
                'status' => false,
                'errors' => ['error_code' => 405, 'message' => "Currency not matched, Please contact support"],
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
                        if ($customer && $customer->userAccount) {
                            $this->customerService->walletTransfer(customer: $customer, data: [
                                'amount' => $request->amount,
                                'type' => 'credit'
                            ]);
                            sendDeviceNotification(
                                fcm_token: $customer?->fcm_token,
                                title:translate("wallet_transfer_vito_from_mart") ,
                                description: translate("you_transfer_your_wallet_balance_vito_from_mart"),
                                status: 1
                            );
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
            }catch (\Exception $e) {

            }
        }
        $data = [
            'status' => false,
            'data' => ['error_code' => 402, 'message' => "Invalid token"]
        ];
        return response()->json($data);
    }
}
