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
        Schema::create('bar_event_galleries', function (Blueprint $table) {
			$table->id();
			$table->foreignId('event_id')->constrained('bar_events')->cascadeOnDelete();

			$table->string('filename')->nullable();
			$table->string('filepath');

			$table->json('metadata')->nullable();

			$table->boolean('status')->default(1);
			$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_event_galleries');
    }
};
