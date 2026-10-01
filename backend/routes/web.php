<?php

use App\Http\Controllers\MetricsController;
use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

// Prometheus scrape endpoint — must be declared before the catch-all redirect
// route, which would otherwise match "metrics" as a short code.
Route::get('/metrics', MetricsController::class);

Route::get('/{shortCode}', RedirectController::class)
    ->where('shortCode', '[A-Za-z0-9]+');