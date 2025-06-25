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
        Schema::table('user_files', function (Blueprint $table) {
            $table->string('source_url')->nullable()->after('status')->comment('Original source URL (Google Drive, etc.)');
            $table->string('google_drive_file_id')->nullable()->after('source_url')->comment('Google Drive file ID');
            $table->text('error_message')->nullable()->after('google_drive_file_id')->comment('Error message if download/transfer failed');
            $table->timestamp('downloaded_at')->nullable()->after('error_message')->comment('When file was downloaded');
            $table->string('download_source')->default('upload')->after('downloaded_at')->comment('Source: upload, google_drive, etc.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_files', function (Blueprint $table) {
            $table->dropColumn([
                'source_url',
                'google_drive_file_id', 
                'error_message',
                'downloaded_at',
                'download_source'
            ]);
        });
    }
}; 