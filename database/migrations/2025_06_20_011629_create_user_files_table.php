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
        Schema::create('user_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vps_server_id')->nullable()->constrained()->onDelete('set null');
            $table->string('disk')->default('local'); // e.g., 'local', 's3'
            $table->string('path'); // Path on the disk
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size'); // Size in bytes
            $table->string('status')->default('PENDING_TRANSFER'); // PENDING_TRANSFER, AVAILABLE, FAILED
            $table->text('status_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_files');
    }
};
