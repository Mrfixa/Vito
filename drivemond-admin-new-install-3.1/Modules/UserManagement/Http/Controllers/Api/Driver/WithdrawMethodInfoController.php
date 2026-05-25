<?php

namespace Modules\UserManagement\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Service\Interfaces\DriverWithdrawMethodInfoServiceInterface;
use Modules\UserManagement\Service\Interfaces\WithdrawMethodServiceInterface;
use Modules\UserManagement\Service\Interfaces\WithdrawRequestServiceInterface;
use Modules\UserManagement\Transformers\UserWithdrawMethodInfoResource;

class WithdrawMethodInfoController extends Controller
{
    protected $driverWithdrawMethodInfoService;
    protected $withdrawMethodService;
    protected $withdrawRequestService;

    public function __construct(DriverWithdrawMethodInfoServiceInterface $driverWithdrawMethodInfoService, WithdrawMethodServiceInterface $withdrawMethodService,
                                WithdrawRequestServiceInterface          $withdrawRequestService)
    {
        $this->driverWithdrawMethodInfoService = $driverWithdrawMethodInfoService;
        $this->withdrawMethodService = $withdrawMethodService;
        $this->withdrawRequestService = $withdrawRequestService;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|integer',
            'offset' => 'required|integer',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $user = auth()->user();
        $withdrawMethodInfos = $this->driverWithdrawMethodInfoService->getBy(criteria: ['user_id' => $user->id, 'is_active' => 1], limit: $request->limit, offset: $request->offset);
        $withdrawMethodInfos = UserWithdrawMethodInfoResource::collection($withdrawMethodInfos);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $withdrawMethodInfos, limit: $request->limit, offset: $request->offset));
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'withdraw_method' => 'required',
            'method_name' => 'required|unique:user_withdraw_method_infos,user_id,' . $user->id,
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        if ($user?->user_type != 'driver') {

            return response()->json(responseFormatter(DEFAULT_403), 403);
        }
        $account = $this->driverWithdrawMethodInfoService->findOneBy(criteria: ['user_id' => $user->id, 'method_name' => $request->method_name]);
        if ($account) {
            return response()->json(responseFormatter(WITHDRAW_METHOD_INFO_EXIST_403), 403);
        }

        $method = $this->withdrawMethodService->findOne(id: $request->withdraw_method);
        $fields = array_column($method->method_fields, 'input_name');
        $values = $request->all();

        $data = [];
        foreach ($fields as $field) {
            if (key_exists($field, $values)) {
                $data[$field] = $values[$field];
            }
        }

        DB::beginTransaction();
        $attributes = [
            'user_id' => $user?->id,
            'withdraw_method_id' => $request->withdraw_method,
            'method_name' => $request->method_name,
            'method_info' => $data,
        ];
        $this->driverWithdrawMethodInfoService->create(data: $attributes);
        DB::commit();

        return response()->json(responseFormatter(WITHDRAW_METHOD_INFO_STORE_200));
    }

    public function update($id, Request $request)
    {

        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'withdraw_method' => 'required',
            'method_name' => 'required|unique:user_withdraw_method_infos,user_id,' . $user->id,
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        if ($user?->user_type != 'driver') {

            return response()->json(responseFormatter(DEFAULT_403), 403);
        }

        $withdrawMethodInfo = $this->driverWithdrawMethodInfoService->findOne(id: $id);
        if (!$withdrawMethodInfo) {
            return response()->json(responseFormatter(DEFAULT_400), 400);
        }
        $method = $this->withdrawMethodService->findOne(id: $withdrawMethodInfo->withdraw_method_id);
        $fields = array_column($method->method_fields, 'input_name');
        $values = $request->all();

        $data = [];
        foreach ($fields as $field) {
            if (key_exists($field, $values)) {
                $data[$field] = $values[$field];
            }
        }

        DB::beginTransaction();
        $attributes = [
            'method_name' => $request->method_name,
            'method_info' => $data,
        ];
        $this->driverWithdrawMethodInfoService->update(id: $id, data: $attributes);
        DB::commit();

        return response()->json(responseFormatter(WITHDRAW_METHOD_INFO_UPDATE_200));
    }

    public function destroy($id)
    {
        $withdrawMethodInfo = $this->driverWithdrawMethodInfoService->findOne(id: $id);
        if (!$withdrawMethodInfo) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        $withdrawRequest = $this->withdrawRequestService->getBy(criteria: ['method_id' => $withdrawMethodInfo->withdraw_method_id], whereInCriteria: ['status' => [APPROVED, PENDING]]);
        if (count($withdrawRequest) > 0) {
            return response()->json(responseFormatter(WITHDRAW_METHOD_INFO_REQUEST_EXIST_403), 403);
        }
        $this->driverWithdrawMethodInfoService->delete(id: $id);
        return response()->json(responseFormatter(WITHDRAW_METHOD_INFO_DELETE_200));

    }
}
