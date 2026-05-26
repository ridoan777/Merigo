<?php

namespace App\Helpers\Settings;

use App\Helpers\ApiJsonReturnHelper;
use App\Models\Billings\Subscriptions\SubscriptionTier;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;

class SettingsGatekeeping
{
	// --------------------------------------
	public static function unblockChecking(int $userId, Model $object, int $subscriptionTierId)
	{
		$user = User::where('id', $userId)->first();
		if (!$user->status) {
			return ApiJsonReturnHelper::handle(false, 409, 'Your account is not active. The requested task is aborted. Contact support.', $object);
		}

		if (!$object->status) {
			return ApiJsonReturnHelper::handle(false, 409, 'The content/feature is not active. And the requested task is aborted. Try different content or contact support.', null);
		}

		$tier = SubscriptionTier::where('id', $subscriptionTierId)->first();
		if (!$tier->status) {
			return ApiJsonReturnHelper::handle(false, 409, 'This Tier/Package is not available. Try different package or contact support.', $object);
		}
		return null;
	}

	// --------------------------------------

}
/*
This helper checks if the user | content | subscription Tier is blocked or not.

USE CASE-1:
if(!($existingSubscription = SettingsGatekeeping::unblockChecking($user->id, $project, $tier->id))){
	return $existingSubscription;
}
*/