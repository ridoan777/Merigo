<?php

use App\Http\Controllers\Billings\PointWallets\WebWalletController;
use Illuminate\Support\Facades\Route;


// ------------ BAR WALLET ------------

Route::prefix('admin')->middleware(['auth:web'])->group(function () {

   Route::get('/wallet/bar/index', [WebWalletController::class, 'barWalletIndex'])->name('backend_bar_wallet_index');

   Route::post('/wallet/bars/{barWallet}/toggle', [WebWalletController::class, 'toggleBar'])->name('backend_bar_wallet_toggle');

   Route::delete('/wallet/bars/{barWallet}/delete', [WebWalletController::class, 'deleteBar'])->name('backend_bar_wallet_delete');

   Route::post('/wallet/bars/bulk-delete', [WebWalletController::class, 'bulkDeleteBar'])->name('backend_bar_wallet_bulk_delete');
   
   Route::post('/wallet/bar/datatable', [WebWalletController::class, 'barWalletDatatable'])->name('backend_bar_wallet_datatable');
});
// ------------ BAR WALLET ------------


// ------------ MERIGO WALLET ------------

Route::prefix('admin')->middleware(['auth:web'])->group(function () {

   Route::get('/wallet/merigo/index', [WebWalletController::class, 'merigoWalletIndex'])->name('backend_merigo_wallet_index');

   Route::post('/wallet/merigos/{merigoWallet}/toggle', [WebWalletController::class, 'toggleMerigo'])->name('backend_merigo_wallet_toggle');

   Route::delete('/wallet/merigos/{merigoWallet}/delete', [WebWalletController::class, 'deleteMerigo'])->name('backend_merigo_wallet_delete');

   Route::post('/wallet/merigos/bulk-delete', [WebWalletController::class, 'bulkDeleteMerigo'])->name('backend_merigo_wallet_bulk_delete');
   
   Route::post('/wallet/merigo/datatable', [WebWalletController::class, 'merigoWalletDatatable'])->name('backend_merigo_wallet_datatable');
});
// ------------ MERIGO WALLET ------------


// ------------ WALLET TRX ------------

Route::prefix('admin')->middleware(['auth:web'])->group(function () {

   Route::get('/wallet/transactions/index', [WebWalletController::class, 'walletTrxIndex'])->name('backend_wallet_trx_index');

   Route::post('/wallet/transactions/{singleTrx}/toggle', [WebWalletController::class, 'toggleTrx'])->name('backend_wallet_trx_toggle');

   Route::delete('/wallet/transactions/{singleTrx}/delete', [WebWalletController::class, 'deleteTrx'])->name('backend_wallet_trx_delete');

   Route::post('/wallet/transactions/bulk-delete', [WebWalletController::class, 'bulkDeleteTrx'])->name('backend_wallet_trx_bulk_delete');
   
   Route::post('/wallet/transactions/datatable', [WebWalletController::class, 'walletTrxDatatable'])->name('backend_wallet_trx_datatable');
});
// ------------ WALLET TRX ------------