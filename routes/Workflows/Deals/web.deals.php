<?php

use App\Http\Controllers\Workflows\Deals\WebDealController;
use Illuminate\Support\Facades\Route;


// ------------ BARS ------------

Route::prefix('admin')->middleware(['auth:web'])->group(function () {

   Route::get('/deals/{deal}/show', [WebDealController::class, 'show'])->name('backend_deal_show');

   Route::post('/deals/store', [WebDealController::class, 'store'])->name('backend_deal_store');

   Route::delete('/deals/{deal}/delete', [WebDealController::class, 'delete'])->name('backend_deal_delete');
});
// ------------ BARS ------------