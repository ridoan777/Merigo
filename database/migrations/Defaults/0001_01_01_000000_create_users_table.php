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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('user_uid')->unique();
            $table->string('username')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('user_role')->default('student')->index(); // roles: super_admin | admin | bar_manager | student

            $table->string('gender')->nullable();   // male, female, prefer_not
            $table->string('phone')->nullable()->index();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable()->index();

            $table->string('avatar')->nullable();
            $table->string('password')->nullable();

            $table->string('city')->nullable();

            $table->boolean('agreed_terms')->default(1); // yes/no
            $table->string('term_version')->nullable();
            $table->string('timezone')->nullable();
            $table->string('own_referral_code')->nullable()->unique();
            $table->string('invited_referral_code')->nullable();

            $table->json('fcm_token')->nullable();

            $table->boolean('status')->default(1)->index(); // active/inactive
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
