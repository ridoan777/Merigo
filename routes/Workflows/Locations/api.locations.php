<?php

namespace App\Models\Workflows\Locations;

use App\Http\Controllers\Workflows\UserLocation\ApiGeoLocationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/user/location', [ApiGeoLocationController::class, 'location']);
    Route::get('/user/single-location/{singleBar}/show', [ApiGeoLocationController::class, 'singleUserLocation']);
    Route::post('/user/location/store', [ApiGeoLocationController::class, 'store']);
});

Route::prefix('checkin')->middleware(['auth:sanctum'])->group(function () {

    Route::post('/fetch-bars', [ApiGeoLocationController::class, 'barCheckin']);
});
