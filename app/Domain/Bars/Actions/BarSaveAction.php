<?php

namespace App\Domain\Bars\Actions;

use App\Helpers\FileHelpers\FileManagement;
use App\Models\Workflows\Bar\Bar;

class BarSaveAction
{
	public function execute($request, array $validated, $existingBar, $BAR_UID, $USER)
	{
		// ------------ FILE HANDLING ------------
		$DISK_FOLDER = config('filesystems.default');
		$IMAGE_FOLDER = 'Bars/' . $BAR_UID;

		if ($request->hasFile('image') || $request->boolean('remove_image')) {
			$validated['image'] = FileManagement::handleFile(
				$request->file('image'),
				"BAR-" . ($BAR_UID),
				$existingBar?->image ?? null,
				$IMAGE_FOLDER,
				$DISK_FOLDER,
				$request->boolean('remove_image')
			);
		} else {
			$validated['image'] = ['path' => $existingBar->image ?? null];
		}
		// ---------------- FILE HANDLING ----------------

		$bar = Bar::updateOrCreate(
			[
				'bar_uid' => $BAR_UID,
			],
			[
				'bar_admin_id' => $validated['bar_admin_id'] ?? $USER->id,
				'name' => $validated['name'],
				'earning_points' => $validated['earning_points'] ?? 0,
				'cd_time' => $validated['cd_time'] ?? 12,
				'contact' => $validated['contact'] ?? null,
				'city' => $validated['city'] ?? null,
				'address' => $validated['address'] ?? null,
				'latitude' => $validated['latitude'] ?? null,
				'longitude' => $validated['longitude'] ?? null,
				'status' => $validated['status'] ?? 1,
				'image' => $validated['image']['path'] ?? null,
			]
		);

		return $bar;
	}
}

/*
USER CASE:
	use App\Actions\Billings\SubscriptionTierSaveAction;
	public function store(BarSaveRequest $request, BarSaveAction $action)
	{
		... ... ...
		$bar = $action->execute($validated, $BAR_UID, $USER);
	}
*/