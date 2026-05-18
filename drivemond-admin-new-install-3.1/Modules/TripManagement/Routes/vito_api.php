<?php

use Illuminate\Support\Facades\Route;
use Modules\TripManagement\Http\Controllers\Api\Customer\VitoMartController;
use Modules\TripManagement\Http\Controllers\Api\Driver\VitoMartDriverController;

/*
|--------------------------------------------------------------------------
| VitoMart Customer Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'customer/mart', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::controller(VitoMartController::class)->group(function () {
        Route::get('products', 'products');
        Route::get('products/{id}', 'productDetails');
        Route::post('order', 'createOrder');
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
Route::group(['prefix' => 'driver/mart', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::controller(VitoMartDriverController::class)->group(function () {
        Route::get('pending-orders', 'pendingOrders');
        Route::post('accept-order', 'acceptOrder');
        Route::put('update-status', 'updateStatus');
        Route::post('upload-proof', 'uploadDeliveryProof');
        Route::get('my-orders', 'myOrders');
    });
});
