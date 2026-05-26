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
		Schema::create('subscription_tiers', function (Blueprint $table) {
			$table->id();
			$table->foreignId('service_id')->nullable()->constrained('projects')->nullOnDelete();

			$table->string('platform')->nullable();

			$table->string('name')->nullable();
			$table->string('slogan')->nullable();

			$table->decimal('starting_price', 10, 2)->default(0);
			$table->decimal('final_price', 10, 2)->default(0);

			$table->string('price_label')->nullable();
			$table->integer('duration')->nullable();

			$table->text('benefits')->nullable();
			$table->text('description')->nullable();
			
			$table->text('rc_entitlement_id')->nullable();
			$table->text('apple_product_id')->nullable();
			$table->text('google_subscription_id')->nullable();
			$table->text('google_base_plan_id')->nullable();
			
			$table->text('stripe_product_id')->nullable();
			$table->text('stripe_price_id')->nullable();

			$table->string('image')->nullable();
			
			$table->boolean('status')->default(1);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('subscription_tiers');
	}
};
