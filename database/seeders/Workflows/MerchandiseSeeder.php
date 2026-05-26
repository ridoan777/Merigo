<?php

namespace Database\Seeders\Workflows;

use App\Helpers\UidGenerator;
use App\Models\Workflows\Merchandises\Merchandise;
use App\Models\Users\User;
use Illuminate\Database\Seeder;

class MerchandiseSeeder extends Seeder
{
	public function run(): void
	{
		$merchandiseNames = [
			'T-Shirt', 'Mug', 'Key Chain', 'Hoodie', 'Cap',
			'Water Bottle', 'Notebook', 'Pen Set', 'Backpack', 'Sticker Pack',
			'Poster', 'Mouse Pad', 'Phone Case', 'Tote Bag', 'Badge Set',
			'Coaster', 'Umbrella', 'Power Bank', 'Earbuds', 'Desk Organizer',
			'Stress Ball', 'Fidget Spinner', 'Coffee Maker', 'Smart Watch', 'Bluetooth Speaker',
			'Gaming Headset', 'Webcam', 'Ring Light', 'Portable Fan', 'Travel Pillow',
			'Eye Mask', 'Socks', 'Beanie', 'Scarf', 'Gloves',
			'Wallet', 'Card Holder', 'Passport Cover', 'Luggage Tag', 'Travel Adapter',
			'Journal', 'Planner', 'Calendar', 'Photo Frame', 'Candle',
			'Diffuser', 'Hand Sanitizer', 'Lip Balm', 'Sunscreen', 'Mini Fan'
		];

		$descriptions = [
			'High-quality merchandise for everyday use.',
			'Exclusive design, limited edition.',
			'Durable and stylish, a must-have item.',
			'Perfect gift for friends and family.',
			'Comfortable and practical.',
			'Show your support with this unique item.',
			'Eco-friendly and sustainable.',
			'Innovative design with premium materials.',
			'Lightweight and easy to carry.',
			'Adds a touch of elegance to your collection.',
		];

		$pointsCosts = [100, 250, 500, 750, 1000, 1500, 2000, 2500, 3000, 4000, 5000];

		$imagePath = 'Merchandise/camera.jpeg';

		$userIds = User::pluck('id')->toArray();

		if (empty($userIds)) {
			$this->command->warn('No users found to assign as merchandise creators. Skipping Merchandise seeding.');
			return;
		}

		$totalProducts = 20;

		for ($i = 0; $i < $totalProducts; $i++) {
			$name = $merchandiseNames[array_rand($merchandiseNames)] . ' ' . ($i + 1);
			$creatorId = $userIds[array_rand($userIds)];

			Merchandise::create([
				'merc_uid' => UidGenerator::uniqueULID($name, 12, 16, 4),
				'creator' => $creatorId,
				'name' => $name,
				'description' => $descriptions[array_rand($descriptions)],
				'points_cost' => $pointsCosts[array_rand($pointsCosts)],
				'image' => $imagePath,
				'status' => 1,
			]);
		}
	}
}