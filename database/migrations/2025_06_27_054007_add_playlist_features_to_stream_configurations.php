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
            $table->text('rtmp_backup_url')->nullable()->after('rtmp_url');
            $table->enum('playlist_order', ['sequential', 'random'])->default('sequential')->after('loop');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->dropColumn(['rtmp_backup_url', 'playlist_order']);
        });
    }
};
