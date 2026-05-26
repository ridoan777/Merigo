<?php

namespace Database\Seeders\Workflows;

use App\Helpers\UidGenerator;
use App\Models\Workflows\Bar\Bar;
use App\Models\Users\User;
use Illuminate\Database\Seeder;

class BarSeeder extends Seeder
{
   public function run(): void
   {
      $directions = [null, 'East ', null, 'West ', null, 'South ', null, 'North '];
      $barNames = ['Sky Lounge', 'Night Owl', 'Blue Moon', 'Urban Sip', 'Golden Tap', 'Neon Bar', 'The Vault', 'Chill Spot', 'Red Room', 'Midnight Hub'];

      $cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'];

      $addresses = ['123 Main St', '456 Elm St', '789 Oak St', '101 Pine St', '202 Maple Ave', '303 Cedar Rd', '404 Birch Ln', '505 Walnut St', '606 Cherry Ave', '707 Aspen Rd'];

      $barAdmins = User::whereHas('roles', fn($q) => $q->where('role_key', 'bar_admin'))->get();

      $bars = [];
      $totalBars = min(20, $barAdmins->count());

      $barPoints = [100, 150, 125, 200, 150, 250, 50, 125, 150, 200];
      $cdTime = [6, 8, 12, 12, 12, 6];

      for ($i = 0; $i < $totalBars; $i++) {

         $admin = $barAdmins[$i];

         $name = trim(($directions[array_rand($directions)] ?? '') . $barNames[array_rand($barNames)]);
         $BAR_UID = UidGenerator::uniqueULID($name, 12, 15, 4);

         $bars[] = [
            'bar_uid' => $BAR_UID,
            'bar_admin_id' => $admin->id,
            'name' => $name,

            'earning_points' => $barPoints[array_rand($barPoints)],
            'cd_time' => $cdTime[array_rand($cdTime)],

            'contact' => "+1-555-" . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
            'city' => $cities[array_rand($cities)],
            'address' => $addresses[array_rand($addresses)],
            'latitude' => null,
            'longitude' => null,
            'status' => 1,
            'image' => null,
            'created_at' => now(),
            'updated_at' => now(),
         ];
      }

      Bar::insert($bars);
   }
}