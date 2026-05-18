<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthManagement\Http\Controllers\Web\Admin\Auth\LoginController;

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

Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::group(['prefix' => 'auth', 'as' => 'auth.'], function () {
        Route::controller(LoginController::class)->group(function () {
            Route::get('/code/captcha/{tmp}', 'captcha')->name('default-captcha');
            Route::get('login', 'loginView')->name('login');
            Route::post('login', 'login');
            Route::post('external-login-from-mart', 'externalLoginFromMart');
            Route::get('logout', 'logout')->name('logout');
        });
    });

    Route::group(['prefix' => 'qr-tokens', 'as' => 'qr-tokens.', 'middleware' => 'admin'], function () {
        Route::controller(\Modules\AuthManagement\Http\Controllers\Web\VitoQrAdminController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('generate', 'generate')->name('generate');
            Route::post('revoke/{id}', 'revoke')->name('revoke');
        });
    });
});

