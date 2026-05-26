<?php

namespace App\Domain\Wallets\Actions;

use App\Models\Billings\Wallets\MerigoWallet;

class MerigoWalletSaveAction
{
	public function execute($payload, $MERIGO_WALLET_ID, $USER)
	{
		logger('8-inside MerigoWalletSaveAction', [$MERIGO_WALLET_ID]);
		$data = is_array($payload)
			? $payload
			: [
				'balance' => $payload?->balance,
				'total_earnings' => $payload?->total_earnings,
				'total_spent' => $payload?->total_spent,
				'status' => $payload?->status,
			];

		$wallet = MerigoWallet::updateOrCreate(
			[
				'user_id' => $USER->id,
			],
			[
				'merigo_wallet_uid' => $MERIGO_WALLET_ID,
				'total_referrals' => $data['total_referrals'] ?? 0,
				'balance' => $data['balance'] ?? 0,
				'total_earnings' => $data['total_earnings'] ?? 0,
				'total_spent' => $data['total_spent'] ?? 0,
				'status' => $data['status'] ?? 1,
			]
		);

		return $wallet;
	}
}