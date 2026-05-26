<?php

namespace App\Domain\Bars\Enums;

enum PointsToShowEnums: string
{
	case BARTENDER = 'bartender';
	case BOUNCER = 'bouncer';

	// ----------------- LABEL -----------------
	public function label(): string
	{
		return match($this) {
			self::BARTENDER => 'Bartender',
			self::BOUNCER => 'Bouncer',
		};
	}
}