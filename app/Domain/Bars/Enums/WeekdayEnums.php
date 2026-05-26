<?php

namespace App\Domain\Bars\Enums;

enum WeekdayEnums: string
{
	case MONDAY = 'monday';
	case TUESDAY = 'tuesday';
	case WEDNESDAY = 'wednesday';
	case THURSDAY = 'thursday';
	case FRIDAY = 'friday';
	case SATURDAY = 'saturday';
	case SUNDAY = 'sunday';

	// ----------------- LABEL -----------------
	public function label(): string
	{
		return match($this) {
			self::MONDAY => 'Monday',
			self::TUESDAY => 'Tuesday',
			self::WEDNESDAY => 'Wednesday',
			self::THURSDAY => 'Thursday',
			self::FRIDAY => 'Friday',
			self::SATURDAY => 'Saturday',
			self::SUNDAY => 'Sunday',
		};
	}
}