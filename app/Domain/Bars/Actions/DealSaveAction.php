<?php

namespace App\Domain\Bars\Actions;

use App\Models\Workflows\Bar\Deal;

class DealSaveAction
{
	public function execute(array $validated, $DEAL_UID)
	{
		$deal = Deal::updateOrCreate(
			[
				'deal_uid' => $DEAL_UID,
			],
			[
				'bar_id' => $validated['bar_id'],
				'event_id' => $validated['event_id'] ?? null,
				'name' => $validated['name'],
				'point_cost' => $validated['point_cost'],
				'to_show' => $validated['to_show'] ?? null,
				'expiry' => $validated['expiry'] ?? null,
				'status' => $validated['status'] ?? 1,
			]
		);

		return $deal;
	}
}