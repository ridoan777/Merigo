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
        Schema::create('wallet_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_uid')->unique();

            // $table->nullableMorphs('wallet_morphed');
            $table->string('wallet_morphed_type')->nullable();
            $table->unsignedBigInteger('wallet_morphed_id')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('transaction_type')->comment('earn|spend');

            $table->integer('amount')->default(0);

            $table->string('phase')->nullable()->comment('in_progress,completed,failed');

            $table->text('note')->nullable();

            $table->boolean('status')->default(1);

            $table->timestamps();
            $table->index(['wallet_morphed_type', 'wallet_morphed_id'],'wallet_morph_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_point_transactions');
    }
};
