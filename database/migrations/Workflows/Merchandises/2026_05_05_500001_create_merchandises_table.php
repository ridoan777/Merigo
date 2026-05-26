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
        Schema::create('merchandises', function (Blueprint $table) {
            $table->id();

            $table->string('merc_uid')->unique();
            $table->foreignId('creator')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('name');
            $table->text('description')->nullable();
            
            $table->integer('points_cost')->default(0);

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
        Schema::dropIfExists('merchandises');
    }
};
