<?php

namespace App\Http\Controllers\Workflows\UserLocation;

use App\Domain\Bars\Services\HaversineDistance;
use App\Helpers\ApiJsonReturnHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\Errors\ExceptionHandling;
use Throwable;


class ApiGeoLocationController extends Controller
{
	public function barCheckin(Request $request)
	{
		try {
			$validated = $request->validate([
				'latitude' => 'nullable|numeric',
				'longitude' => 'nullable|numeric',
				'coordinates' => 'nullable|string',
			]);

			$distance = new HaversineDistance();
			$bars = $distance->handle($validated);

			$message = $bars ? "All nearby bars have been fetched sucessfully!" : "No nearby bars found! Try using manual checking!";

			return ApiJsonReturnHelper::handle(true, 200, $message, $bars);

		} catch (Throwable $e) {
			return ExceptionHandling::handle('fetching nearby bars', $e);
		}
	}
}
