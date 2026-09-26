<?php

use App\Http\Controllers\Api\V1\AccessCodeController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ShortUrlController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Public Short URLs
    |--------------------------------------------------------------------------
    | Requires a valid access_code in the request body.
    */

    Route::post('/urls', [ShortUrlController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Admin API
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'admin'])
        ->prefix('admin')
        ->group(function () {

            // Short URLs
            Route::get('/urls',          [ShortUrlController::class, 'index']);
            Route::get('/urls/{id}',     [ShortUrlController::class, 'show']);
            Route::patch('/urls/{id}',   [ShortUrlController::class, 'update']);
            Route::delete('/urls/{id}',  [ShortUrlController::class, 'destroy']);

            // Access Codes
            Route::get('/access-codes',              [AccessCodeController::class, 'index']);
            Route::post('/access-codes',             [AccessCodeController::class, 'store']);
            Route::get('/access-codes/{id}',         [AccessCodeController::class, 'show']);
            Route::patch('/access-codes/{id}',       [AccessCodeController::class, 'update']);
            Route::delete('/access-codes/{id}',      [AccessCodeController::class, 'destroy']);
            Route::post('/access-codes/{id}/send',   [AccessCodeController::class, 'send']);

        });

});
