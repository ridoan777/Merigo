<?php

use App\Http\Controllers\Workflows\Merchandises\{WebMerchandiseController, WebMercRequestController};
use Illuminate\Support\Facades\Route;

// ------------------------ MERCHANDISES -------------------------

Route::prefix('admin')->middleware(['auth:web', 'verify_role_key:admin,super_admin'])->group(function () {

   Route::get('/merchandises/index', [WebMerchandiseController::class, 'index'])->name('backend_merchandise_index');

   // Route::get('/merchandises/create', [WebMerchandiseController::class, 'create'])->name('backend_merchandise_create');

   Route::get('/merchandises/{merchandise}/show', [WebMerchandiseController::class, 'show'])->name('backend_merchandise_show');


   Route::post('/merchandises/store', [WebMerchandiseController::class, 'store'])->name('backend_merchandise_store');

   Route::post('/merchandises/{merchandise}/toggle', [WebMerchandiseController::class, 'toggle'])->name('backend_merchandise_toggle');


   Route::delete('/merchandises/{merchandise}/delete', [WebMerchandiseController::class, 'delete'])->name('backend_merchandise_delete');

   Route::post('/merchandises/bulk-delete', [WebMerchandiseController::class, 'bulkDelete'])->name('backend_merchandise_bulk_delete');


   Route::post('/merchandises/datatable', [WebMerchandiseController::class, 'merchandiseDatatable'])->name('backend_merchandise_datatable');
});

// ------------------------ MERCHANDISES -------------------------


// ------------------------ MERC REQUESTS -------------------------

Route::prefix('admin')->middleware(['auth:web', 'verify_role_key:admin,super_admin'])->group(function () {

   Route::get('/merc/requests/index', [WebMercRequestController::class, 'index'])->name('backend_merc_requests_index');

   Route::get('/merc/requests/{mercRequest}/show', [WebMercRequestController::class, 'show'])->name('backend_merc_requests_show');

      Route::post('/merc/requests/{mercRequest}/store', [WebMercRequestController::class, 'store'])->name('backend_merc_requests_store');

   Route::post('/merc/requests/{mercRequest}/toggle', [WebMercRequestController::class, 'toggle'])->name('backend_merc_requests_toggle');

   Route::delete('/merc/requests/{mercRequest}/delete', [WebMercRequestController::class, 'delete'])->name('backend_merc_requests_delete');

   Route::post('/merc/requests/bulk-delete', [WebMercRequestController::class, 'bulkDelete'])->name('backend_merc_requests_bulk_delete');

   Route::post('/merc/requests/datatable', [WebMercRequestController::class, 'mercReqDatatable'])->name('backend_merc_requests_datatable');

});
// ------------------------ MERC REQUESTS -------------------------
