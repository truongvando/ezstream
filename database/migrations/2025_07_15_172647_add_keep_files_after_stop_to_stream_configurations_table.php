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
            $table->boolean('keep_files_after_stop')->default(false)->after('loop');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->dropColumn('keep_files_after_stop');
        });
    }
};
