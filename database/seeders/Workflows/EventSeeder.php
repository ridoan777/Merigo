<?php

namespace Database\Seeders\Workflows;

use App\Helpers\UidGenerator;
use App\Models\Workflows\Bar\{Event,EventGallery};
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
	public function run(): void
	{
		$eventDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

		$eventNames = ['DJ Night','Live Concert','Happy Hour','Beer Fest','Ladies Night','Retro Party','EDM Blast','Cocktail Evening','Open Mic','Folk Festival'];

		$descriptions = [
			'Enjoy live music and exclusive drinks.',
			'Special discounts and exciting rewards.',
			'An unforgettable nightlife experience.',
			'Party hard with DJs and giveaways.',
			'Exclusive event with premium atmosphere.',
		];

		$galleryImages = [
			'Events/concert.jpg',
			'Events/folklife.png',
		];

		$eventBarMap = [
			1 => 5,
			2 => 6,
			3 => 7,
			4 => 8,
		];

		$eventGalleries = [];

		$totalEvents = 20;

		for ($i = 0; $i < $totalEvents; $i++) {

			$barId = array_rand($eventBarMap);
			$creatorId = $eventBarMap[$barId];

			$name = $eventNames[array_rand($eventNames)] . ' ' . ($i + 1);

			$event = Event::create([
				'event_uid' => UidGenerator::uniqueULID($name, 12, 15, 4),
				'bar_id' => $barId,
				'creator_id' => $creatorId,
				'event_day' => $eventDays[array_rand($eventDays)],
				'name' => $name,
				'description' => $descriptions[array_rand($descriptions)],
				'points_giveaway' => rand(50, 250),
				'image' => null,
				'expiry' => rand(0, 1) ? now()->addDays(rand(5, 60)) : null,
				'status' => 1,
			]);

			$totalGallery = rand(0, 3);

			for ($g = 0; $g < $totalGallery; $g++) {

				$image = $galleryImages[array_rand($galleryImages)];

				$eventGalleries[] = [
					'event_id' => $event->id,
					'filename' => basename($image),
					'filepath' => $image,
					'metadata' => json_encode([
						'type' => 'dummy',
						'source' => asset($image),
					]),
					'status' => 1,
					'created_at' => now(),
					'updated_at' => now(),
				];
			}
		}

		EventGallery::insert($eventGalleries);
	}
}