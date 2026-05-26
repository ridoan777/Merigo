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
		Schema::create('subscribed_user_apps', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
			$table->foreignId('tier_id')->nullable()->constrained('subscription_tiers')->nullOnDelete();
			
			$table->text('entitlement_id')->nullable()->comment('premium|pro|entitlement Identifier');
			$table->string('provider')->default('revenuecat')->comment('revenuecat');
			$table->string('store')->nullable()->comment('Google|Apple|Amazon');

			$table->string('payment_status')->default('pending')->comment('pending|paid');	// pending, paid
			$table->string('sub_status')->default('requested')->comment('requested|running|cancelled|on-trial');

			$table->string('country')->nullable();
			$table->string('currency')->nullable();
			$table->decimal('amount', 10, 2)->default(0);
			$table->string('duration')->nullable();

			$table->text('note')->nullable();
			$table->boolean('status')->default(1);

			$table->text('rc_product_id')->nullable();
			$table->text('rc_subscription_id')->nullable()->comment('unique identifier');
			$table->text('rc_purchase_token')->nullable()->comment('unique identifier');
			$table->text('rc_event_id')->nullable();

			$table->dateTime('purchased_at')->nullable();
			$table->dateTime('renewal_at')->nullable();
			$table->dateTime('cancelled_at')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('subscribed_user_apps');
	}
};
