<?php

use App\Http\Controllers\Workflows\Events\WebEventController;
use Illuminate\Support\Facades\Route;


// ------------ EVENTS ------------

Route::prefix('admin')->middleware(['auth:web'])->group(function () {


   Route::get('/event/index', [WebEventController::class, 'index'])->name('backend_event_index');

   Route::get('/event/create', [WebEventController::class, 'create'])->name('backend_event_create');

   Route::get('/event/{event}/show', [WebEventController::class, 'show'])->name('backend_event_show');

   Route::post('/event/store', [WebEventController::class, 'store'])->name('backend_event_store');

   Route::post('/events/{event}/toggle', [WebEventController::class, 'toggle'])->name('backend_event_toggle');

   Route::delete('/events/{event}', [WebEventController::class, 'destroy'])->name('backend_event_delete');

   Route::post('/events/bulk-delete', [WebEventController::class, 'bulkDelete'])->name('backend_events_bulk_delete');
   
   Route::post('/event/datatable', [WebEventController::class, 'eventDatatable'])->name('backend_event_datatable');
});
// ------------ EVENTS ------------