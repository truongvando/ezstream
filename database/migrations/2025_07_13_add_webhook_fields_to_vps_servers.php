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
        Schema::table('vps_servers', function (Blueprint $table) {
            $table->boolean('webhook_configured')->default(false)->after('status_message');
            $table->string('webhook_url')->nullable()->after('webhook_configured');
            $table->timestamp('last_webhook_setup')->nullable()->after('webhook_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vps_servers', function (Blueprint $table) {
            $table->dropColumn(['webhook_configured', 'webhook_url', 'last_webhook_setup']);
        });
    }
};
