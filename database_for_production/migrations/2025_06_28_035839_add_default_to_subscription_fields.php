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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Modify starts_at and ends_at to have default values
            $table->timestamp('starts_at')->nullable()->default(null)->change();
            $table->timestamp('ends_at')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Revert changes
            $table->timestamp('starts_at')->nullable(false)->change();
            $table->timestamp('ends_at')->nullable(false)->change();
        });
    }
};
