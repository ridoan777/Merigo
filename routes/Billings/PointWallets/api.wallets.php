<?php

use App\Http\Controllers\Billings\PointWallets\ApiTransactionController;
use App\Http\Controllers\Billings\PointWallets\ApiWalletController;
use Illuminate\Support\Facades\Route;


Route::prefix('wallets')->middleware(['auth:sanctum'])->group(function () {

   Route::get('/bar/index', [ApiWalletController::class, 'indexBarWallets']);
   Route::get('/merigo/index', [ApiWalletController::class, 'indexMerigoWallets']);
   Route::get('/merigo/my-wallet', [ApiWalletController::class, 'showMerigo']);

});

Route::prefix('wallets/transactions')->middleware(['auth:sanctum'])->group(function () {

   Route::get('/index', [ApiWalletController::class, 'index']);
   
   Route::get('/bars/index', [ApiWalletController::class, 'indexBarTrx']);
   Route::post('/check-in', [ApiTransactionController::class, 'checkIn']);
   Route::post('/deal', [ApiTransactionController::class, 'deal']);

});
