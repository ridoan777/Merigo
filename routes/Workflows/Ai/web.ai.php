<?php

use App\Http\Controllers\Workflows\Ai\WebAiController;
use Illuminate\Support\Facades\Route;


// ------------ PROJECTS ------------

Route::prefix('admin')->middleware(['auth:web'])->group(function () {

   // Route::get('/ai/index', [WebAiController::class, 'index'])->name('backend_ai_index');

   // Route::get('/ai/send', [WebAiController::class, 'send']);
});
// ------------ PROJECTS ------------