<?php

namespace App\Domain\Ads\Actions;

use App\Helpers\FileHelpers\FileManagement;
use App\Models\Workflows\Ads\Ad;

class AdSaveAction
{
	public function execute(array $validated, string $AD_UID, ?Ad $existingAd = null)
	{
		// ------------ IMAGE FILE HANDLING ------------
		$IMAGE_FOLDER = 'Ads/' . $AD_UID;
		$DISK_FOLDER = config('filesystems.default');

		if (!empty($validated['image']) || !empty($validated['remove_image'])) {
			$validated['image'] = FileManagement::handleFile(
				$validated['image'] ?? null,
				"AD-" . ($validated['title'] ?? null),
				$existingAd?->image ?? null,
				$IMAGE_FOLDER,
				$DISK_FOLDER,
				$validated['remove_image']
			);
		} else {
			$validated['image'] = ['path' => $existingAd->image ?? null];
		}
		// ------------ IMAGE FILE HANDLING ------------

		$ad = Ad::updateOrCreate(
			[
				'ad_uid' => $AD_UID,
			],
			[
				'title' => $validated['title'],
				'description' => $validated['description'] ?? null,
				'ad_link' => $validated['ad_link'] ?? null,
				'schedule_day' => $validated['schedule_day'] ?? null,
				'image' => $validated['image']['path'] ?? null,
				'status' => $validated['status'] ?? 1,
			]
		);

		return $ad;
	}
}