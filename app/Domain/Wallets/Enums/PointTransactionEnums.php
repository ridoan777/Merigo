<?php

namespace App\Domain\Wallets\Enums;

enum PointTransactionEnums: string
{
	case CHECKIN = 'checkin';	// earning from bar
	case DEAL = 'deal';	// spend to bar deals
	case REFERRAL = 'referral';	// earning from Merigo
	case MERCHANDISE = 'merchandise';	// spending to merigo store

	// ----------------- LABEL -----------------
	public function label(): string
	{
		return match ($this) {
			self::CHECKIN => 'Checkin',
			self::DEAL => 'Deal',
			self::REFERRAL => 'Referral',
			self::MERCHANDISE => 'Merchandise',
		};
	}

	public function isEarning(): bool
	{
		return match ($this) {
			self::CHECKIN,
			self::REFERRAL => true,

			default => false,
		};
	}

	public function isSpending(): bool
	{
		return match ($this) {
			self::DEAL,
			self::MERCHANDISE => true,

			default => false,
		};
	}
}