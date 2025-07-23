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
        Schema::create('system_events', function (Blueprint $table) {
            $table->id();
            $table->string('level')->default('info')->index(); // e.g., info, warning, error
            $table->string('type')->index(); // e.g., WEBHOOK_REPORT, SYNC_JOB, STREAM_COMMAND
            $table->text('message'); // A human-readable message
            $table->json('context')->nullable(); // For detailed data like webhook payloads
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_events');
    }
};
