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
            $table->float('cpu_usage_percent', 5, 2)->default(0);
            $table->float('ram_usage_percent', 5, 2)->default(0);
            $table->float('disk_usage_percent', 5, 2)->default(0);
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