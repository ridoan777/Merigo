<?php

namespace App\Domain\Bars\Actions;

use App\Helpers\FileHelpers\FileManagement;
use App\Models\Users\User;
use App\Models\Workflows\Bar\Event;

class EventSaveAction
{
	public function execute(array $validated, string $EVENT_UID, ?Event $existingEvent, User $USER, ?bool $isNewItem = false)
	{
		// $existingEvent = (isset($validated['id']) && $validated['id']) ? Event::find($validated['id']) : null;

		$event = Event::updateOrCreate(
			[
				'event_uid' => $EVENT_UID,
			],
			[
				'bar_id' => $validated['bar_id'],
				'creator_id' => $isNewItem ? $USER->id : $existingEvent->creator_id,
				'event_day' => $validated['event_day'] ?? null,
				'name' => $validated['name'],
				'description' => $validated['description'] ?? null,
				'points_giveaway' => $validated['points_giveaway'],
				'image' => $validated['image']['path'] ?? null,
				'expiry' => $validated['expiry'] ?? null,
				'status' => $validated['status'] ?? 1,
			]
		);

		return $event;
	}
}