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
        Schema::table('vps_servers', function (Blueprint $table) {
            $table->bigInteger('storage_limit')->default(85899345920)->after('description'); // 80GB default
            $table->bigInteger('storage_used')->default(0)->after('storage_limit');
            $table->json('storage_stats')->nullable()->after('storage_used'); // Detailed storage info
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vps_servers', function (Blueprint $table) {
            $table->dropColumn(['storage_limit', 'storage_used', 'storage_stats']);
        });
    }
};
