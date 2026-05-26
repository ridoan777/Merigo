<?php

namespace App\Http\Controllers\Billings\Tiers;

use App\Actions\Billings\SubscriptionTierSaveAction;
use App\Helpers\{FileHelpers\FileManagement, Notifications\InAppNotificationHelper, Notifications\PushNotificationHelper, UidGenerator};
use App\Http\Controllers\Controller;
use App\Http\Requests\Billings\TierValidationRequest;
use App\Models\Billings\Subscriptions\SubscriptionTier;
use App\Models\System\Settings\OptionSiteSetup;
use App\Models\Workflows\Projects\Project;
use Exception;
use Throwable;

class WebSubscriptionTierController extends Controller
{
	// this controller stores both web & mobile version of tiers
	public function index()
	{
		$projects = Project::status(1)->get();
		$webTiers = SubscriptionTier::platform('web')->get();
		$mobileTiers = SubscriptionTier::platform('mobile')->get();

		return view('Admin.sidebar.Billings.Subscriptions.index', compact('projects', 'webTiers', 'mobileTiers'));
	}

	public function show(SubscriptionTier $tier)
	{
		$projects = Project::status(1)->get();
		return view('Admin.sidebar.Billings.Subscriptions.show', compact('projects', 'tier'));
	}

	public function store(TierValidationRequest $request, SubscriptionTierSaveAction $action)
	{
		// dd("validated", $request->all());
		try {
			$validated = $request->validated();
			$DISK_FOLDER = config('filesystems.default');
			$existingTier = null;
			$maxTierId = SubscriptionTier::max('id') ?? 0;

			// ------------ CHECKING EXISTANCE ------------
			if (isset($validated['id']) && $validated['id']) {
				$existingTier = SubscriptionTier::find($validated['id']);
			}
			// ------------ CHECKING EXISTANCE ------------

			// ------------ FILE HANDLING ------------
			$IMAGE_FOLDER = 'Subscriptions/tiers/';

			if ($request->hasFile('image') || $request->boolean('remove_image')) {
				$validated['image'] = FileManagement::handleFile(
					$request->file('image'),
					($maxTierId + 1) . "-TIER-{$validated['platform']}-" . ($validated['final_price'] ?? 'no-price'),
					$existingTier->image ?? null,
					$IMAGE_FOLDER,
					$DISK_FOLDER,
					$request->boolean('remove_image')
				);
			} else {
				$validated['image'] = ['path' => $existingTier->image ?? null];
			}
			// ------------ FILE HANDLING ------------

			$tier = $action->execute($validated);

			// ------------------------- PUSH NOTIFTCATION -------------------------
			if(isset($validated['id']) && $validated['id']){
				$push = OptionSiteSetup::where('type', 'notifications')->where('name', 'push_notification')->first();
				if ((int) $push->value === 1) {
					PushNotificationHelper::allUserPush($request?->user(), null, "{$tier->name} | {$tier->duration} days | '$'{$tier->final_price}", "A new subscription package has been created. Go check it out");
				}
			}
			// ------------------------- PUSH NOTIFTCATION -------------------------

			return redirect()->back()->with('success', "Subscription tier '{$tier->name}' is saved successfully.");
		} catch (Throwable $e) {
			return back()->with('error', 'Action error: ' . $e->getMessage())->withInput();
		}
	}


   public function delete(SubscriptionTier $tier)
   {
      $name = null;
      try {
         $name = $tier?->name ?? null;
         $DISK_FOLDER = config('filesystems.default');

         FileManagement::deleteFile($tier->image, $DISK_FOLDER);
         $tier->delete();

         return redirect()->route('backend_subscription_tiers_index')->with('success', "Subscription tier '{$name}' has been deleted successfully.");
      } catch (Exception $e) {
         return redirect()->back()->with('error', 'Action Error: ' . $e->getMessage())->withInput();
      }
   }
}
