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
            $table->foreignId('user_file_id')->nullable()->after('vps_server_id')->constrained('user_files')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            // Drop foreign key first
            if (Schema::hasColumn('stream_configurations', 'user_file_id')) {
                $table->dropForeign(['user_file_id']);
                $table->dropColumn('user_file_id');
            }
        });
    }
}; 