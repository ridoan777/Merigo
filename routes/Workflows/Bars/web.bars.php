<?php

use App\Http\Controllers\Workflows\Bars\WebBarController;
use App\Http\Controllers\Workflows\Projects\WebProjectController;
use Illuminate\Support\Facades\Route;


// ------------ BARS ------------

Route::prefix('admin')->middleware(['auth:web'])->group(function () {


   Route::get('/bar/index', [WebBarController::class, 'index'])->name('backend_bar_index');

   Route::get('/bar/create', [WebBarController::class, 'create'])->name('backend_bar_create');

   Route::get('/bar/{bar}/show', [WebBarController::class, 'show'])->name('backend_bar_show');

   Route::post('/bar/store', [WebBarController::class, 'store'])->name('backend_bar_store');

   Route::post('/bars/{bar}/toggle', [WebBarController::class, 'toggle'])->name('backend_bar_toggle');

   Route::delete('/bars/{bar}', [WebBarController::class, 'destroy'])->name('backend_bar_delete');

   Route::post('/bars/bulk-delete', [WebBarController::class, 'bulkDelete'])->name('backend_bars_bulk_delete');
   
   Route::post('/bar/datatable', [WebBarController::class, 'barDatatable'])->name('backend_bar_datatable');
});
// ------------ BARS ------------