<?php

namespace App\Helpers\Notifications;
use App\Helpers\Errors\LoggerAccess;
use App\Models\System\Settings\OptionSiteSetup;
use App\Models\Users\User;
use App\Notifications\PushNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class PushNotificationHelper
{
	public static function handle(?bool $isMultiple = false, $notification, ?User $singleUser = null)
	{
		if (!self::isEnabled())
			return;

		if ($isMultiple) {
			return self::allUserPush($notification);
		} else {
			return self::singleUserPush($notification, $singleUser);
		}
	}
	// ------------------------------------------------------------------------------------	

	public static function isEnabled(): bool
	{
		$push = OptionSiteSetup::where('type', 'notifications')->where('name', 'push_notification')->value('value');

		return (int) $push === 1;
	}
	// ------------------------------------------------------------------------------------	

	public static function allUserPush($notification)
	{
		User::whereNotNull('fcm_token')->chunk(100, function ($users) use ($notification) {
			foreach ($users as $recipient) {
				try {
					/** @var \App\Models\Users\User $recipient */
					$recipient->notify($notification);
				} catch (Throwable $e) {
					Log::error('FCM send failed for all users', ['user_id' => $recipient->id ?? "N/A user_id", 'error' => $e->getMessage()]);
				}
			}
		});

		return true;
	}
	// ------------------------------------------------------------------------------------	

	public static function singleUserPush($notification, User $singleUser)
	{
		if (!$singleUser || !$singleUser->fcm_token) {
			LoggerAccess::showLog(['local', 'staging'], 'info', "Single user FCM token not found", [$singleUser]);
			return;
		}

		try {
			$singleUser->notify($notification);
		} catch (Throwable $e) {
			Log::error('FCM send failed for single user', ['user_name' => $singleUser?->name ?? "N/A name", 'error' => $e->getMessage()]);
		}
		return true;
	}
}
/*
	// ------------------------- PUSH NOTIFTCATION -------------------------
	PushNotificationHelper::allUserPush(new ProjectSavePushNotify(true, $project));
	PushNotificationHelper::handle(true, new MealPlanPushNotify(false, $TARGET_PREFERENCE));

	PushNotificationHelper::singleUserPush($PROJECT_MANAGER, new ProjectSavePushNotify(false, $project, $USER));
	PushNotificationHelper::handle(false, new MealPlanPushNotify(false, $TARGET_PREFERENCE), $USER);
	// ------------------------- PUSH NOTIFTCATION -------------------------
*/