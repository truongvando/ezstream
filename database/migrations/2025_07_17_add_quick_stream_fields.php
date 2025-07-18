<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            // Quick stream feature
            $table->boolean('is_quick_stream')->default(false)->after('keep_files_after_stop');
            $table->boolean('auto_delete_from_cdn')->default(false)->after('is_quick_stream');
            
            // Rename existing field for clarity
            $table->renameColumn('keep_files_after_stop', 'keep_files_on_agent');
        });
        
        Schema::table('user_files', function (Blueprint $table) {
            // Mark files for auto-deletion
            $table->boolean('auto_delete_after_stream')->default(false)->after('status');
            $table->timestamp('scheduled_deletion_at')->nullable()->after('auto_delete_after_stream');
        });
    }

    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->dropColumn(['is_quick_stream', 'auto_delete_from_cdn']);
            $table->renameColumn('keep_files_on_agent', 'keep_files_after_stop');
        });
        
        Schema::table('user_files', function (Blueprint $table) {
            $table->dropColumn(['auto_delete_after_stream', 'scheduled_deletion_at']);
        });
    }
};
