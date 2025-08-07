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
        Schema::create('stream_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stream_id')->default(0)->index();
            $table->string('event');
            $table->enum('level', ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'])->default('INFO');
            $table->enum('category', [
                'STREAM_LIFECYCLE',
                'PLAYLIST_MANAGEMENT',
                'QUALITY_MONITORING',
                'ERROR_RECOVERY',
                'AGENT_COMMUNICATION',
                'PERFORMANCE',
                'USER_ACTION'
            ])->default('STREAM_LIFECYCLE');
            $table->json('context')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('vps_id')->nullable()->index();
            $table->string('session_id')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['stream_id', 'created_at']);
            $table->index(['level', 'created_at']);
            $table->index(['category', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stream_logs');
    }
};
