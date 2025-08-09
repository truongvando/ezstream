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
            // Drop existing unique constraint if exists
            try {
                $table->dropUnique('unique_user_title');
            } catch (\Exception $e) {
                // Constraint might not exist
            }

            // Add missing columns if they don't exist
            if (!Schema::hasColumn('stream_configurations', 'rtmp_backup_url')) {
                $table->string('rtmp_backup_url')->nullable()->after('rtmp_url');
            }

            if (!Schema::hasColumn('stream_configurations', 'is_quick_stream')) {
                $table->boolean('is_quick_stream')->default(false)->after('loop');
            }

            if (!Schema::hasColumn('stream_configurations', 'auto_delete_from_cdn')) {
                $table->boolean('auto_delete_from_cdn')->default(false)->after('is_quick_stream');
            }

            if (!Schema::hasColumn('stream_configurations', 'last_started_at')) {
                $table->timestamp('last_started_at')->nullable()->after('scheduled_end');
            }

            // Re-add unique constraint with proper name
            $table->unique(['user_id', 'title'], 'stream_configurations_user_title_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->dropUnique('stream_configurations_user_title_unique');
            $table->dropColumn(['rtmp_backup_url', 'is_quick_stream', 'auto_delete_from_cdn', 'last_started_at']);
        });
    }
};
