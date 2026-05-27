<?php

use Illuminate\Support\Facades\Route;
use Modules\TripManagement\Http\Controllers\Api\Customer\VitoMartController;
use Modules\TripManagement\Http\Controllers\Api\Driver\VitoMartDriverController;

/*
|--------------------------------------------------------------------------
| VitoMart Customer Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'customer/mart', 'middleware' => ['auth:api', 'maintenance_mode', 'scope:AccessToCustomer']], function () {
    Route::controller(VitoMartController::class)->group(function () {
        Route::get('products', 'products');
        Route::get('products/{id}', 'productDetails');
        Route::middleware('throttle:30,1')->post('apply-promo', 'applyPromo');
        Route::middleware('throttle:10,1')->post('order', 'createOrder');
        Route::get('orders', 'orderList');
        Route::get('orders/{id}', 'orderDetails');
        Route::put('orders/{id}/cancel', 'cancelOrder');
    });
});

/*
|--------------------------------------------------------------------------
| VitoMart Driver Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'driver/mart', 'middleware' => ['auth:api', 'maintenance_mode', 'scope:AccessToDriver']], function () {
    Route::controller(VitoMartDriverController::class)->group(function () {
        Route::get('pending-orders', 'pendingOrders');
        Route::post('accept-order', 'acceptOrder');
        Route::put('update-status', 'updateStatus');
        Route::middleware('throttle:10,1')->post('upload-proof', 'uploadDeliveryProof');
        Route::get('my-orders', 'myOrders');
        Route::get('orders/{id}', 'orderDetails');
        // Alias routes for per-order photo and signature upload
        Route::post('orders/{id}/photo', 'uploadDeliveryProof');
        Route::post('orders/{id}/signature', 'uploadDeliveryProof');
    });
});

/*
|--------------------------------------------------------------------------
| VitoMart Admin API Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'admin/mart', 'middleware' => ['auth:api', 'maintenance_mode', 'scope:AccessToSuperAdmin']], function () {
    // Admin Mart Product API
    Route::get('products', [\Modules\TripManagement\Http\Controllers\Api\Admin\VitoMartAdminApiController::class, 'index']);
    Route::post('products', [\Modules\TripManagement\Http\Controllers\Api\Admin\VitoMartAdminApiController::class, 'store']);
    Route::put('products/{id}', [\Modules\TripManagement\Http\Controllers\Api\Admin\VitoMartAdminApiController::class, 'update']);
    Route::delete('products/{id}', [\Modules\TripManagement\Http\Controllers\Api\Admin\VitoMartAdminApiController::class, 'destroy']);
});
