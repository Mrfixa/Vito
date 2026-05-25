<?php

namespace Modules\UserManagement\Http\Controllers\Api\Driver;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\BusinessManagement\Service\Interfaces\SettingServiceInterface;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Traits\Payment;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Http\Requests\UserProfileUpdateApiRequest;
use Modules\UserManagement\Service\Interfaces\DriverDetailServiceInterface;
use Modules\UserManagement\Service\Interfaces\DriverServiceInterface;
use Modules\UserManagement\Service\Interfaces\DriverTimeLogServiceInterface;
use Modules\UserManagement\Transformers\DriverResource;
use Modules\UserManagement\Transformers\DriverTimeLogResource;

class DriverController extends Controller
{
    use Payment;

    protected $driverService;
    protected $driverDetailService;
    protected $driverTimeLogService;
    protected $tripRequestService;
    protected $settingsService;

    public function __construct(DriverServiceInterface        $driverService, DriverDetailServiceInterface $driverDetailService,
                                DriverTimeLogServiceInterface $driverTimeLogService, TripRequestServiceInterface $tripRequestService,
                                SettingServiceInterface       $settingService)
    {
        $this->driverService = $driverService;
        $this->driverDetailService = $driverDetailService;
        $this->driverTimeLogService = $driverTimeLogService;
        $this->tripRequestService = $tripRequestService;
        $this->settingsService = $settingService;
    }

