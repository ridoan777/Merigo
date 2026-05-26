<?php

// use App\Events\MessageSent;

use App\Http\Controllers\Billings\Customers\WebCustomersController;
use Illuminate\Support\Facades\Route;

// ------------------------ CUSTOMERS ------------------------
Route::prefix('admin')->middleware(['auth:web'])->group(function () {

   Route::get('/billings/customers/index', [WebCustomersController::class, 'index'])->name('backend_billings_customers_index');
});
// ------------------------ CSUTOMERS ------------------------


// ------------------------ SUBSCRIBERS ------------------------
Route::prefix('admin')->middleware(['auth:web'])->group(function () {

   Route::get('/billings/customers/subscribers/index', [WebCustomersController::class, 'subscribers'])->name('backend_billings_customers_subscribers');

});
// ------------------------ SUBSCRIBERS ------------------------


// ------------------------ BUYERS ------------------------
Route::prefix('admin')->middleware(['auth:web'])->group(function () {

   Route::get('/billings/customers/buyers/index', [WebCustomersController::class, 'buyers'])->name('backend_billings_customers_buyers');

});
// ------------------------ BUYERS ------------------------
