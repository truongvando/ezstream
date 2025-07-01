<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_file_id')->constrained()->onDelete('cascade');
            $table->foreignId('vps_server_id')->nullable()->constrained()->onDelete('set null');
            $table->string('stream_name');
            $table->string('rtmp_url');
            $table->string('stream_key');
            $table->enum('status', ['STOPPED', 'STARTING', 'RUNNING', 'STOPPING', 'ERROR'])->default('STOPPED');
            $table->boolean('is_playlist')->default(false);
            $table->json('playlist_files')->nullable();
            $table->boolean('loop_playlist')->default(false);
            $table->timestamp('last_status_update')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_configurations');
    }
}; 