    public function profileInfo(Request $request): JsonResponse
    {
        if ($request->user()->user_type == DRIVER) {

            $relations = [
                'level', 'vehicle', 'vehicle.brand', 'vehicle.model', 'vehicle.category', 'driverDetails', 'userAccount', 'latestTrack', 'receivedReviews', 'driverIdentityVerification'];
            $withAvgRelations = [
                ['receivedReviews', 'rating']
            ];
            $withCountQuery = [
                'receivedReviews' => [],
            ];

            $driver = $this->driverService->findOneBy(criteria: ['id' => auth()->user()->id], withAvgRelations: $withAvgRelations, relations: $relations, withCountQuery: $withCountQuery);
            $driver = DriverResource::make($driver);

            return response()->json(responseFormatter(DEFAULT_200, $driver), 200);
        }
        return response()->json(responseFormatter(DEFAULT_401), 401);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(UserProfileUpdateApiRequest $request): JsonResponse
    {
        $this->driverService->update(id: $request->user()->id, data: $request->all());

        return response()->json(responseFormatter(DEFAULT_UPDATE_200), 200);
    }

    /**
     * @return JsonResponse
     */
    public function onlineStatus(): JsonResponse
    {
        $driver = auth()->user();
        $details = $this->driverDetailService->findOneBy(criteria: ['user_id' => $driver->id]);
        $attributes = [
            'column' => 'user_id',
            'is_online' => $details['is_online'] == 1 ? 0 : 1,
            'availability_status' => $details['is_online'] == 1 ? 'unavailable' : 'available',
        ];
        $this->driverService->update(data: $attributes, id: $driver->id);
        // Time log set into driver details
//        $this->details->setTimeLog(
//            driver_id:$driver->id,
//            date:date('Y-m-d'),
//            online:($details->is_online == 1 ? now() : null),
//            offline:($details->is_online == 1 ? null : now()),
//            activeLog:true
//        );

        return response()->json(responseFormatter(DEFAULT_STATUS_UPDATE_200));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function myActivity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required_with:from|date',
            'from' => 'required_with:to|date',
            'limit' => 'required|numeric',
            'offset' => 'required|numeric'
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $user = auth()->user();
        $attributes = [
            'driver_id' => $user->id,
        ];

        $whereBetweenCriteria = [];
        if ($request['to']) {
            $from = Carbon::parse($request['from'])->startOfDay();
            $to = Carbon::parse($request['to'])->endOfDay();
            $whereBetweenCriteria = [
                'created_at' => [$from, $to],
            ];
        }

        $data = $this->driverTimeLogService->getBy(criteria: $attributes, whereBetweenCriteria: $whereBetweenCriteria, limit: $request['limit'], offset: $request['offset']);
        $activity = DriverTimeLogResource::collection($data);
        return response()->json(responseFormatter(DEFAULT_200, $activity, $request['limit'], $request['offset']), 200);

    }

    public function changeLanguage(Request $request): JsonResponse
    {
        if (auth('api')->user()) {
            $this->driverService->changeLanguage(id: auth('api')->user()->id, data: [
                'current_language_key' => $request->header('X-localization') ?? 'en'
            ]);
            return response()->json(responseFormatter(DEFAULT_200), 200);
        }
        return response()->json(responseFormatter(DEFAULT_404), 200);
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|integer',
            'offset' => 'required|integer',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $criteria = [
            ['driver_id', '!=', null],
            'driver_id' => auth()->user()->id,
            'payment_status' => PAID,
        ];
        $incomeStatements = $this->tripRequestService->getBy(criteria: $criteria, limit: $request->limit, offset: $request->offset, orderBy: ['updated_at' => 'desc']);
        $incomeStatements = TripRequestResource::collection($incomeStatements);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $incomeStatements, limit: $request->limit, offset: $request->offset));
    }

    public function referralDetails(Request $request): JsonResponse
    {
        if ($request->user()->user_type == DRIVER) {
            $useCodeEarning = referralEarningSetting('use_code_earning', DRIVER)?->value;
            $data = [
                'referral_code' => auth()->user()->ref_code,
                'share_code_earning' => (double)referralEarningSetting('share_code_earning', DRIVER)?->value,
                'use_code_earning' => (double)referralEarningSetting('use_code_earning', DRIVER)?->value,
            ];
            return response()->json(responseFormatter(DEFAULT_200, $data), 200);

        }
        return response()->json(responseFormatter(DEFAULT_401), 401);
    }

    public function payDigitally(Request $request)
    {
        $minimumToPay = businessConfig('min_amount_to_pay')?->value ?? 1;
        $driver = $this->driverService->findOneBy(criteria: ['id' => $request->user_id], relations: ['userAccount']);
        $maximumToPay = $driver?->userAccount->payable_balance > $driver?->userAccount->receivable_balance ? ($driver?->userAccount->payable_balance - $driver?->userAccount->receivable_balance) : 1;
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
            'amount' => 'required|numeric|gte:' . $minimumToPay . '|lte:' . $maximumToPay,
            'payment_method' => 'required|in:ssl_commerz,stripe,paypal,razor_pay,paystack,senang_pay,paymob_accept,flutterwave,paytm,paytabs,liqpay,mercadopago,bkash,fatoorah,xendit,amazon_pay,iyzi_pay,hyper_pay,foloosi,ccavenue,pvit,moncash,thawani,tap,viva_wallet,hubtel,maxicash,esewa,swish,momo,payfast,worldpay,sixcash,ssl_commerz,stripe,paypal,razor_pay,paystack,senang_pay,paymob_accept,flutterwave,paytm,paytabs,liqpay,mercadopago,bkash,fatoorah,xendit,amazon_pay,iyzi_pay,hyper_pay,foloosi,ccavenue,pvit,moncash,thawani,tap,viva_wallet,hubtel,maxicash,esewa,swish,momo,payfast,worldpay,sixcash'
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $activePaymentGateway = $this->settingsService->findOneBy(criteria: ['key_name' => $request->payment_method, 'is_active' => 1]);

        if (empty($activePaymentGateway))
        {
            return redirect()->route('gateway-inactive');
        }

        $driver = $this->driverService->findOneBy(criteria: ['id' => $request->user_id], relations: ['userAccount']);
        $payer = new Payer(name: $driver?->first_name, email: $driver?->email, phone: $driver?->phone, address: '');

        $paymentInfo = new PaymentInfo(
            hook: 'driverDigitalPay',
            currencyCode: businessConfig('currency_code')?->value ?? 'USD',
            paymentMethod: $request->payment_method,
            paymentPlatform: 'mono',
            payerId: $driver->id,
            receiverId: '100',
            additionalData: [],
            paymentAmount: $request->amount,
            externalRedirectLink: null,
            attribute: 'pay_to_admin_digitally',
            attributeId: $driver?->userAccount->id
        );

        $receiverInfo = new Receiver('receiver_name', 'example.png');
        $redirectLink = $this->generate_link($payer, $paymentInfo, $receiverInfo);

        return redirect($redirectLink);
    }
}
