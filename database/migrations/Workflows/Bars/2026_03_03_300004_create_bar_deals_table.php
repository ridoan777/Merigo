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
        Schema::create('bar_deals', function (Blueprint $table) {
            $table->id();
            $table->string('deal_uid')->unique();

            $table->foreignId('bar_id')->constrained('bars')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('bar_events')->nullOnDelete();

            $table->string('name');
            $table->integer('point_cost')->default(0);
            $table->string('to_show')->nullable()->comment('bartender|bouncer');

            $table->timestamp('expiry')->nullable();

            $table->boolean('status')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_deals');
    }
};
