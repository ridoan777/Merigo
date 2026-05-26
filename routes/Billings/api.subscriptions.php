<?php

use App\Http\Controllers\Billings\Webhooks\{RevenueCatWebhookController,StripeWebhookController};
use Illuminate\Support\Facades\{Auth, Route};


// ------------------------- REVENUECAT + WEBHOOKS -------------------------
Route::prefix('revenuecat')->group(function () {

   Route::post('/sandbox/webhook', [RevenueCatWebhookController::class, 'handleWebhook']);

});
// ------------------------- REVENUECAT + WEBHOOKS -------------------------