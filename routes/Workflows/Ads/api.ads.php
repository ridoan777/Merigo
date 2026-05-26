<?php

use App\Http\Controllers\Workflows\Ads\ApiAdsController;
use Illuminate\Support\Facades\Route;




Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/ads/show', [ApiAdsController::class, 'show']);
});
