<?php

use App\Http\Controllers\Workflows\Referrals\WebReferralsController;
use Illuminate\Support\Facades\Route;


Route::prefix('admin')->middleware(['auth:web', 'verify_role_key:admin,super_admin'])->group(function () {

   Route::get('/referral/index', [WebReferralsController::class, 'index'])->name('backend_referral_index');

//    Route::get('/referral/create', [WebReferralsController::class, 'create'])->name('backend_referral_create');

//    Route::get('/referral/{singleReferral}/show', [WebReferralsController::class, 'show'])->name('backend_referral_show');


   Route::post('/referral/store', [WebReferralsController::class, 'storeRule'])->name('backend_referral_store');

//    Route::post('/referral/{referral}/toggle', [WebReferralsController::class, 'toggle'])->name('backend_referral_toggle');


//    Route::delete('/referral/{referral}/delete', [WebReferralsController::class, 'delete'])->name('backend_referral_delete');

//    Route::post('/referrals/bulk-delete', [WebReferralsController::class, 'bulkDelete'])->name('backend_referral_bulk_delete');


   Route::post('/referral/datatable', [WebReferralsController::class, 'referralDatatable'])->name('backend_referral_datatable');
});