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
            // Update status column to support all required values
            $table->enum('status', [
                'PENDING',
                'INACTIVE', 
                'ACTIVE',
                'STARTING',
                'STREAMING',
                'STOPPING',
                'STOPPED',
                'COMPLETED',
                'ERROR'
            ])->default('INACTIVE')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            // Revert to original enum (if needed)
            $table->enum('status', [
                'PENDING',
                'INACTIVE', 
                'ACTIVE',
                'STARTING',
                'STREAMING',
                'ERROR'
            ])->default('INACTIVE')->change();
        });
    }
};
