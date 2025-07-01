<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vps_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address');
            $table->string('ssh_user')->default('root');
            $table->text('ssh_private_key');
            $table->enum('status', ['active', 'inactive', 'provisioning', 'error'])->default('active');
            $table->integer('max_concurrent_streams')->default(10);
            $table->integer('current_streams')->default(0);
            $table->string('region')->nullable();
            $table->json('specs')->nullable()->comment('CPU, RAM, Storage specs');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vps_servers');
    }
}; 