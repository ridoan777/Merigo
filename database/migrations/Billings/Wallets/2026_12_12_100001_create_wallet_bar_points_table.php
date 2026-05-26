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
        Schema::create('wallet_bar_points', function (Blueprint $table) {
            $table->id();
            $table->string('bar_wallet_uid')->unique();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bar_id')->constrained('bars')->onDelete('cascade');

            $table->bigInteger('balance')->default(0);
            $table->bigInteger('total_earnings')->default(0);
            $table->bigInteger('total_spent')->default(0);
            
            $table->boolean('status')->default(1);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_bar_points');
    }
};
