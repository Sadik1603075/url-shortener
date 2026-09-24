<?php

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
    */

    Route::post('/urls', [ShortUrlController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Admin APIs
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'admin'])
        ->prefix('admin')
        ->group(function () {
            Route::get('/urls', [ShortUrlController::class, 'index']);
    });
    
});