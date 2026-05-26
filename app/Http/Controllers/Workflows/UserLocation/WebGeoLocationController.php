<?php

namespace App\Http\Controllers\Workflows\UserLocation;

use App\Helpers\ApiJsonReturnHelper;
use App\Http\Controllers\Controller;
use App\Models\Workflows\Location\Location;
use Illuminate\Http\Request;
use App\Helpers\Errors\ExceptionHandling;
use App\Models\Workflows\Location\UserLocation;
use Throwable;
use Illuminate\Routing\Controllers\{Middleware, HasMiddleware};
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};


class WebGeoLocationController extends Controller
{
    // --------------------- Middleware ---------------------
    public static function middleware(): array
    {
        return [
            new Middleware(RoleMiddleware::using('user_id|admin'), only: ['location']),
            new Middleware(RoleMiddleware::using('admin'), only: ['singleBar']),
            // Optional: permissions
            // new Middleware(PermissionMiddleware::using('bar-list'), only: ['allBars']),
        ];
    }

    public function saveLocation(Request $request)
    {
        return response()->json([
            'lat' => $request->lat,
            'lng' => $request->lng,
            'name' => $request->name
        ]);
    }
}
