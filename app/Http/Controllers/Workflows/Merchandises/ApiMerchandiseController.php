<?php

namespace App\Http\Controllers\Workflows\Merchandises;

use App\Helpers\ApiJsonReturnHelper;
use App\Helpers\Errors\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Models\Workflows\Merchandises\Merchandise;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\{Middleware, HasMiddleware};
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};
use Throwable;



class ApiMerchandiseController extends Controller
{
    // --------------------- Middleware ---------------------
    public static function middleware(): array
    {
        return [
            new Middleware(RoleMiddleware::using('student|admin'), only: ['index', 'request']),
        ];
    }
    // ---------------------------------------------------

    public function index(Request $request)
    {
        try {
            $merchandise = Merchandise::status(1)->paginate(20);

            return ApiJsonReturnHelper::handle(true, 200, "All merchandise have been fetched sucessfully!", $merchandise);

        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching merchandise', $e);
        }
    }
}
