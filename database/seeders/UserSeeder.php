<?php

namespace Database\Seeders;

use App\Helpers\UidGenerator;
use App\Models\Users\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
	public function run(): void
	{
		$users = [
			[ 'name' => 'Zeta Master', 'user_role' => 'SUPER_ADMIN', 'email' => 'admin@test.com', 'gender' => 'male' ],
			[ 'name' => 'Yeti Joker', 'user_role' => 'GUEST', 'email' => 'joker@test.com', 'gender' => 'prefer_not' ],

			[ 'name' => 'Alpha Albert', 'user_role' => 'ADMIN', 'email' => 'alpha@test.com', 'gender' => 'male' ],
			[ 'name' => 'Beta Burners', 'user_role' => 'ADMIN', 'email' => 'beta@test.com', 'gender' => 'prefer_not' ],

			[ 'name' => 'Charlie Chapline', 'user_role' => 'BAR_ADMIN', 'email' => 'charlie@test.com', 'gender' => 'male' ],
			[ 'name' => 'Delta Dallas', 'user_role' => 'BAR_ADMIN', 'email' => 'delta@test.com', 'gender' => 'female' ],
			[ 'name' => 'Echo Elijah', 'user_role' => 'BAR_ADMIN', 'email' => 'echo@test.com', 'gender' => 'male' ],
			[ 'name' => 'Fenri Fox', 'user_role' => 'BAR_ADMIN', 'email' => 'fox@test.com', 'gender' => 'female' ],

			[ 'name' => 'Gil Gamesh', 'user_role' => 'STUDENT', 'email' => 'gil@test.com', 'gender' => 'male' ],
			[ 'name' => 'Home Lander', 'user_role' => 'STUDENT', 'email' => 'home@test.com', 'gender' => 'male' ],
		];

		foreach ($users as $index => $item) {

			$roleKey = strtolower($item['user_role']);

			$role = Role::where('role_key', $roleKey)->first();

			if (!$role) continue;

			$user = User::updateOrCreate(
				['email' => $item['email']],
				[
					'user_uid' => UidGenerator::uniqueULID($item['name'], 8, 15, 4),
					'username' => UidGenerator::uniqueUsername($item['name'], 8, 4),
					'name' => $item['name'],
					'user_role' => $roleKey,	// must be role_key, not name
					'created_by' => (int)1,

					'phone' => "+{$index}-123-4567",
					'password' => Hash::make('12345678'),

					'gender' => $item['gender'],
					'city' => "San Diego",

					'remember_token' => Str::random(10),
					'email_verified_at' => now(),

					'agreed_terms' => 1,
					'term_version' => null,
					'own_referral_code' => UidGenerator::uniqueULID("REFF", 5, 15, 4),
					'invited_referral_code' => null,
					'status' => 1
				]
			);

			$user->syncRoles([$role]);
		}
	}
}