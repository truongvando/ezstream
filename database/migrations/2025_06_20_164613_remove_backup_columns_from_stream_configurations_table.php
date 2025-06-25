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
            $table->dropColumn(['backup_rtmp_url', 'backup_stream_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->string('backup_rtmp_url')->nullable()->after('stream_key');
            $table->string('backup_stream_key')->nullable()->after('backup_rtmp_url');
        });
    }
};
