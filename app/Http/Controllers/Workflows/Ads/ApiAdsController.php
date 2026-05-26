<?php

namespace App\Http\Controllers\Workflows\Ads;

use App\Helpers\ApiJsonReturnHelper;
use App\Helpers\Errors\ExceptionHandling;
use App\Models\Workflows\Ads\Ad;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Throwable;

class ApiAdsController extends Controller
{
    public function show(Request $request)
    {
        try {
            $today = strtolower(Carbon::now()->format('l'));

            $ad = Ad::status(1)->scheduleDay($today)->first();

            if (!$ad) {
                $ad = Ad::status(1)->latest()->first();
            }

            return ApiJsonReturnHelper::handle(true, 200, "Ad fetched successfully!", $ad);

        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching ads', $e);
        }
    }
}
