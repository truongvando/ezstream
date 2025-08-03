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
        Schema::create('deposit_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->decimal('deposit_amount', 10, 2);
            $table->decimal('bonus_amount', 10, 2);
            $table->decimal('bonus_percentage', 5, 2);
            $table->decimal('total_deposits_before', 10, 2)->comment('Tổng nạp trước khi nạp lần này');
            $table->decimal('total_deposits_after', 10, 2)->comment('Tổng nạp sau khi nạp lần này');
            $table->string('bonus_tier')->comment('Tier bonus: none, 2%, 3%, 4%, 5%');
            $table->text('calculation_details')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_bonuses');
    }
};
