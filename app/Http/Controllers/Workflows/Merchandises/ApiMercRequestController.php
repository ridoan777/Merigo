<?php

namespace App\Http\Controllers\Workflows\Merchandises;

use App\Helpers\ApiJsonReturnHelper;
use App\Helpers\Errors\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\MercRedeemValidateRequest;
use App\Models\Workflows\Merchandises\Merchandise;
use App\Models\Workflows\Merchandises\MercRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\{Middleware, HasMiddleware};
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};
use Throwable;


class ApiMercRequestController extends Controller
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
            $USER = $request->user();
            $merchandise = MercRequest::userId($USER->id)->paginate(20);

            return ApiJsonReturnHelper::handle(true, 200, "All my merchandise redeem requests have been fetched sucessfully!", $merchandise);

        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching merchandise redeem requests', $e);
        }
    }
}
