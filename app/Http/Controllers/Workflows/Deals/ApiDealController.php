<?php

namespace App\Http\Controllers\Workflows\Deals;

use App\Domain\Bars\Actions\DealSaveAction;
use App\Helpers\{UidGenerator, ApiJsonReturnHelper, FileHelpers\FileManagement};
use App\Helpers\Auth\OwnershipAuthCheck;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\DealValidateRequest;
use App\Models\Workflows\Bar\{Bar, Deal};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Helpers\Errors\ExceptionHandling;
use Illuminate\Routing\Controllers\{Middleware, HasMiddleware};
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware};
use Throwable;

class ApiDealController extends Controller implements HasMiddleware
{
    use AuthorizesRequests;
    // --------------------- Middleware ---------------------
    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using('deals_delete'), only: ['delete']),
        ];
    }

    // ------------------------------------------

    public function index()
    {
        try {
            $deals = Deal::with([
                'dealRelatingBackTo_Bar:id,bar_uid,bar_admin_id,name,image,address,status',
                'dealRelatingBackTo_Bar.barRelatingBackTo_User:id,username,user_role,name,email,phone,avatar,city,status',
                'dealRelatingBackTo_Event'
            ])->where('status', 1)->whereHas('dealRelatingBackTo_Bar', fn($q) => $q->where('status', 1))->groupBy('bar_id')->paginate(20);

            $deals->transform(function ($item) {
                $item->bar_details = $item->dealRelatingBackTo_Bar;
                $item->manager_details = $item->bar_details->barRelatingBackTo_User?->only(['id', 'user_uid', 'username', 'user_role', 'name', 'email', 'phone', 'avatar', 'city', 'status', 'avatar_url']);
                $item->event_details = $item->dealRelatingBackTo_Event;
                unset($item->dealRelatingBackTo_Bar);
                unset($item->barRelatingBackTo_User);
                unset($item->bar_details->barRelatingBackTo_User);
                unset($item->dealRelatingBackTo_Event);

                return $item;
            });

            return ApiJsonReturnHelper::handle(true, 200, 'All deals have been fetched successfully!', $deals);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching all deals', $e);
        }
    }
    // ------------------------------------------

    public function indexByBars()
    {
        try {
            $deals = Deal::with([
                'dealRelatingBackTo_Bar:id,bar_uid,bar_admin_id,name,image,address,status',
                'dealRelatingBackTo_Bar.barRelatingBackTo_User:id,user_uid,username,user_role,name,email,phone,avatar,city,status',
                'dealRelatingBackTo_Event'
            ])->where('status', 1)->whereHas('dealRelatingBackTo_Bar', fn($q) => $q->where('status', 1))->paginate(20);

            $groupedDeals = $deals->getCollection()->groupBy('bar_id')->map(function ($items) {
                $first = $items->first();

                $barDetails = $first->dealRelatingBackTo_Bar;
                $managerDetails = $barDetails?->barRelatingBackTo_User?->only(['id', 'user_uid', 'username', 'user_role', 'name', 'email', 'phone', 'avatar', 'city', 'status', 'avatar_url']);

                if ($barDetails) {
                    $barDetails->unsetRelation('barRelatingBackTo_User');
                }

                $dealItems = $items->map(function ($deal) {
                    $deal->event_details = $deal->dealRelatingBackTo_Event;

                    $deal->unsetRelation('dealRelatingBackTo_Bar');
                    $deal->unsetRelation('dealRelatingBackTo_Event');

                    unset($deal->bar_details, $deal->manager_details);

                    return $deal;
                })->values();

                return [
                    'bar_id' => $first->bar_id,
                    'bar_details' => $barDetails,
                    'manager_details' => $managerDetails,
                    'deals' => $dealItems,
                ];
            })->values();

            $deals->setCollection($groupedDeals);

            return ApiJsonReturnHelper::handle(true, 200, 'All deals have been fetched successfully!', $deals);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching all deals', $e);
        }
    }
    // ------------------------------------------

    public function myDealsAdmin(Request $request)
    {
        try {
            $USER = $request->user();
            $myDeals = Deal::with('dealRelatingBackTo_Bar.barRelatingBackTo_User:id,user_uid,username,user_role,name,email,phone,avatar,city,status')
                ->whereHas('dealRelatingBackTo_Bar', fn($q) => $q->where('bar_admin_id', $USER->id))
                ->get();

            $myDeals->transform(function ($item) {
                $item->bar_details = $item->dealRelatingBackTo_Bar;
                $item->bar_admin_details = $item->bar_details->barRelatingBackTo_User;

                $item->unsetRelation('dealRelatingBackTo_Bar');
                $item->bar_details->unsetRelation('barRelatingBackTo_User');

                return $item;
            });

            return ApiJsonReturnHelper::handle(true, 200, "All my deals have been fetched successfully!", $myDeals);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching all my deals', $e);
        }
    }
    // ------------------------------------------

    public function show(Deal $deal)
    {
        try {
            $deal->load(['dealRelatingBackTo_Bar.barRelatingBackTo_User:id,username,user_role,name,email,phone,avatar,city,status', 'dealRelatingBackTo_Event'])->status(1);

            if ($deal) {
                $deal->bar_details = $deal->dealRelatingBackTo_Bar;
                $deal->manager_details = $deal->bar_details->barRelatingBackTo_User;
                $deal->event_details = $deal->dealRelatingBackTo_Event;
                unset($deal->dealRelatingBackTo_Bar);
                unset($deal->barRelatingBackTo_User);
                unset($deal->bar_details->barRelatingBackTo_User);
                unset($deal->dealRelatingBackTo_Event);

                return $deal;
            }

            return ApiJsonReturnHelper::handle(true, 200, "A bar deal has been fetched successfully!", $deal);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching a deal', $e);
        }
    }
    // ------------------------------------------

    public function showBarDeals(Bar $bar)
    {
        try {
            $bar->load(['barRelatingBackTo_User:id,username,user_role,name,email,phone,avatar,city,status', 'barRelationWith_Deal']);

            if ($bar) {
                $bar->bar_manager = $bar->barRelatingBackTo_User;
                $bar->bar_deals = $bar->barRelationWith_Deal;
                unset($bar->barRelatingBackTo_User);
                unset($bar->barRelationWith_Deal);
            }

            return ApiJsonReturnHelper::handle(true, 200, "All deals under this bar have been fetched successfully!", $bar);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('fetching bar deals', $e);
        }
    }
    // ------------------------------------------

    public function store(DealValidateRequest $request, DealSaveAction $action)
    {
        try {
            $validated = $request->validated();
            $USER = $request->user();

            $DEAL_UID = null;
            $existingDeal = null;

            // ------------ CHECKING EXISTANCE + UID ------------
            if (isset($validated['id']) && $validated['id']) {
                $this->authorize('deals_update');
                $existingDeal = Deal::findOrFail((int)$validated['id']);
                $DEAL_UID = $existingDeal->deal_uid;

            } else {
                $this->authorize('deals_create');
                $DEAL_UID = UidGenerator::uniqueULID($validated['name'], 12, 15, 4);
            }

            $targetBar = Bar::findOrFail((int)$validated['bar_id']);
            OwnershipAuthCheck::ownerVsOwner($targetBar->bar_admin_id, $USER->id);
            // ------------ CHECKING EXISTANCE + UID ------------

            $deal = $action->execute($validated, $DEAL_UID);

            return ApiJsonReturnHelper::handle(true, 200, "A deal '{$deal->name}' is saved successfully!", $deal);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('saving a deal', $e);
        }
    }

    public function delete(Request $request, Deal $deal)
    {
        $name = null;
        try {
            $USER = $request->user();
            $name = $deal?->name ?? $deal?->deal_uid ?? null;

            OwnershipAuthCheck::ownerVsOwner($deal?->dealRelatingBackTo_Bar?->bar_admin_id, $USER->id);
            $deal->delete();

            return ApiJsonReturnHelper::handle(true, 200, "A deal {$name} is deleted successfully!", null);
        } catch (Throwable $e) {
            return ExceptionHandling::handle('deleting a deal', $e);
        }
    }
}
