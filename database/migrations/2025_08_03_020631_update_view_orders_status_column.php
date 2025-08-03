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
        Schema::table('view_orders', function (Blueprint $table) {
            // Drop the old enum column
            $table->dropColumn('status');
        });

        Schema::table('view_orders', function (Blueprint $table) {
            // Add new enum column with more values
            $table->enum('status', [
                'PENDING',
                'PROCESSING',
                'COMPLETED',
                'FAILED',
                'PENDING_FUNDS',
                'PENDING_RETRY',
                'CANCELLED',
                'REFUNDED'
            ])->default('PENDING')->after('api_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('view_orders', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('view_orders', function (Blueprint $table) {
            $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED'])->default('PENDING');
        });
    }
};
