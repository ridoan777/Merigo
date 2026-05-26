<?php

namespace App\Http\Controllers\Workflows\Referrals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\{Middleware, HasMiddleware};
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};



class ApiReferralsController extends Controller
{
    // --------------------- Middleware ---------------------
    public static function middleware(): array
    {
        return [
            new Middleware(RoleMiddleware::using('bar_admin|admin'), only: ['allBars']),
            new Middleware(RoleMiddleware::using('admin'), only: ['singleBar']),
            // Optional: permissions
            // new Middleware(PermissionMiddleware::using('bar-list'), only: ['allBars']),
        ];
    }
}
