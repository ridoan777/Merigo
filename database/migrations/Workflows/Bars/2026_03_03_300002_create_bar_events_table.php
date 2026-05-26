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
        Schema::create('bar_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_uid')->unique();
            $table->foreignId('bar_id')->constrained('bars')->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('event_day');

            $table->string('name');
            $table->text('description')->nullable();

            $table->integer('points_giveaway')->default(0);
            
            $table->string('image')->nullable();
            $table->date('expiry')->nullable();

            $table->boolean('status')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_events');
    }
};
