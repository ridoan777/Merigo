<?php

namespace App\Providers;

use App\Models\Users\User;
use Illuminate\Support\ServiceProvider;
use App\Models\System\Security\CustomPersonalAccessToken;
use Illuminate\Support\Facades\Gate;
use Laravel\Cashier\Cashier;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 */
	public function register(): void
	{
		Cashier::useCustomerModel(User::class);
	}

	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
		Sanctum::usePersonalAccessTokenModel(CustomPersonalAccessToken::class);

		$this->loadMigrationsFrom([
			database_path('migrations'),
			database_path('migrations/Defaults'),
			database_path('migrations/Packages'),
			// --------------- SETTINGS ---------------
			database_path('migrations/Settings'),
			database_path('migrations/Settings/Options'),
			database_path('migrations/Settings/Notifications'),
			database_path('migrations/Settings/Role_Managements'),
			// --------------- MANAGEMENTS ---------------
			database_path('migrations/Managements'),
			// --------------- COMMUNICATIONS ---------------
			database_path('migrations/Communications'),
			// --------------- PROJECTS ---------------
			database_path('migrations/Workflows/Projects'),
			// --------------- BARS ---------------
			database_path('migrations/Workflows/Bars'),
			// --------------- LOCATIONS ---------------
			database_path('migrations/Workflows/Locations'),
			// --------------- REFERRALS ---------------
			database_path('migrations/Workflows/Referrals'),
			// --------------- ADS ---------------
			database_path('migrations/Workflows/Ads'),
			// --------------- WALLETS ---------------
			database_path('migrations/Workflows/Wallets'),
			// --------------- BILLING ---------------
			database_path('migrations/Billings/Subscriptions'),
			database_path('migrations/Billings/Wallets'),
			// --------------- MERCHANDISE ---------------
			database_path('migrations/Workflows/Merchandises'),
		]);

		Gate::before(function ($user, string $ability) {
			// return $user->hasRole('super_admin') ? true : null;
			return $user->hasRoleKey('SUPER_ADMIN') ? true : null;
		});

	}
}
