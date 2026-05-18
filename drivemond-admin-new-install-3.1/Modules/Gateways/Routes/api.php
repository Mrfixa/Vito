<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Gateways\Http\Controllers\Api\V1\PaymentConfigController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/Gateways', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'v1', 'as'=>'v1.'], function () {
    Route::get('payment-config', [PaymentConfigController::class, 'payment_config_get']);
});

/*
|--------------------------------------------------------------------------
| Vito Stripe Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'customer/stripe', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::post('payment-intent', [\Modules\Gateways\Http\Controllers\Api\VitoStripeController::class, 'createPaymentIntent']);
});

Route::post('stripe/webhook', [\Modules\Gateways\Http\Controllers\Api\VitoStripeController::class, 'webhook']);
