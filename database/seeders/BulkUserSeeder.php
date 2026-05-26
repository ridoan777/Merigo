<?php

namespace Database\Seeders;

use App\Helpers\UidGenerator;
use App\Models\Users\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class BulkUserSeeder extends Seeder
{
	public function run(): void
	{
		$letters = range('A', 'Z');

		$names = ['Harris', 'Irwin', 'Johnson', 'King', 'Lewis', 'Morris', 'Nelson', 'Owens', 'Parker', 'Quinn', 'Roberts', 'Stevens', 'Turner', 'Underwood', 'Vance', 'Walker', 'Xavier', 'Young', 'Zimmer'];

		$cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'];

		$gender = ['male', 'female', 'prefer_not'];

		$avatars = ['user-avatars/haninabz-school.png', 'user-avatars/fountain_pen.jpg', 'user-avatars/rose.jpg', null];

		$barAdminRole = Role::where('role_key', 'bar_admin')->first();
		$studentRole = Role::where('role_key', 'student')->first();

		$password = Hash::make('12345678');

		$users = [];
		$pivot = [];

		$startIndex = array_search('K', $letters);
		$totalUsers = 200;

		for ($i = 0; $i < $totalUsers; $i++) {

			$letterIndex = ($startIndex + $i) % count($letters);
			$letter = $letters[$letterIndex];
			$name = ($names[$letterIndex] ?? 'User') . " " . $letter;

			$role = $i < 3 ? $barAdminRole : $studentRole;

			$email = strtolower(str_replace(' ', '', $name)) . $i . '@test.com';

			$users[] = [
				'user_uid' => UidGenerator::uniqueULID($name, 8, 15, 4),
				'username' => UidGenerator::uniqueUsername($name, 8, 4),
				'name' => $name,
				'user_role' => $role->role_key,
				'created_by' => 3,
				'password' => $password,

				'gender' =>  $gender[array_rand($gender)],
				'phone' => "+123-" . str_pad((string)$i, 5, '0', STR_PAD_LEFT),
				'city' => $cities[array_rand($cities)],
				'avatar' => $avatars[array_rand($avatars)],

				'remember_token' => Str::random(10),
				'email' => $email,
				'email_verified_at' => now(),
				'own_referral_code' => UidGenerator::uniqueULID("REFF", 5, 15, 4),
				'invited_referral_code' => null,
				'status' => 1,
				'created_at' => now(),
				'updated_at' => now(),
			];
		}

		// Bulk insert users
		User::insert($users);

		// Fetch inserted users once
		$insertedUsers = User::whereIn('email', array_column($users, 'email'))->get();

		foreach ($insertedUsers as $user) {
			$roleId = $user->user_role === 'bar_admin' ? $barAdminRole->id : $studentRole->id;

			$pivot[] = [
				'role_id' => $roleId,
				'model_type' => User::class,
				'model_id' => $user->id
			];
		}

		DB::table('model_has_roles')->insert($pivot);
	}
}