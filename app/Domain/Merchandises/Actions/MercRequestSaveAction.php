<?php

namespace App\Domain\Merchandises\Actions;

use App\Models\Workflows\Merchandises\MercRequest;

class MercRequestSaveAction
{
	public function execute($validated, $USER, $points_debited, $phase)
	{
		$mercRequest = MercRequest::updateOrCreate(
			[
				'id' => $validated['id'] ?? null,
			],
			[
				'merc_id' => $validated['merc_id'],
				'user_id' => $USER->id,
				'phase' => $phase,
				'points_debited' => $points_debited,
				'receiver_phone' => $validated['receiver_phone'],
				'receiver_address' => $validated['receiver_address'],
				'note' => $validated['note'] ?? null,
				'status' => $validated['status'] ?? 1,
			]
		);

		return $mercRequest;
	}
}