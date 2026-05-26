<?php

use App\Http\Controllers\Workflows\Referrals\ApiReferralsController;
use Illuminate\Support\Facades\Route;




Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/referrals/data', [ApiReferralsController::class, 'allBars']);
    // Route::get('/bars/single-bar/{singleBar}/show', [ApiBarController::class, 'singleBar']);
    // Route::post('/bars/store', [ApiReferralsController::class, 'store']);
    // Route::delete('/bars/delete/{id}', [ApiBarController::class, 'delete']);
});
