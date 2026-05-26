<?php

namespace App\Domain\Bars\Services;

use App\Models\Workflows\Bar\Bar;

class HaversineDistance
{
   public function handle($validated = null, bool $strict = true)
   {
      $latitude = null;
      $longitude = null;
      // ----------------- COORDINATE PARSING -----------------
      if (!empty($validated['coordinates'])) {
         $coordinates = trim($validated['coordinates']);

         preg_match(
            '/^\s*(-?\d+(\.\d+)?)\s*[,\s]\s*(-?\d+(\.\d+)?)\s*$/',
            $coordinates,
            $matches
         );

         $latitude = isset($matches[1]) ? (float)$matches[1] : null;
         $longitude = isset($matches[3]) ? (float)$matches[3] : null;

      } elseif (
         !empty($validated['latitude']) &&
         !empty($validated['longitude'])
      ) {
// dd(2);
         $latitude = (float)$validated['latitude'];
         $longitude = (float)$validated['longitude'];
      }
      // ----------------- COORDINATE PARSING -----------------

      $query = Bar::select('id', 'bar_uid', 'bar_admin_id', 'name', 'earning_points', 'address', 'city', 'latitude', 'longitude', 'image')->status(1);

      // ----------------- DISTANCE CALCULATION -----------------
      if ($latitude !== null && $longitude !== null) {

         $query->selectRaw("
				ROUND(
					(
						6371 * acos(
							cos(radians(?))
							* cos(radians(latitude))
							* cos(radians(longitude) - radians(?))
							+ sin(radians(?))
							* sin(radians(latitude))
						)
					),
					2
				) AS distance_to_bar_in_km
			", [$latitude, $longitude, $latitude]);

         if ($strict) {
            $query->whereNotNull('latitude')->whereNotNull('longitude')->having('distance_to_bar_in_km', '<=', 1);
         }

         // $query->orderBy('distance_to_bar_in_km');
         $query->orderByRaw('distance_to_bar_in_km IS NULL')->orderBy('distance_to_bar_in_km');
      }
      // ----------------- DISTANCE CALCULATION -----------------

      return $query->paginate(20);
   }
}