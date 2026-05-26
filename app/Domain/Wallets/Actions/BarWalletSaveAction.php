<?php

namespace App\Domain\Wallets\Actions;

use App\Models\Billings\Wallets\BarWallet;

class BarWalletSaveAction
{
	public function execute($payload, int $barId, $BAR_WALLET_UID, $USER)
	{
		$data = is_array($payload)
			? $payload
			: [
				'balance' => $payload?->balance,
				'total_earnings' => $payload?->total_earnings,
				'total_spent' => $payload?->total_spent,
				'status' => $payload?->status,
			];

		$wallet = BarWallet::updateOrCreate(
			[
				'user_id' => $USER->id,
				'bar_id' => $barId,
			],
			[
				'bar_wallet_uid' => $BAR_WALLET_UID,

				'balance' => $data['balance'] ?? 0,
				'total_earnings' => $data['total_earnings'] ?? 0,
				'total_spent' => $data['total_spent'] ?? 0,
				'status' => $data['status'] ?? 1,
			]
		);

		return $wallet;
	}
}