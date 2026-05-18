<?php

use Illuminate\Support\Facades\Route;

Route::controller(\Modules\AuthManagement\Http\Controllers\Api\AuthController::class)->group(function () {
    Route::group(['prefix' => 'customer'], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('registration', 'register')->name('customer-registration');
            Route::post('registration-from-otp', 'registrationFromOtp');
            Route::post('login', 'login')->name('customer-login');
            Route::post('social-login', 'customerSocialLogin');
            Route::post('update-data', 'updateData');
            //login
            Route::post('otp-login', 'otpLogin');
            Route::post('check', 'userExistOrNotChecking');
            // reset or forget password
            Route::post('forget-password', 'forgetPassword');
            Route::post('reset-password', 'resetPassword');
            Route::post('otp-verification', 'otpVerification');
            Route::post('firebase-otp-verification', 'firebaseOtpVerification');
            //send otp for otp login or reset
            Route::post('send-otp', 'sendOtp');
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
            Route::post('registration', 'register')->name('driver-registration');
            Route::post('registration-from-otp', 'registrationFromOtp');
            Route::post('update-data', 'updateData');
            Route::post('login', 'login')->name('driver-login');
            Route::post('send-otp', 'sendOtp');
            Route::post('check', 'userExistOrNotChecking');
            Route::post('forget-password', 'forgetPassword');
            Route::post('reset-password', 'resetPassword');
            Route::post('otp-verification', 'otpVerification');
            Route::post('firebase-otp-verification', 'firebaseOtpVerification');
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
    Route::group(['prefix' => 'customer/auth'], function () {
        Route::post('pin-login', 'pinLogin');
        Route::post('pin-register', 'pinRegister');
    });

    Route::group(['prefix' => 'driver/auth'], function () {
        Route::post('pin-login', 'pinLogin');
        Route::post('pin-register', 'pinRegister');
    });
});

/*
|--------------------------------------------------------------------------
| Vito QR Token Routes
|--------------------------------------------------------------------------
*/
Route::controller(\Modules\AuthManagement\Http\Controllers\Api\QrTokenController::class)->group(function () {
    Route::post('qr-token/validate', 'validateToken');
    Route::post('qr-token/redeem', 'redeemToken');
    Route::get('qr/validate/{token}', 'validateTokenPublic');

    Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::post('qr-token/generate', 'generateToken');
        Route::post('qr-token/revoke', 'revokeToken');
    });
});
