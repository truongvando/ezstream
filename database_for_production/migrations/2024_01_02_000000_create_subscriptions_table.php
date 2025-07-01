<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_package_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['PENDING_PAYMENT', 'ACTIVE', 'EXPIRED', 'CANCELLED'])->default('PENDING_PAYMENT');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->bigInteger('payment_transaction_id')->unsigned()->nullable();
            $table->timestamps();

            $table->foreign('payment_transaction_id')->references('id')->on('transactions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
}; 