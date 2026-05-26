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
		Schema::create('subscribed_user_webs', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
			$table->foreignId('tier_id')->nullable()->constrained('subscription_tiers')->nullOnDelete();
			$table->foreignId('service_id')->nullable()->constrained('projects')->nullOnDelete();
			$table->unique(['user_id', 'tier_id', 'service_id'], 'unique_web_user_tier_service');

			$table->string('payment_status')->nullable();
			$table->string('sub_status')->default('requested');

			$table->decimal('amount', 10, 2)->default(0);
			$table->integer('duration')->nullable();

			$table->text('note')->nullable();
			$table->boolean('status')->default(1);
			
			$table->text('invoice_id')->nullable();
			$table->text('invoice_num')->nullable();
			$table->datetime('next_renewal_at')->nullable();
			$table->datetime('renewed_at')->nullable();
			$table->dateTime('cancelled_at')->nullable();

			$table->string('payer')->nullable();
			$table->string('last4')->nullable();
			$table->string('brand')->nullable();
			$table->string('expiry')->nullable();

			$table->text('stripe_checkout_session_id')->nullable();
			$table->text('stripe_subscription_id')->nullable();
			$table->text('stripe_customer_id')->nullable();

			$table->string('pdf_1st')->nullable();
			$table->string('pdf_latest')->nullable();

			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('subscribed_user_webs');
	}
};
