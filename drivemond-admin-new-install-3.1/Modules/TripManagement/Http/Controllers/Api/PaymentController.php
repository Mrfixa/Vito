<?php

namespace Modules\TripManagement\Http\Controllers\Api;

use App\Events\CustomerTripPaymentSuccessfulEvent;
use App\Events\DriverPaymentReceivedEvent;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Traits\Payment;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;
use Modules\UserManagement\Lib\LevelUpdateCheckerTrait;


class PaymentController extends Controller
{
    use TransactionTrait, Payment, LevelHistoryManagerTrait, LevelUpdateCheckerTrait;

    protected $tripRequestservice;


    public function __construct(
        TripRequestServiceInterface $tripRequestservice,


    )
    {
        $this->tripRequestservice = $tripRequestservice;
    }

    public function payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'payment_method' => 'required|in:wallet,cash'
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $trip = $this->tripRequestservice->findOne(id: $request->trip_request_id, relations: ['customer.userAccount', 'driver.userAccount', 'fee', 'driver.driverDetails']);
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 404);
        }
        if ($trip->payment_status == PAID) {

            return response()->json(responseFormatter(DEFAULT_PAID_200));
        }

        $tips = 0;
        DB::beginTransaction();
        if (!is_null($request->tips) && $request->payment_method == 'wallet') {
            $tips = $request->tips;
        }
        $feeAttributes['tips'] = $tips;

        $data = [
            'tips' => $tips,
            'payment_method' => $request->payment_method,
            'paid_fare' => $trip->paid_fare + $tips,
            'payment_status' => PAID
        ];
        $trip->fee()->update($feeAttributes);
        $trip = $this->tripRequestservice->update(id: $request->trip_request_id, data: $data);
        if ($request->payment_method == 'wallet') {
            if ($trip->customer->userAccount->wallet_balance < ($trip->paid_fare)) {

                return response()->json(responseFormatter(INSUFFICIENT_FUND_403), 403);
            }
            $method = '_with_wallet_balance';
            $this->walletTransaction($trip);
        } // driver only make cash payment
        elseif ($request->payment_method == 'cash') {
            $method = '_by_cash';
            $this->cashTransaction($trip);
        }

        $this->customerLevelUpdateChecker($trip->customer);
        DB::commit();
        $tripType = ($trip->ride_request_type ?? 'regular') === 'regular' ? 'regular_trip' : 'schedule_trip';
        $push = getNotification('payment_successful', type: $tripType);
        sendDeviceNotification(
            fcm_token: auth('api')->user()->user_type == 'customer' ? $trip->driver->fcm_token : $trip->customer->fcm_token,
            title: translate(key: $push['title'], locale: $trip?->driver?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, paidAmount: getCurrencyFormat($trip->paid_fare), methodName: translate(key: $method, locale: $trip?->driver?->current_language_key), locale: $trip?->driver?->current_language_key),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            notification_type: $trip->type == 'parcel' ? 'parcel' : 'trip',
            action: $push['action'],
            user_id: $trip->driver->id
        );

        $pushTips = getNotification("tips_from_customer");
        if ($trip->tips > 0) {
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate(key: $pushTips['title'], locale: $trip->driver->current_language_key),
                description: textVariableDataFormat(value: $pushTips['description'], tipsAmount: getCurrencyFormat($trip->tips), tripId: $trip->ref_id, customerName: $trip->customer->first_name . ' ' . $trip->customer->last_name, locale: $trip->driver->current_language_key),
                status: $push['status'],
                ride_request_id: $trip->id,
                notification_type: 'trip',
                action: $push['action'],
                user_id: $trip->driver->id,
            );
        }

        $maximumAmountToHoldCash = businessConfig('cash_in_hand_setup_status')?->value && businessConfig('max_amount_to_hold_cash')?->value ? businessConfig('max_amount_to_hold_cash')?->value : null;
        $payableBalance = $trip?->driver?->userAccount->payable_balance > $trip?->driver?->userAccount->receivable_balance ? ($trip?->driver?->userAccount->payable_balance - $trip?->driver?->userAccount->receivable_balance) : 0;
        if ($maximumAmountToHoldCash && $payableBalance >= $maximumAmountToHoldCash)
        {
            $trip->driver->driverDetails->update(['is_suspended' => 1, 'suspend_reason' => SuspendReasonEnum::CASH_IN_HAND_LIMIT->value]);
            $cashInHandLimitExceeds = getNotification('cash_in_hand_limit_exceeds');
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate(key: $cashInHandLimitExceeds['title'], locale: $trip->driver->current_language_key),
                description: textVariableDataFormat(value: $cashInHandLimitExceeds['description'], driverName: $trip->customer->first_name . ' ' . $trip->customer->last_name, locale: $trip->driver->current_language_key),
                status: $cashInHandLimitExceeds['status'],
                ride_request_id: $trip?->driver->id,
                notification_type: '',
                action: $cashInHandLimitExceeds['action'],
                user_id: $trip?->driver->id,
            );
        }

        if (checkReverbConnection()) {
            try {
                DriverPaymentReceivedEvent::broadcast($trip);
                CustomerTripPaymentSuccessfulEvent::broadcast($trip);
            } catch (Exception $exception) {

            }
        }


        return response()->json(responseFormatter(DEFAULT_UPDATE_200));
    }


    public function digitalPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'payment_method' => 'required|in:ssl_commerz,stripe,paypal,razor_pay,paystack,senang_pay,paymob_accept,flutterwave,paytm,paytabs,liqpay,mercadopago,bkash,fatoorah,xendit,amazon_pay,iyzi_pay,hyper_pay,foloosi,ccavenue,pvit,moncash,thawani,tap,viva_wallet,hubtel,maxicash,esewa,swish,momo,payfast,worldpay,sixcash,ssl_commerz,stripe,paypal,razor_pay,paystack,senang_pay,paymob_accept,flutterwave,paytm,paytabs,liqpay,mercadopago,bkash,fatoorah,xendit,amazon_pay,iyzi_pay,hyper_pay,foloosi,ccavenue,pvit,moncash,thawani,tap,viva_wallet,hubtel,maxicash,esewa,swish,momo,payfast,worldpay,sixcash'
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $trip = $this->tripRequestservice->findOne(id: $request->trip_request_id, relations: ['customer.userAccount', 'fee', 'time', 'driver']);
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 404);
        }
        if ($trip->payment_status == PAID) {

            return response()->json(responseFormatter(DEFAULT_PAID_200));
        }
        $tips = $request->tips;
        $feeAttributes['tips'] = $tips;

        $trip->fee()->update($feeAttributes);

        $data = [
            'tips' => $tips,
            'payment_method' => $request->payment_method,
        ];


        $trip = $this->tripRequestservice->update(id: $request->trip_request_id, data: $data);
        $paymentAmount = $trip->paid_fare + $tips;
        $customer = $trip->customer;
        $payer = new Payer(
            name: $customer?->first_name,
            email: $customer->email,
            phone: $customer->phone,
            address: ''
        );

        $businessLogo = dynamicStorage('storage/app/public/business') . '/' . businessConfig('header_logo')?->value ?? '';
        $businessName = businessConfig('business_name')?->value;

        $additionalData = [
            'business_name' => $businessName,
            'business_logo' => $businessLogo
        ];

        //hook is look for a autoloaded function to perform action after payment
        $paymentInfo = new PaymentInfo(
            hook: 'tripRequestUpdate',
            currencyCode: businessConfig('currency_code')?->value ?? 'USD',
            paymentMethod: $request->payment_method,
            paymentPlatform: 'mono',
            payerId: $customer->id,
            receiverId: '100',
            additionalData: $additionalData,
            paymentAmount: $paymentAmount,
            externalRedirectLink: null,
            attribute: 'order',
            attributeId: $request->trip_request_id
        );
        $receiverInfo = new Receiver('receiver_name', 'example.png');
        $redirectLink = $this->generate_link($payer, $paymentInfo, $receiverInfo);

        return redirect($redirectLink);
    }
}
