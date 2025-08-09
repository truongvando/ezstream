<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_files', function (Blueprint $table) {
            if (!Schema::hasColumn('user_files', 'stream_video_id')) {
                $table->string('stream_video_id')->nullable()->after('path');
            }
            if (!Schema::hasColumn('user_files', 'stream_metadata')) {
                $table->json('stream_metadata')->nullable()->after('stream_video_id');
            }
            if (!Schema::hasColumn('user_files', 'vps_server_id')) {
                $table->foreignId('vps_server_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('user_files', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('scheduled_deletion_at');
            }
            if (!Schema::hasColumn('user_files', 'source_url')) {
                $table->string('source_url')->nullable()->after('is_locked');
            }
            if (!Schema::hasColumn('user_files', 'google_drive_file_id')) {
                $table->string('google_drive_file_id')->nullable()->after('source_url');
            }
            if (!Schema::hasColumn('user_files', 'upload_session_url')) {
                $table->text('upload_session_url')->nullable()->after('google_drive_file_id');
            }
            if (!Schema::hasColumn('user_files', 'error_message')) {
                $table->text('error_message')->nullable()->after('upload_session_url');
            }
            if (!Schema::hasColumn('user_files', 'downloaded_at')) {
                $table->timestamp('downloaded_at')->nullable()->after('error_message');
            }
            if (!Schema::hasColumn('user_files', 'download_source')) {
                $table->string('download_source')->default('upload')->after('downloaded_at');
            }
            if (!Schema::hasColumn('user_files', 'status_message')) {
                $table->text('status_message')->nullable()->after('download_source');
            }
        });
    }

    public function down()
    {
        Schema::table('user_files', function (Blueprint $table) {
            $table->dropColumn([
                'stream_video_id', 'stream_metadata', 'vps_server_id', 'is_locked',
                'source_url', 'google_drive_file_id', 'upload_session_url', 
                'error_message', 'downloaded_at', 'download_source', 'status_message'
            ]);
        });
    }
};
