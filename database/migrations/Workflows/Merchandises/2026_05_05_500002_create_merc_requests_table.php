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
        Schema::create('merc_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('merc_id')->constrained('merchandises')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('phase')->default('pending')->comment('pending|declined|incoming|completed|cancelled');

            $table->integer('points_debited')->default(0);
            $table->string('receiver_phone');
            $table->text('receiver_address');
            $table->text('note')->nullable();

            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merc_requests');
    }
};
