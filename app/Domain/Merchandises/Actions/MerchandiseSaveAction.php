<?php

namespace App\Domain\Merchandises\Actions;

use App\Models\Workflows\Merchandises\Merchandise;
use App\Helpers\FileHelpers\FileManagement;

class MerchandiseSaveAction
{
	public function execute($validated, $MERC_UID, $existingMerchandise, $USER, $isNewItem)
	{
		$DISK_FOLDER = config('filesystems.default');

		// ------------ IMAGE FILE HANDLING ------------
		$IMAGE_FOLDER = 'Merchandise/' . $MERC_UID;

		if ((isset($validated['image']) && $validated['image']) || $validated['remove_image']) {
			$image = FileManagement::handleFile(
				$validated['image'],
				"MERC-" . ($validated['name']),
				$existingMerchandise?->image ?? null,
				$IMAGE_FOLDER,
				$DISK_FOLDER,
				$validated['remove_image']
			);
		} else {
			$image = ['path' => $existingProject->image ?? null];
		}
		// ------------ IMAGE FILE HANDLING ------------

		$merchandise = Merchandise::updateOrCreate(
			[
				'merc_uid' => $MERC_UID,
			],
			[
				'name' => $validated['name'],
				'description' => $validated['description'] ?? null,
				'points_cost' => $validated['points_cost'],
				'image' => $image['path'],
				'creator' => $isNewItem ? $USER->id : ($existingMerchandise->creator ?? $USER->id),
				
				'status' => $validated['status'] ?? 1,
			]
		);

		return $merchandise;
	}
}