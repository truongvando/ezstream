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
        Schema::create('scheduled_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('service_id');
            $table->text('link');
            $table->integer('quantity');
            $table->decimal('total_amount', 10, 2);
            $table->timestamp('scheduled_at');
            $table->boolean('is_repeat')->default(false);
            $table->integer('repeat_interval_hours')->nullable();
            $table->integer('max_repeats')->default(1);
            $table->integer('completed_repeats')->default(0);
            $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'CANCELLED'])->default('PENDING');
            $table->json('service_data')->nullable();
            $table->json('last_order_response')->nullable();
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamp('next_execution_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['scheduled_at', 'status']);
            $table->index(['next_execution_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_orders');
    }
};
