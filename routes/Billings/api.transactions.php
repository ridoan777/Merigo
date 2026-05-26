<?php

// use App\Events\MessageSent;

use App\Http\Controllers\Billings\Transactions\WebTransactionsController;
use Illuminate\Support\Facades\Route;

// ------------------------ TIERS ------------------------
Route::prefix('admin')->middleware(['auth:web'])->group(function () {
   
   Route::get('/billings/transactions/tiers/index', [WebTransactionsController::class, 'index'])->name('backend_transactions_tiers_index');

});
// ------------------------ TIERS ------------------------
