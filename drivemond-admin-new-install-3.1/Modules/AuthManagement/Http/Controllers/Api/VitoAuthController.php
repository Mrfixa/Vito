<?php

namespace Modules\AuthManagement\Http\Controllers\Api;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\AuthManagement\Entities\QrToken;
use Modules\AuthManagement\Service\Interfaces\AuthServiceInterface;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Service\Interfaces\CustomerLevelServiceInterface;
use Modules\UserManagement\Service\Interfaces\CustomerServiceInterface;
use Modules\UserManagement\Service\Interfaces\DriverLevelServiceInterface;
use Modules\UserManagement\Service\Interfaces\DriverServiceInterface;

class VitoAuthController extends Controller
{
    protected $authService;
    protected $customerService;
    protected $driverService;
    protected $customerLevelService;
    protected $driverLevelService;

    public function __construct(
        AuthServiceInterface         $authService,
        CustomerServiceInterface     $customerService,
        DriverServiceInterface       $driverService,
        CustomerLevelServiceInterface $customerLevelService,
        DriverLevelServiceInterface  $driverLevelService,
    ) {
        $this->authService = $authService;
        $this->customerService = $customerService;
        $this->driverService = $driverService;
        $this->customerLevelService = $customerLevelService;
        $this->driverLevelService = $driverLevelService;
    }

    public function pinLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:3|max:50',
            'pin' => 'required|string|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 422);
        }

        $isCustomerRoute = str_contains($request->route()->getPrefix(), 'customer');
        $userType = $isCustomerRoute ? CUSTOMER : DRIVER;

        $user = User::where('username', $request->username)
            ->where('user_type', $userType)
            ->first();

        if (!$user) {
            return response()->json(responseFormatter(constant: AUTH_LOGIN_401), 403);
        }

        $hitLimit = businessConfig('maximum_login_hit')?->value ?? 5;
        $blockTime = businessConfig('temporary_login_block_time')?->value ?? 60;

        if ($user->is_temp_blocked || $user->pin_blocked_at) {
            $blockedAt = $user->pin_blocked_at;
            if ($blockedAt) {
                $secondsPassed = Carbon::parse($blockedAt)->diffInSeconds();
                if ($secondsPassed <= $blockTime) {
                    $time = $blockTime - $secondsPassed;
                    return response()->json([
                        'response_code' => 'too_many_attempt_405',
                        'message' => translate('please_try_again_after_') . CarbonInterval::seconds($time)->cascade()->forHumans(),
                    ], 403);
                }
            }

            $user->pin_attempts = 0;
            $user->is_temp_blocked = 0;
            $user->pin_blocked_at = null;
            $user->save();
        }

        if (!$user->pin_hash || !Hash::check($request->pin, $user->pin_hash)) {
            $user->pin_attempts = ($user->pin_attempts ?? 0) + 1;
            if ($user->pin_attempts >= (int)$hitLimit) {
                $user->is_temp_blocked = 1;
                $user->pin_blocked_at = now();
            }
            $user->save();
            return response()->json(responseFormatter(AUTH_LOGIN_401), 403);
        }

        if (!$user->is_active) {
            $user->update(['pin_attempts' => 0]);
            if ($user->user_type === 'driver') {
                return response()->json(responseFormatter(DEFAULT_USER_UNDER_REVIEW_DISABLED_401), 403);
            }
            return response()->json(responseFormatter(DEFAULT_USER_DISABLED_401), 403);
        }

        $userData = [
            'pin_attempts' => 0,
            'is_temp_blocked' => 0,
            'pin_blocked_at' => null,
        ];
        $user = $this->authService->update(id: $user->id, data: $userData);

        foreach ($user->tokens as $token) {
            $token->revoke();
        }

        $accessType = $user->user_type == CUSTOMER ? CUSTOMER_PANEL_ACCESS : DRIVER_PANEL_ACCESS;

        return response()->json(responseFormatter(AUTH_LOGIN_200, $this->authenticate($user, $accessType)));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = auth('api')->user()->token();
        $token->revoke();

        return response()->json(responseFormatter(DEFAULT_200));
    }

    public function pinRegister(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:3|max:50|unique:users,username',
            'pin' => 'required|string|digits:6|confirmed',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'referral_code' => 'nullable|string',
            'qr_token' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 422);
        }

        $isCustomerRoute = str_contains($request->route()->getPrefix(), 'customer');

        if (!$isCustomerRoute && !businessConfig('driver_self_registration')?->value) {
            return response()->json(responseFormatter(SELF_REGISTRATION_400), 400);
        }

        $firstLevel = $isCustomerRoute
            ? $this->customerLevelService->findOneBy(['user_type' => CUSTOMER, 'sequence' => 1])
            : $this->driverLevelService->findOneBy(['user_type' => DRIVER, 'sequence' => 1]);

        if (!$firstLevel) {
            return response()->json(responseFormatter(LEVEL_403), 403);
        }

        $data = array_merge($validator->validated(), [
            'pin_hash' => Hash::make($request->pin),
            'password' => $request->pin,
        ]);

        try {
            DB::transaction(function () use ($request, $data, $isCustomerRoute) {
                $expectedRole = $isCustomerRoute ? 'customer' : 'driver';

                $qrToken = QrToken::where('token', $request->qr_token)
                    ->where('role', $expectedRole)
                    ->where('expires_at', '>', now())
                    ->whereNull('redeemed_at')
                    ->where('is_revoked', false)
                    ->lockForUpdate()
                    ->first();

                if (!$qrToken) {
                    throw new \RuntimeException('invalid_qr_token');
                }

                $user = $isCustomerRoute
                    ? $this->customerService->create($data)
                    : $this->driverService->create($data);

                $qrToken->update(['redeemed_at' => now(), 'redeemed_by' => $user->id]);
            });
        } catch (UniqueConstraintViolationException $e) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['error_code' => 'username', 'message' => 'Username already taken']]), 409);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'invalid_qr_token') {
                return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['error_code' => 'qr_token', 'message' => 'Invalid or expired invitation token']]), 400);
            }
            throw $e;
        }

        return response()->json(responseFormatter(REGISTRATION_200));
    }

    private function authenticate($user, $accessType): array
    {
        $token = $user->createToken($user->phone ?? $user->username, [$accessType])->accessToken;

        return [
            'token' => $token,
            'is_active' => $user->is_active,
            'is_phone_verified' => $user->phone_verified_at ? 1 : 0,
        ];
    }
}
