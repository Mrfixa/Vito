<?php

namespace Modules\AuthManagement\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\UserManagement\Entities\User;

class ClientOtpAuthController extends Controller
{
    /**
     * POST /api/customer/auth/check
     * Checks if a phone number is usable. Always returns 200 so the client
     * proceeds to send-otp.  Does NOT create the user yet.
     */
    public function checkUser(Request $request)
    {
        $request->validate(['phone_or_email' => 'required|string|min:5|max:30']);
        return response()->json(['message' => 'Phone accepted', 'is_registered' => true]);
    }

    /**
     * POST /api/customer/auth/send-otp
     * Generates a 6-digit OTP, stores the hash, and (in non-testing env) sends
     * an SMS via Twilio or just logs it.  In testing / local env the raw OTP is
     * returned so automated tests can proceed without a real SMS gateway.
     */
    public function sendOtp(Request $request)
    {
        $request->validate(['phone_or_email' => 'required|string|min:5|max:30']);
        $phone = $request->input('phone_or_email');

        // 30-second resend cooldown
        $recent = DB::table('vito_otps')
            ->where('phone', $phone)
            ->where('created_at', '>', now()->subSeconds(30))
            ->whereNull('verified_at')
            ->exists();

        if ($recent) {
            return response()->json(
                ['errors' => ['message' => ['Please wait 30 seconds before requesting a new OTP.']]],
                429
            );
        }

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('vito_otps')->insert([
            'id'         => (string) Str::uuid(),
            'phone'      => $phone,
            'otp_hash'   => Hash::make($otp),
            'expires_at' => now()->addMinutes(5)->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->dispatchSms($phone, "Your VITO verification code is: {$otp}");

        $resp = ['message' => 'OTP sent successfully'];

        // Expose OTP only in testing / local – never in production
        if (app()->environment('testing', 'local')) {
            $resp['otp'] = $otp;
        }

        return response()->json($resp);
    }

    /**
     * POST /api/customer/auth/otp-verification
     * Verifies the OTP.
     * – 200  + token  → existing user with profile complete (login success)
     * – 406           → new user or profile incomplete (Flutter navigates to OtpSignupScreen)
     * – 400           → invalid OTP
     * – 422           → too many attempts
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone_or_email' => 'required|string|min:5|max:30',
            'otp'            => 'required|string|size:6',
        ]);

        $phone = $request->input('phone_or_email');
        $otp   = $request->input('otp');

        $record = DB::table('vito_otps')
            ->where('phone', $phone)
            ->where('expires_at', '>', now()->toDateTimeString())
            ->whereNull('verified_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$record) {
            return response()->json(
                ['errors' => ['message' => ['OTP has expired or was not found. Please request a new one.']]],
                404
            );
        }

        if ($record->attempts >= 5) {
            return response()->json(
                ['errors' => ['message' => ['Too many failed attempts. Please request a new OTP.']]],
                422
            );
        }

        DB::table('vito_otps')->where('id', $record->id)->increment('attempts');

        if (!Hash::check($otp, $record->otp_hash)) {
            return response()->json(
                ['errors' => ['message' => ['Invalid OTP. Please try again.']]],
                400
            );
        }

        // Mark as verified
        DB::table('vito_otps')
            ->where('id', $record->id)
            ->update(['verified_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()]);

        // Find or create the customer
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            $user = User::create([
                'id'        => (string) Str::uuid(),
                'phone'     => $phone,
                'user_type' => 'customer',
                'is_active' => 0,
            ]);
        }

        // If profile is incomplete (no first_name), tell Flutter to show the profile screen
        $hasProfile = !empty($user->first_name);
        if (!$hasProfile) {
            return response()->json(
                ['errors' => ['message' => ['Profile incomplete. Please complete your registration.']], 'phone' => $phone],
                406
            );
        }

        if (!$user->is_active) {
            $user->update(['is_active' => 1]);
        }

        $token = $user->createToken('client-otp', ['AccessToCustomer'])->accessToken;

        return response()->json([
            'message' => 'Login successful',
            'data'    => ['token' => $token, 'user' => $this->userArray($user)],
        ]);
    }

    /**
     * POST /api/customer/auth/registration-from-otp
     * Completes the profile for a newly verified user (Flutter's OtpSignupScreen).
     * Called after a 406 response from verify-otp. Requires the phone to have a
     * recently-verified OTP in vito_otps.
     */
    public function registrationFromOtp(Request $request)
    {
        $validated = $request->validate([
            'f_name'        => 'required|string|max:100',
            'l_name'        => 'nullable|string|max:100',
            'phone'         => 'required|string|min:5|max:30',
            'email'         => 'nullable|email|max:255',
            'referral_code' => 'nullable|string|max:50',
        ]);

        $phone = $validated['phone'];

        // Confirm a recent verified OTP exists for this phone (max 10 minutes ago)
        $recentVerified = DB::table('vito_otps')
            ->where('phone', $phone)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>', now()->subMinutes(10)->toDateTimeString())
            ->exists();

        if (!$recentVerified) {
            return response()->json(
                ['errors' => ['message' => ['OTP verification required before completing registration.']]],
                401
            );
        }

        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json(
                ['errors' => ['message' => ['User not found. Please start the registration again.']]],
                404
            );
        }

        $updateData = [
            'is_active'  => 1,
            'first_name' => $validated['f_name'],
        ];

        if (!empty($validated['l_name'])) {
            $updateData['last_name'] = $validated['l_name'];
        }
        if (!empty($validated['email'])) {
            $updateData['email'] = $validated['email'];
        }

        $user->update($updateData);

        $token = $user->createToken('client-otp', ['AccessToCustomer'])->accessToken;

        return response()->json([
            'message' => 'Registration successful',
            'data'    => ['token' => $token, 'user' => $this->userArray($user)],
        ]);
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function userArray(User $user): array
    {
        return [
            'id'         => $user->id,
            'phone'      => $user->phone,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'is_active'  => $user->is_active,
        ];
    }

    private function dispatchSms(string $phone, string $message): void
    {
        try {
            $sid   = env('TWILIO_ACCOUNT_SID');
            $token = env('TWILIO_AUTH_TOKEN');
            $from  = env('TWILIO_FROM_NUMBER');

            if ($sid && $token && $from) {
                // Use Twilio if configured
                $client = new \Twilio\Rest\Client($sid, $token);
                $client->messages->create($phone, ['from' => $from, 'body' => $message]);
            } else {
                // Fallback: log only (works in dev / testing)
                Log::info("OTP SMS to {$phone}: {$message}");
            }
        } catch (\Throwable $e) {
            Log::warning('SMS dispatch failed: ' . $e->getMessage());
        }
    }
}
