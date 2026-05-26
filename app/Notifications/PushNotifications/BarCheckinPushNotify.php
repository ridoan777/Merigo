<?php

namespace App\Notifications\PushNotifications;

use App\Helpers\Errors\LoggerAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class BarCheckinPushNotify extends Notification
{
	use Queueable;

	/**
	 * Create a new notification instance.
	 */
	public function __construct(
		public $isMultiple = false,	//single, multiple [single user or multi]
		public $bar,
		public $actor = null,
	) {
	}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @return array<int, string>
	 */
	public function via(object $notifiable): array
	{
		return [FcmChannel::class];
	}

	/**
	 * Get the array representation of the notification.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(object $notifiable): array
	{
		return [
			//
		];
	}

	public function toFcm(object $notifiable): FcmMessage
	{
		// LoggerAccess::showLog(['local', 'staging'], 'info', "FCM push has reached", [$notifiable]);
		$pushTitle = "Hi! {$notifiable?->name}";
		$pushBody = "{$this->actor->name} just checked in at {$this->bar->name} & earned {$this->bar->earning_points}!";
		// $pushBody = "{$this->actor->name} just checked in at {$this->bar->name} & earned {$this->bar->earning_points}! " . config('fcm.project_id') . " & " . env('FIREBASE_CREDENTIALS');

		return FcmMessage::create()
				->notification(
					FcmNotification::create()
						->title($pushTitle)
						->body($pushBody)
				)
				->data([
					'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
					'sound' => 'default',
					'priority' => 'high',
					'timestamp' => now()->toIso8601String(),
				])
				// ->token('token')
				->token($notifiable->fcm_token)
				->topic('topic');
	}
}
/*
	// ------------------------- PUSH NOTIFTCATION -------------------------
		PushNotificationHelper::singleUserPush($PROJECT_MANAGER, new ProjectSavePushNotify(false, $project, $USER));
	// ------------------------- PUSH NOTIFTCATION -------------------------
*/