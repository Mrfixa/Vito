<?php

namespace Modules\UserManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\UserManagement\Service\Interfaces\CustomerServiceInterface;
use Modules\UserManagement\Service\Interfaces\LoyaltyPointsHistoryServiceInterface;
use Modules\UserManagement\Transformers\LoyaltyPointsHistoryResource;

class LoyaltyPointController extends Controller
{
    use TransactionTrait;

    protected $customerService;
    protected $loyaltyPointsHistoryService;

    public function __construct(CustomerServiceInterface $customerService, LoyaltyPointsHistoryServiceInterface $loyaltyPointsHistoryService)
    {
        $this->customerService = $customerService;
        $this->loyaltyPointsHistoryService = $loyaltyPointsHistoryService;
    }

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|integer',
            'offset' => 'required|integer',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $criteria =[
            'user_id' => auth('api')->id(),
        ];
        $loyaltyPointsHistory = $this->loyaltyPointsHistoryService->getBy(criteria: $criteria,orderBy:['created_at'=>'desc'] ,limit: $request->get('limit'),offset: $request->get('offset'));
        $history = LoyaltyPointsHistoryResource::collection($loyaltyPointsHistory);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $history, limit: $request->limit, offset: $request->offset));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function convert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'points' => 'required',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $conversion_rate = businessConfig('loyalty_points', 'customer_settings')?->value;
        $user = auth('api')->user();
        $conversionRate = ($conversion_rate['points'] ?? 1);
        if (($conversion_rate['status'] ?? false) && $user->loyalty_points >= $request->points) {
            if ($request->points < $conversionRate) {
                return response()->json(responseFormatter(constant: [
                    'response_code' => ERROR_INSUFFICIENT_POINTS['response_code'],
                    'message' => str_replace(':min_points', $conversionRate, ERROR_INSUFFICIENT_POINTS['message'])
                ]),403);
            }

            DB::beginTransaction();
            $customerData = [
                'loyalty_points' => ($user->loyalty_points - $request->points),
            ];
            $driver = $this->customerService->updateLoyaltyPoint(id: $user->id, data: $customerData);
            $balance = $request->points / ($conversion_rate['points'] ?? 1);
            $account = $this->customerLoyaltyPointsTransaction($driver, $balance);
            $attributes = [
                'user_id' => $user->id,
                'model_id' => $account->id,
                'model' => 'user_account',
                'points' => $request->points,
                'type' => 'debit'
            ];
            $this->loyaltyPointsHistoryService->create(data: $attributes);
            DB::commit();

            return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
        }

        return response()->json(responseFormatter(constant: INSUFFICIENT_POINTS_403), 403);
    }
}
