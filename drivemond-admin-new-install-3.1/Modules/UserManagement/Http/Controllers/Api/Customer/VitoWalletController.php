<?php

namespace Modules\UserManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\UserManagement\Entities\UserAccount;

/**
 * Vito canonical wallet endpoint backing GET /api/wallet.
 * Returns the authenticated customer's wallet balance plus recent transactions.
 */
class VitoWalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $account = UserAccount::where('user_id', $userId)->first();

        $transactions = collect();
        if (class_exists(\Modules\TransactionManagement\Entities\Transaction::class)) {
            try {
                $transactions = \Modules\TransactionManagement\Entities\Transaction::query()
                    ->where(function ($q) use ($userId) {
                        $q->where('from_user_id', $userId)->orWhere('to_user_id', $userId);
                    })
                    ->orderByDesc('created_at')
                    ->limit(min((int)$request->input('limit', 20), 100))
                    ->get();
            } catch (\Throwable $e) {
                $transactions = collect();
            }
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'balance' => $account?->wallet_balance ?? 0.0,
            'currency' => businessConfig('currency_code')?->value ?? 'USD',
            'transactions' => $transactions,
        ]));
    }
}
