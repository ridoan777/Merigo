<?php

use App\Http\Controllers\Workflows\Bars\ApiBarController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:sanctum'])->group(function () {

   // Route::post('/bars/index', [ApiBarController::class, 'index']);  
   Route::get('/bars/index', [ApiBarController::class, 'index']);  

   Route::get('/bars/my-bar', [ApiBarController::class, 'myBar']);
   Route::get('/bars/single-bar/{singleBar}/show', [ApiBarController::class, 'show']);

   Route::post('/bars/store', [ApiBarController::class, 'store']);
   Route::delete('/bars/{singleBar}/delete', [ApiBarController::class, 'delete']);
});
