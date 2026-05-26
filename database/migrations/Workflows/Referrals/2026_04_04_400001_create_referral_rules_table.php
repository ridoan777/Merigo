<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referral_rules', function (Blueprint $table) {
            $table->id();
            
            $table->integer('credit_amount')->default(0)->comment('integer in cents');
            $table->integer('credit_limit')->default(0);
            $table->integer('exchange_limit')->default(0);

            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_rules');
    }
};
