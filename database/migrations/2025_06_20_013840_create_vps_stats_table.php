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
        Schema::create('vps_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vps_server_id')->constrained()->onDelete('cascade');
            $table->float('cpu_load', 5, 2)->comment('CPU load average for 1 min');
            $table->unsignedInteger('ram_total_mb');
            $table->unsignedInteger('ram_used_mb');
            $table->unsignedInteger('disk_total_gb');
            $table->unsignedInteger('disk_used_gb');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vps_stats');
    }
}; 