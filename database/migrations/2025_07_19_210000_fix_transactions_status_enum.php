<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix transactions status enum to include CANCELLED
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('PENDING', 'COMPLETED', 'FAILED', 'CANCELLED') DEFAULT 'PENDING'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back (optional)
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('PENDING', 'COMPLETED', 'FAILED') DEFAULT 'PENDING'");
    }
};
