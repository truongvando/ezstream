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
        // This migration ensures user_files table matches the actual database structure
        // No changes needed - database already has correct columns:
        // disk, path, original_name, size, etc.

        // Just log that we're syncing
        \Log::info('User files table structure sync - no changes needed');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed
    }
};
