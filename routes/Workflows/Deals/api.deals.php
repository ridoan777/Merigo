<?php

use App\Http\Controllers\Workflows\Deals\ApiDealController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:sanctum'])->group(function () {

   Route::get('/deals/index', [ApiDealController::class, 'index']);
   Route::get('/deals/bars/index', [ApiDealController::class, 'indexByBars']);

   Route::get('/deals/admin/my-deals', [ApiDealController::class, 'myDealsAdmin']);
   Route::get('/deals/bar-deals/{bar}/show', [ApiDealController::class, 'showBarDeals']);

   Route::get('/deals/{deal}/show', [ApiDealController::class, 'show']);

   Route::post('/deals/store', [ApiDealController::class, 'store']);
   Route::delete('/deals/{deal}/delete', [ApiDealController::class, 'delete']);
});
