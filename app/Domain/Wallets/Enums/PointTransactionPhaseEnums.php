<?php

namespace App\Domain\Wallets\Enums;

enum PointTransactionPhaseEnums: string
{
	case PROGRESS = 'progress';
	case COMPLETED = 'completed';
	case FAILED = 'failed';

	// ----------------- LABEL -----------------
	public function label(): string
	{
		return match ($this) {
			self::PROGRESS => 'Progress',
			self::COMPLETED => 'Completed',
			self::FAILED => 'Failed',
		};
	}
}