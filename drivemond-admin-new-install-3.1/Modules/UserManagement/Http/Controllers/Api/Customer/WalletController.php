<?php

namespace Modules\UserManagement\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\BusinessManagement\Service\Interfaces\SettingServiceInterface;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Traits\Payment;
use Modules\TransactionManagement\Entities\Transaction;
use Modules\UserManagement\Entities\UserAccount;
use Modules\UserManagement\Service\Interfaces\UserServiceInterface;
use Modules\UserManagement\Service\Interfaces\WalletBonusServiceInterface;
use Modules\UserManagement\Transformers\WalletBonusResource;

class WalletController extends Controller
{
    use Payment;
    protected $walletBonusService;
    protected $customerService;
    protected $settingsService;
    public function __construct(
        WalletBonusServiceInterface $walletBonusService,
        UserServiceInterface $customerService,
        SettingServiceInterface $settingsService
    )
    {
        $this->walletBonusService = $walletBonusService;
        $this->customerService = $customerService;
        $this->settingsService = $settingsService;
    }
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = UserAccount::where('user_id', $user->id)->first();
        $limit = min($request->input('limit', 20), 100);
        $offset = (int) ($request->input('offset', 0));

        $transactions = Transaction::where('user_id', $user->id)
            ->where('account', 'wallet')
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json(responseFormatter(constant: DEFAULT_200, content: [
            'balance'      => (float) ($account?->wallet_balance ?? 0),
            'transactions' => $transactions,
        ]));
    }

    /**
     * Display a listing of the resource.
     */
    public function bonusList(Request $request)
    {
        $data = [
            'is_active' => 1,
            'date' => Carbon::today()->toDateString(),
            'user_type' => CUSTOMER
        ];

        $walletBonusList = $this->walletBonusService->getListForAPI(data: $data, limit: $request->limit, offset: $request->offset);
        $data = WalletBonusResource::collection($walletBonusList);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $data, limit: $request->limit, offset: $request->offset));
    }

    public function addFundDigitally(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
            'amount' => 'required|numeric|gt:0',
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

        $customer = $this->customerService->findOneBy(criteria: ['id' => $request->user_id], relations: ['userAccount']);
        $payer = new Payer(name: $customer?->first_name, email: $customer?->email, phone: $customer?->phone, address: '');
        $paymentInfo = new PaymentInfo(
            hook: 'customerWalletUpdate',
            currencyCode: businessConfig('currency_code')?->value ?? 'USD',
            paymentMethod: $request->payment_method,
            paymentPlatform: 'mono',
            payerId: $customer->id,
            receiverId: '100',
            additionalData: [],
            paymentAmount: $request->amount,
            externalRedirectLink: null,
            attribute: 'add_wallet_amount_digitally',
            attributeId: $customer?->userAccount->id
        );
        $receiverInfo = new Receiver('receiver_name', 'example.png');
        $redirectLink = $this->generate_link($payer, $paymentInfo, $receiverInfo);

        return redirect($redirectLink);
    }
}
