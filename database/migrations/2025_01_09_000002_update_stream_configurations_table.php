<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            // Add new columns if they don't exist
            if (!Schema::hasColumn('stream_configurations', 'vps_server_id')) {
                $table->foreignId('vps_server_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('stream_configurations', 'rtmp_backup_url')) {
                $table->text('rtmp_backup_url')->nullable()->after('rtmp_url');
            }
            if (!Schema::hasColumn('stream_configurations', 'is_quick_stream')) {
                $table->boolean('is_quick_stream')->default(false)->after('keep_files_on_agent');
            }
            if (!Schema::hasColumn('stream_configurations', 'auto_delete_from_cdn')) {
                $table->boolean('auto_delete_from_cdn')->default(false)->after('is_quick_stream');
            }
            if (!Schema::hasColumn('stream_configurations', 'playlist_order')) {
                $table->enum('playlist_order', ['sequential', 'random'])->default('sequential')->after('auto_delete_from_cdn');
            }
            if (!Schema::hasColumn('stream_configurations', 'auto_start_when_ready')) {
                $table->boolean('auto_start_when_ready')->default(false)->after('updated_at');
            }
            if (!Schema::hasColumn('stream_configurations', 'processing_files')) {
                $table->json('processing_files')->nullable()->after('auto_start_when_ready');
            }
        });

        // Update status enum to include waiting_for_processing
        DB::statement("ALTER TABLE stream_configurations MODIFY COLUMN status ENUM('PENDING','INACTIVE','ACTIVE','STARTING','STREAMING','STOPPING','STOPPED','COMPLETED','ERROR','waiting_for_processing') NOT NULL DEFAULT 'INACTIVE'");
    }

    public function down()
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'vps_server_id', 'rtmp_backup_url', 'is_quick_stream', 
                'auto_delete_from_cdn', 'playlist_order', 'auto_start_when_ready', 
                'processing_files'
            ]);
        });
        
        // Revert status enum
        DB::statement("ALTER TABLE stream_configurations MODIFY COLUMN status ENUM('PENDING','INACTIVE','ACTIVE','STARTING','STREAMING','STOPPING','STOPPED','COMPLETED','ERROR') NOT NULL DEFAULT 'INACTIVE'");
    }
};
