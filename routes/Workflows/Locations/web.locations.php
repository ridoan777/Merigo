<?php

namespace App\Models\Workflows\Locations;

use App\Http\Controllers\Workflows\UserLocation\WebGeoLocationController;
use Illuminate\Support\Facades\Route;


Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {

   Route::get('/user/get-location', [WebGeoLocationController::class, 'saveLocation']);
});
