<?php

/*
|--------------------------------------------------------------------------
| VITO canonical alias routes
|--------------------------------------------------------------------------
|
| The VITO playbook documents a flat URL surface
| (e.g. POST /api/rides/accept) for clients that want to address VITO
| flows without going through DriveMond's nested customer/driver
| prefixes. These aliases simply re-bind the existing Vito controllers
| under the playbook's canonical paths. The original DriveMond routes
| under /api/customer/* and /api/driver/* continue to work unchanged.
|
*/

use Illuminate\Support\Facades\Route;
use Modules\AuthManagement\Http\Controllers\Api\QrTokenController;
use Modules\AuthManagement\Http\Controllers\Api\VitoAuthController;
use Modules\Gateways\Http\Controllers\Api\VitoStripeController;
use Modules\ReviewModule\Http\Controllers\Api\ReviewController;
use Modules\TripManagement\Http\Controllers\Api\Customer\TripRequestController as CustomerTripController;
use Modules\TripManagement\Http\Controllers\Api\Customer\VitoMartController;
use Modules\TripManagement\Http\Controllers\Api\Driver\TripRequestController as DriverTripController;
use Modules\TripManagement\Http\Controllers\Api\Driver\VitoMartDriverController;
use Modules\TripManagement\Http\Controllers\Api\Driver\VitoParcelController;
use Modules\TripManagement\Http\Controllers\Api\Driver\VitoTripController;
use Modules\UserManagement\Http\Controllers\Api\Customer\VitoWalletController;

/* Auth / QR */
Route::middleware('throttle:60,1')->group(function () {
    Route::post('check-username', [VitoAuthController::class, 'checkUsername']);
});

Route::middleware('throttle:20,1')->group(function () {
    Route::post('pin-login', [VitoAuthController::class, 'pinLogin']);
    Route::post('register-client', [VitoAuthController::class, 'pinRegister'])
        ->defaults('_vito_role', 'customer');
    Route::post('register-driver', [VitoAuthController::class, 'pinRegister'])
        ->defaults('_vito_role', 'driver');
});

/* Rides */
Route::middleware(['auth:api', 'maintenance_mode', 'scope:AccessToCustomer'])
    ->prefix('rides')
    ->group(function () {
        Route::post('create', [CustomerTripController::class, 'createRideRequest']);
    });

Route::middleware(['auth:api', 'maintenance_mode', 'scope:AccessToDriver'])
    ->prefix('rides')
    ->group(function () {
        Route::post('accept', [VitoTripController::class, 'atomicAccept']);
        Route::post('update-status', [DriverTripController::class, 'rideStatusUpdate']);
    });

Route::middleware(['auth:api', 'maintenance_mode'])
    ->prefix('rides')
    ->group(function () {
        Route::post('rate', [ReviewController::class, 'store']);
    });

/* Parcel */
Route::middleware(['auth:api', 'maintenance_mode', 'scope:AccessToCustomer'])
    ->prefix('parcel')
    ->group(function () {
        Route::post('create', [CustomerTripController::class, 'createRideRequest']);
    });

Route::middleware(['auth:api', 'maintenance_mode', 'scope:AccessToDriver'])
    ->prefix('parcel')
    ->group(function () {
        Route::post('accept', [VitoParcelController::class, 'atomicAcceptParcel']);
        Route::post('update-status', [DriverTripController::class, 'rideStatusUpdate']);
    });

/* Mart (canonical flat aliases for customer + driver endpoints) */
Route::middleware(['auth:api', 'maintenance_mode', 'scope:AccessToCustomer'])
    ->prefix('mart')
    ->group(function () {
        Route::get('products', [VitoMartController::class, 'products']);
        Route::get('products/{id}', [VitoMartController::class, 'productDetails']);
        Route::post('orders', [VitoMartController::class, 'createOrder']);
        Route::get('orders', [VitoMartController::class, 'myOrders']);
        Route::get('orders/{id}', [VitoMartController::class, 'orderDetails']);
        Route::put('orders/{id}/cancel', [VitoMartController::class, 'cancelOrder']);
    });

Route::middleware(['auth:api', 'maintenance_mode', 'scope:AccessToDriver'])
    ->prefix('mart')
    ->group(function () {
        Route::post('orders/{id}/photo', [VitoMartDriverController::class, 'uploadDeliveryProof']);
        Route::post('orders/{id}/signature', [VitoMartDriverController::class, 'uploadDeliveryProof']);
        Route::post('orders/{id}/status', [VitoMartDriverController::class, 'updateStatus']);
    });

/* Admin Mart JSON API */
Route::middleware(['auth:api', 'maintenance_mode', 'scope:AccessToSuperAdmin'])
    ->prefix('admin/mart')
    ->group(function () {
        Route::get('products', [\Modules\TripManagement\Http\Controllers\Api\Admin\VitoMartAdminApiController::class, 'index']);
        Route::post('products', [\Modules\TripManagement\Http\Controllers\Api\Admin\VitoMartAdminApiController::class, 'store']);
        Route::get('products/{id}', [\Modules\TripManagement\Http\Controllers\Api\Admin\VitoMartAdminApiController::class, 'show']);
        Route::put('products/{id}', [\Modules\TripManagement\Http\Controllers\Api\Admin\VitoMartAdminApiController::class, 'update']);
        Route::delete('products/{id}', [\Modules\TripManagement\Http\Controllers\Api\Admin\VitoMartAdminApiController::class, 'destroy']);
    });

/* Wallet & Stripe */
Route::middleware(['auth:api', 'maintenance_mode', 'scope:AccessToCustomer'])
    ->group(function () {
        Route::get('wallet', [VitoWalletController::class, 'show']);
        Route::post('wallet/topup-intent', [VitoStripeController::class, 'createPaymentIntent']);
    });
