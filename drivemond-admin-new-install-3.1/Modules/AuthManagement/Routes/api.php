<?php

use Illuminate\Support\Facades\Route;

Route::controller(\Modules\AuthManagement\Http\Controllers\Api\AuthController::class)->group(function () {
    Route::group(['prefix' => 'customer'], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('registration', 'register')->name('customer-registration');
            Route::post('login', 'login')->name('customer-login');
            Route::post('social-login', 'customerSocialLogin');
            Route::post('update-data', 'updateData');
            //login
            Route::post('otp-login', 'otpLogin');
            // reset or forget password
            Route::post('forget-password', 'forgetPassword');
            Route::post('reset-password', 'resetPassword');
            Route::post('firebase-otp-verification', 'firebaseOtpVerification');
            Route::post('external-registration', 'customerRegistrationFromMart');
            Route::post('external-login', 'customerLoginFromMart');

        });
        Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
            Route::group(['prefix' => 'update'], function () {
                Route::put('fcm-token',  'updateFcmToken');
            });
        });
    });

    //driver routes
    Route::group(['prefix' => 'driver'], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('login', 'login')->name('driver-login');
            Route::post('forget-password', 'forgetPassword');
            Route::post('reset-password', 'resetPassword');
        });

        Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
            Route::group(['prefix' => 'update'], function () {
                Route::put('fcm-token',  'updateFcmToken');
            });
        });

    });

    Route::group(['prefix' => 'user', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::post('logout', 'logout')->name('logout');
        Route::post('delete', 'delete')->name('delete');
        Route::post('change-password', 'changePassword');
    });

});

/*
|--------------------------------------------------------------------------
| Vito PIN-based Authentication Routes
|--------------------------------------------------------------------------
*/
Route::controller(\Modules\AuthManagement\Http\Controllers\Api\VitoAuthController::class)->group(function () {
    Route::group(['prefix' => 'customer/auth', 'middleware' => 'throttle:20,1'], function () {
        Route::post('pin-login', 'pinLogin');
        Route::post('pin-register', 'pinRegister');
    });

    Route::group(['prefix' => 'driver/auth', 'middleware' => 'throttle:20,1'], function () {
        Route::post('pin-login', 'pinLogin');
        Route::post('pin-register', 'pinRegister');
    });

    Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::post('auth/logout', 'logout');
    });

    // Public username availability probe (used by the registration screen)
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('check-username', 'checkUsername');
    });
});

/*
|--------------------------------------------------------------------------
| Vito QR Token Routes
|--------------------------------------------------------------------------
*/
Route::controller(\Modules\AuthManagement\Http\Controllers\Api\QrTokenController::class)->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('qr-token/validate', 'validateToken');
        Route::get('qr/validate/{token}', 'validateTokenPublic');
    });

    Route::group(['middleware' => ['auth:api', 'scope:AccessToSuperAdmin', 'maintenance_mode', 'throttle:10,1']], function () {
        Route::post('qr-token/generate', 'generateToken');
        Route::post('qr-token/revoke', 'revokeToken');
    });
});

/*
|--------------------------------------------------------------------------
| Vito Client OTP Authentication Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'customer/auth', 'middleware' => ['throttle:20,1']], function () {
    Route::post('check', [\Modules\AuthManagement\Http\Controllers\Api\ClientOtpAuthController::class, 'checkUser']);
    Route::post('send-otp', [\Modules\AuthManagement\Http\Controllers\Api\ClientOtpAuthController::class, 'sendOtp']);
    Route::post('otp-verification', [\Modules\AuthManagement\Http\Controllers\Api\ClientOtpAuthController::class, 'verifyOtp']);
    Route::post('registration-from-otp', [\Modules\AuthManagement\Http\Controllers\Api\ClientOtpAuthController::class, 'registrationFromOtp']);
});
