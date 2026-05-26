<?php

namespace App\Http\Controllers\Workflows\Bars;

use App\Domain\Bars\Actions\BarSaveAction;
use App\Domain\Bars\Services\HaversineDistance;
use App\Helpers\{UidGenerator, ApiJsonReturnHelper, FileHelpers\FileManagement, Notifications\PushNotificationHelper};
use App\Helpers\Auth\OwnershipAuthCheck;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\BarSaveRequest;
use App\Models\Workflows\Bar\Bar;
use App\Notifications\PushNotifications\BarPushNotify;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Helpers\Errors\ExceptionHandling;
use Illuminate\Routing\Controllers\{Middleware, HasMiddleware};
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};
use Throwable;

class ApiBarController extends Controller implements HasMiddleware
{
    use AuthorizesRequests;
    // --------------------- Middleware ---------------------
    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using('bar_delete'), only: ['delete']),
        ];
    }

    // ------------------------------------------

    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
                'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
                'coordinates' => 'nullable|string',
            ]);
            // dd($validated);

            $distance = new HaversineDistance();
            $bars = $distance->handle($validated, false);

            $bars->load(['barRelatingBackTo_User:id,user_uid,user_role,name,phone,email,avatar,city,timezone,status']);

            $bars->getCollection()->transform(function ($bar) {

                $bar->bar_admin_details = $bar->barRelatingBackTo_User;

                $bar->unsetRelation('barRelatingBackTo_User');

                return $bar;
            });

            return ApiJsonReturnHelper::handle(true, 200, "All bars have been fetched successfully", $bars);

        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching bars', $e);
        }
    }
    // ------------------------------------------

    public function myBar(Request $request)
    {
        try {
            $USER = $request->user();
            $myBars = Bar::with('barRelatingBackTo_User:id,user_uid,username,user_role,name,email,phone,avatar,city,status', 'barRelationWith_Deal', 'barRelationWith_Wallets', 'barRelationWith_Event')->userId($USER->id)->status(1)->get();

            $myBars->transform(function ($item) {
                $item->bar_admin_details = $item->barRelatingBackTo_User;
                $item->wallet_widget = [
                    'total_deals' => $item->barRelationWith_Deal->count(),
                    'total_points_given' => (int)$item->barRelationWith_Wallets->sum('total_earnings'),
                    'total_deals_exchanged' => (int)$item->barRelationWith_Wallets->sum('total_spent'),
                    'total_wallet_balance' => (int)$item->barRelationWith_Wallets->sum('balance'),
                    'total_active_events' => $item->barRelationWith_Event->count(),
                ];

                $item->unsetRelation('barRelatingBackTo_User');
                $item->unsetRelation('barRelationWith_Deal');
                $item->unsetRelation('barRelationWith_Wallets');
                $item->unsetRelation('barRelationWith_Event');

                return $item;
            });

            return ApiJsonReturnHelper::handle(true, 200, "My bar details has been fetched successfully!", $myBars);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching my bar details', $e);
        }
    }
    // ------------------------------------------

    public function show(Bar $singleBar)
    {
        try {
            $singleBar->load('barRelatingBackTo_User:id,username,user_role,name,email,phone,avatar,city,status')->status(1);

            if ($singleBar) {
                $singleBar->manager = $singleBar->barRelatingBackTo_User;
                unset($singleBar->barRelatingBackTo_User);
            }

            return ApiJsonReturnHelper::handle(true, 200, "A bar details has been fetched successfully!", $singleBar);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching a bar details', $e);
        }
    }
    // ------------------------------------------

    public function store(BarSaveRequest $request, BarSaveAction $action)
    {
        try {
            $validated = $request->validated();
            $USER = $request->user();

            $BAR_UID = null;
            $existingBar = null;

            // ------------ CHECKING EXISTANCE + UID ------------
            if (isset($validated['id']) && $validated['id']) {
                $this->authorize('bar_update');
                $existingBar = Bar::find($validated['id']);
                $BAR_UID = $existingBar->bar_uid;
                OwnershipAuthCheck::ownerVsOwner($existingBar->bar_admin_id, $USER->id);
            } else {
                $this->authorize('bar_create');
                $BAR_UID = UidGenerator::uniqueULID($validated['name'], 12, 15, 4);
            }
            // ------------ CHECKING EXISTANCE + UID ------------

            $bar = $action->execute($request, $validated, $existingBar, $BAR_UID, $USER);

            // ------------------------- PUSH NOTIFTCATION -------------------------
            PushNotificationHelper::handle(false, new BarPushNotify(false, $bar), $USER);
            // ------------------------- PUSH NOTIFTCATION -------------------------

            return ApiJsonReturnHelper::handle(true, 200, "Bar '{$bar->name}' is saved successfully!", $bar);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('saving bar', $e);
        }
    }

    public function delete(Request $request, Bar $singleBar)
    {

        $name = null;
        $name = null;
        $imageFolder = null;
        try {
            $USER = $request->user();
            $name = $singleBar?->name ?? $singleBar?->bar_uid ?? null;
            ;

            OwnershipAuthCheck::ownerVsOwner($singleBar->bar_admin_id, $USER->id);

            if ($singleBar?->image) {
                $DISK_FOLDER = config('filesystems.default');
                $imageFolder = dirname($singleBar->image);
                FileManagement::deleteFolder($imageFolder, $DISK_FOLDER);
            }

            $singleBar->delete();

            return ApiJsonReturnHelper::handle(true, 200, 'Bar deleted successfully!', null);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('deleting bar', $e);
        }
    }
}
