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
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->timestamp('last_user_action_at')->nullable()->after('last_status_update');
            $table->string('last_user_action')->nullable()->after('last_user_action_at'); // 'start', 'stop', 'force_stop'
            $table->text('sync_notes')->nullable()->after('last_user_action'); // For debugging sync issues
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->dropColumn(['last_user_action_at', 'last_user_action', 'sync_notes']);
        });
    }
};
