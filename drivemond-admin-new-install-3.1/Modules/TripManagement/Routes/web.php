<?php

use Illuminate\Support\Facades\Route;
use Modules\TripManagement\Http\Controllers\Web\RefundController;
use Modules\TripManagement\Http\Controllers\Web\SafetyAlertController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::group(['prefix' => 'trip', 'as' => 'trip.'], function () {
        Route::controller(\Modules\TripManagement\Http\Controllers\Web\TripController::class)->group(function () {
            Route::get('list/{type}', 'tripList')->name('index');
            Route::get('details/{id}', 'show')->name('show');
            Route::delete('delete/{id}', 'destroy')->name('delete');
            Route::get('export', 'export')->name('export');
            Route::get('log', 'log')->name('log');
            Route::get('invoice/{id}', 'invoice')->name('invoice');
        });

        Route::group(['prefix' => 'refund', 'as' => 'refund.'], function () {
            Route::controller(RefundController::class)->group(function () {
                Route::get('list/{type}', 'parcelRefundList')->name('index');
                Route::get('details/{id}', 'show')->name('show');
                Route::post('approved/{id}', 'storeApproved')->name('approved');
                Route::post('denied/{id}', 'storeDenied')->name('denied');
                Route::post('store/{id}', 'store')->name('store');
                Route::get('export', 'export')->name('export');
            });
        });
    });

    Route::group(['prefix' => 'safety-alert', 'as' => 'safety-alert.'], function () {
        Route::controller(SafetyAlertController::class)->group(function () {
            Route::get('list/{type}', 'index')->name('index');
            Route::get('export/{type}', 'export')->name('export');
            Route::put('mark-as-solved/{id}', 'markAsSolved')->name('mark-as-solved');
            Route::put('ajax-mark-as-solved/{id}', 'ajaxMarkAsSolved')->name('ajax-mark-as-solved');
        });
    });

    Route::group(['prefix' => 'mart/products', 'as' => 'mart.products.'], function () {
        Route::controller(\Modules\TripManagement\Http\Controllers\Web\VitoMartAdminController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('store', 'store')->name('store');
            Route::get('{id}/edit', 'edit')->name('edit');
            Route::put('{id}', 'update')->name('update');
            Route::delete('{id}', 'destroy')->name('destroy');
            Route::post('{id}/toggle-status', 'toggleStatus')->name('toggle-status');
            Route::post('{id}/stock-adjust', 'stockAdjust')->name('stock-adjust');
        });
    });

});
