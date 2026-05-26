<?php

namespace App\Http\Controllers\Workflows\Deals;

use App\Domain\Bars\Actions\DealSaveAction;
use App\Helpers\Auth\OwnershipAuthCheck;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\DealValidateRequest;
use App\Models\Workflows\Bar\Bar;
use App\Models\Workflows\Bar\Deal;
use App\Models\Workflows\Bar\Event;
use Illuminate\Support\{Str, Carbon};
use App\Helpers\{FileHelpers\FileManagement, IconPack, Notifications\PushNotificationHelper, UidGenerator};
use Illuminate\Support\Facades\{Log, Storage, DB};
use Illuminate\Http\Request;
use Exception;
use Throwable;

class WebDealController extends Controller
{
	public function show(Deal $deal)
	{
		$bars = Bar::status(1)->get();
		$events = Event::status(1)->get();
		return view('Admin.sidebar.Bars.deals', compact('deal', 'bars', 'events'));
	}

	public function store(DealValidateRequest $request, DealSaveAction $action)
	{
		try {
			$validated = $request->validated();
			$DEAL_UID = null;
			$existingDeal = null;

			// ------------ CHECKING EXISTANCE + UID ------------
			if (isset($validated['id']) && $validated['id']) {
				$existingDeal = Deal::findOrFail((int)$validated['id']);
				$DEAL_UID = $existingDeal->deal_uid;

			} else {
				$DEAL_UID = UidGenerator::uniqueULID($validated['name'], 12, 15, 4);
			}
			// ------------ CHECKING EXISTANCE + UID ------------

			$deal = $action->execute($validated, $DEAL_UID);

			return redirect()->back()->with('success', "Deal '{$deal->name}' is saved successfully.");
		} catch (Throwable $e) {
			return back()->with('error', 'Action error: ' . $e->getMessage())->withInput();
		}
	}

	public function delete(Deal $deal)
	{
		$name = null;
		try {
			$name = $deal?->name ?? $deal?->deal_uid ?? null;

			$deal->delete();

			return redirect()->back()->with('success', "Deal '{$deal->name}' is deleted successfully.");
		} catch (Throwable $e) {
			return back()->with('error', 'Action error: ' . $e->getMessage())->withInput();
		}
	}
}
