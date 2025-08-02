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
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('tool_order_id')->nullable()->constrained('tool_orders')->onDelete('set null');
            $table->foreignId('view_order_id')->nullable()->constrained('view_orders')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['tool_order_id']);
            $table->dropForeign(['view_order_id']);
            $table->dropColumn(['tool_order_id', 'view_order_id']);
        });
    }
};
