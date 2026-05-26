<?php

namespace App\Domain\Merchandises\Enums;

enum MercRequestPhaseEnums: string
{
	case PENDING = 'pending';
	case DECLINED = 'declined';
	case INCOMING = 'incoming';
	case COMPLETED = 'completed';
	case CANCELLED = 'cancelled';

	// ----------------- LABEL -----------------
	public function label(): string
	{
		return match ($this) {
			self::PENDING => 'Pending',
			self::DECLINED => 'Declined',
			self::INCOMING => 'Incoming',
			self::COMPLETED => 'Completed',
			self::CANCELLED => 'Cancelled',
		};
	}
}