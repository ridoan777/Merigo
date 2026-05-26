<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->string('social_provider_id')->nullable()->after('password');
			$table->string('social_provider_name')->nullable()->after('social_provider_id');
			$table->text('social_token')->nullable()->after('social_provider_name');
			$table->text('social_refresh_token')->nullable()->after('social_token');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->dropColumn([
				'social_provider_id',
				'social_provider_name',
				'social_token',
				'social_refresh_token',
			]);
		});
	}
};
