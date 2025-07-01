<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vps_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vps_server_id')->constrained()->onDelete('cascade');
            $table->decimal('cpu_usage', 5, 2);
            $table->decimal('memory_usage', 5, 2);
            $table->decimal('disk_usage', 5, 2);
            $table->decimal('network_in', 15, 2)->default(0);
            $table->decimal('network_out', 15, 2)->default(0);
            $table->integer('active_streams')->default(0);
            $table->timestamps();

            $table->index(['vps_server_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vps_stats');
    }
}; 