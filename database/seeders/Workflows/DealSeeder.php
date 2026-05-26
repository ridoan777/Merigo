<?php

namespace Database\Seeders\Workflows;

use App\Helpers\UidGenerator;
use App\Models\Workflows\Bar\Deal;
use App\Domain\Bars\Enums\PointsToShowEnums;
use Illuminate\Database\Seeder;

class DealSeeder extends Seeder
{
	public function run(): void
	{
		$dealNames = [
			'Free Beer', 'Buy 1 Get 1', 'Half Price Wings', 'VIP Entry', 
			'Cocktail Voucher', 'Taco Tuesday Special', 'Late Night Snack', 
			'Premium Table Booking', 'Shot on the House', 'Appetizer Platter'
		];

		$pointCosts = [50, 75, 100, 125, 150];

		$barMap = [
			1 => 5,
			2 => 6,
			3 => 7,
			4 => 8,
		];

		$totalDeals = 30;

		for ($i = 0; $i < $totalDeals; $i++) {

			$barId = array_rand($barMap);
			$name = $dealNames[array_rand($dealNames)] . ' ' . ($i + 1);
			
			// event_id randomly between 1-15 and keep some blank
			$eventId = rand(0, 1) ? rand(1, 15) : null;

			Deal::create([
				'deal_uid' => UidGenerator::uniqueULID($name, 12, 15, 4),
				'bar_id' => $barId,
				'event_id' => $eventId,
				'name' => $name,
				'point_cost' => $pointCosts[array_rand($pointCosts)],
				'to_show' => rand(0, 1) ? PointsToShowEnums::BOUNCER : PointsToShowEnums::BARTENDER,
				'expiry' => rand(0, 1) ? now()->addDays(rand(5, 60)) : null,
				'status' => 1,
			]);
		}
	}
}