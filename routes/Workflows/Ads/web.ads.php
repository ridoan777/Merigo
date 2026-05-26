<?php

use App\Http\Controllers\Workflows\Ads\WebAdsController;
use Illuminate\Support\Facades\Route;


Route::prefix('admin')->middleware(['auth:web', 'verify_role_key:admin,super_admin'])->group(function () {

   Route::get('/ads/index', [WebAdsController::class, 'index'])->name('backend_ads_index');

   Route::get('/ads/create', [WebAdsController::class, 'create'])->name('backend_ads_create');

   Route::get('/ads/{singleAd}/show', [WebAdsController::class, 'show'])->name('backend_ads_show');

   Route::post('/ads/store', [WebAdsController::class, 'store'])->name('backend_ads_store');

   Route::post('/ads/{singleAd}/toggle', [WebAdsController::class, 'toggle'])->name('backend_ads_toggle');

   Route::delete('/ads/{singleAd}/delete', [WebAdsController::class, 'delete'])->name('backend_ads_delete');

   Route::post('/adss/bulk-delete', [WebAdsController::class, 'bulkDelete'])->name('backend_ads_bulk_delete');

   Route::post('/ads/datatable', [WebAdsController::class, 'adsDatatable'])->name('backend_ads_datatable');
});