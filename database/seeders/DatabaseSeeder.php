<?php

namespace Database\Seeders;

use Database\Seeders\System\{PermissionRoleSeeder, PermissionSeeder,SaticSiteSeeder};
use Database\Seeders\Workflows\BarSeeder;
use Database\Seeders\Workflows\DealSeeder;
use Database\Seeders\Workflows\EventSeeder;
use Database\Seeders\Workflows\MerchandiseSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
	/**
	 * Seed the application's database.
	 */
	public function run(): void
	{
		// User::factory(10)->create();
		
		/* --------------- SYSTEM ---------------  */
		//  $this->call(SaticSiteSeeder::class);

		/* --------------- REGULAR ---------------  */
		// $this->call(PermissionRoleSeeder::class);
		// $this->call(UserSeeder::class);
		// $this->call(BulkUserSeeder::class);
		// $this->call(BarSeeder::class);
		// $this->call(EventSeeder::class);
		// $this->call(DealSeeder::class);
		// $this->call(MerchandiseSeeder::class);
		// $this->call(SubscribedUserSeeder::class);
		// $this->call(PermissionSeeder::class);
		//  $this->call(MailerSeeder::class);

	}
}
