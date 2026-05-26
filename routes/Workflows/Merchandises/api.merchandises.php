<?php

use App\Http\Controllers\Billings\PointWallets\ApiTransactionController;
use App\Http\Controllers\Workflows\Merchandises\{ApiMerchandiseController,ApiMercRequestController};
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/merchandise/all-goods/index', [ApiMerchandiseController::class, 'index']);

});

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/merchandise/requests/index', [ApiMercRequestController::class, 'index']);

    Route::post('/merchandise/{merchandise}/request', [ApiTransactionController::class, 'mercRequest']);

});
    