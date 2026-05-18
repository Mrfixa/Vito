<?php

namespace Modules\AuthManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\AuthManagement\Entities\QrToken;

class QrTokenController extends Controller
{
    public function generateToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:driver,customer',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $token = QrToken::create([
            'token' => Str::random(64),
            'role' => $request->role,
            'created_by' => $request->user()->id,
            'expires_at' => $request->role === 'customer' ? now()->addHour() : now()->addDays(7),
        ]);

        return response()->json(responseFormatter(DEFAULT_200, [
            'token' => $token->token,
            'role' => $token->role,
            'expires_at' => $token->expires_at->toISOString(),
            'qr_data' => json_encode([
                'token' => $token->token,
                'role' => $token->role,
            ]),
        ]));
    }

    public function validateToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $qrToken = QrToken::where('token', $request->token)->first();

        if (!$qrToken || !$qrToken->isValid()) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 404);
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'valid' => true,
            'role' => $qrToken->role,
        ]));
    }

    public function redeemToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $qrToken = DB::transaction(function () use ($request) {
            $token = QrToken::where('token', $request->token)
                ->lockForUpdate()
                ->first();

            if (!$token || !$token->isValid()) {
                return null;
            }

            $token->update([
                'redeemed_at' => now(),
                'redeemed_by' => $request->user()?->id,
            ]);

            return $token;
        });

        if (!$qrToken) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 404);
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'redeemed' => true,
            'role' => $qrToken->role,
        ]));
    }

    public function validateTokenPublic(string $token): JsonResponse
    {
        $qrToken = QrToken::where('token', $token)->first();

        if (!$qrToken || !$qrToken->isValid()) {
            return response()->json(responseFormatter(constant: DEFAULT_404, errors: [['message' => 'Token is invalid or expired']]), 404);
        }

        $data = [
            'valid' => true,
            'role' => $qrToken->role,
        ];

        if ($qrToken->role === 'customer' && $qrToken->creator) {
            $data['driver_name'] = $qrToken->creator->first_name . ' ' . $qrToken->creator->last_name;
        }

        return response()->json(responseFormatter(DEFAULT_200, $data));
    }

    public function revokeToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $token = QrToken::where('token', $request->token)
            ->where('created_by', $request->user()->id)
            ->first();

        if (!$token) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 404);
        }

        $token->update(['is_revoked' => true]);

        return response()->json(responseFormatter(DEFAULT_200));
    }
}
