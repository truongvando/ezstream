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
            $table->json('capabilities')->nullable()->after('bandwidth_gb');
            $table->integer('max_concurrent_streams')->default(1)->after('capabilities');
            $table->integer('current_streams')->default(0)->after('max_concurrent_streams');
            $table->timestamp('last_seen_at')->nullable()->after('current_streams');
            $table->timestamp('last_provisioned_at')->nullable()->after('last_seen_at');
            $table->text('error_message')->nullable()->after('last_provisioned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vps_servers', function (Blueprint $table) {
            $table->dropColumn([
                'capabilities',
                'max_concurrent_streams',
                'current_streams',
                'last_seen_at',
                'last_provisioned_at',
                'error_message'
            ]);
        });
    }
};
