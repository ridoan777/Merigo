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
        Schema::create('bars', function (Blueprint $table) {
            $table->id();
            $table->string('bar_uid')->unique();
            $table->foreignId('bar_admin_id')->constrained('users')->cascadeOnDelete()->unique();

            
            $table->string('name');
            
            $table->integer('earning_points')->default(0)->comment('hours');
            $table->integer('cd_time')->default(12);

            $table->string('contact')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('image')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('status')->default(1);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bars');
    }
};
