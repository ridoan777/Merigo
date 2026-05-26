<?php

use App\Http\Controllers\Workflows\Events\ApiEventController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:sanctum'])->group(function () {

   Route::get('/events/index', [ApiEventController::class, 'index']);
   Route::get('/events/{event}/show', [ApiEventController::class, 'show']);
   
   Route::get('/events/admin/my-evens', [ApiEventController::class, 'myEventsAdmin']);

   Route::post('/events/store', [ApiEventController::class, 'store']);
   Route::delete('/events/{event}/delete', [ApiEventController::class, 'delete']);
});
