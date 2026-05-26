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
        Schema::create('referral_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invitor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_id')->constrained('users')->cascadeOnDelete();

            $table->string('refer_code');
            $table->integer('credit_amount')->default(0)->comment('integer in cents');

            $table->boolean('status')->default(1);
            $table->timestamps();

            $table->unique(['invitor_id', 'invited_id']);
            $table->unique(['invited_id', 'refer_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_records');
    }
};
