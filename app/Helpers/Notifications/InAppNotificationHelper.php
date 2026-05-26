<?php

namespace App\Helpers\Notifications;

use App\Helpers\Errors\ExceptionHandling;
use App\Helpers\Errors\LoggerAccess;
use App\Models\System\Notifications\InAppNotification;
use App\Models\System\Settings\{OptionSiteSetup,UserAppPreference};
use Illuminate\Support\Facades\Log;
use Throwable;

class InAppNotificationHelper
{
	public static function createInAppNotify(
		$USER,
		$triggerPlace,
		$type,	// login, order, system, message, payment, update
		$severity,	// info, alert, warning, critical
		$MESSAGE,
		$focus_person,
		$focus_avatar,
		$action_url,
		$action_label,
		$force_show = false
	) {
		try {
			$IA_NOTIFICATION = null;

			// ------------------ CHECK SETTINGS (ADMIN+USER) ------------------
			$adminSettings = OptionSiteSetup::where('type', 'notifications')->where('name', 'in_app_notification')->where('value', 1)->first();
			$userPreferredSettings = UserAppPreference::where('user_id', $USER->id)->inAppNotification(1)->first();
			// ------------------ CHECK SETTINGS ------------------
			$totalNotifications = InAppNotification::count();

			if ($totalNotifications > 10000) {
				// Delete oldest 5000 notifications safely
				$oldIds = InAppNotification::orderBy('id', 'asc')->limit(5000)->pluck('id')->toArray();
				InAppNotification::whereIn('id', $oldIds)->delete();

				Log::info('InAppNotification cleanup executed', ['deleted_count' => count($oldIds)]);
			}
			if ($adminSettings) {
				if ($force_show || $userPreferredSettings) {
					$IA_NOTIFICATION = InAppNotification::create([
						'user_id' => $USER->id,

						'trigger_place' => $triggerPlace,

						'type' => $type?? 'system',
						'severity' => $severity ?? 'info',

						'message' => $MESSAGE ?? null,
						'focus_name' => $focus_person ?? null,
						'focus_image' => $focus_avatar ?? null,

						'action_label' => $action_label ?? null,
						'action_url' => $action_url ?? null,

						'status' => 1,
					]);
				}
			}

			return $IA_NOTIFICATION;
		} catch (Throwable $e) {
			Log::info("In-app notification error" . $e->getMessage());
			return ExceptionHandling::handle('sending in-app-notification.', $e);
		}
	}
}

/* 
USE-CASE:
	InAppNotificationHelper::createInAppNotify($user, 'apiAuth_password_reset', 'password', 'alert', 'Your password was reset at ' . now()->toDayDateTimeString(), $user->name, $user->avatar_url, null, null);
*/