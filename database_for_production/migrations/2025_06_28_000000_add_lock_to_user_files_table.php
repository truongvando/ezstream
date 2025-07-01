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
        Schema::table('user_files', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('status')->comment('Prevents file from being deleted by cleanup jobs while in use');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_files', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
}; 