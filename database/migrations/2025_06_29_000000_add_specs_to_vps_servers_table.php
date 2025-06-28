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
            $table->string('provider')->nullable()->after('name');
            $table->integer('cpu_cores')->unsigned()->default(1)->after('description');
            $table->integer('ram_gb')->unsigned()->default(1)->after('cpu_cores');
            $table->integer('disk_gb')->unsigned()->default(10)->after('ram_gb');
            $table->integer('bandwidth_gb')->unsigned()->default(1000)->after('disk_gb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vps_servers', function (Blueprint $table) {
            $table->dropColumn(['provider', 'cpu_cores', 'ram_gb', 'disk_gb', 'bandwidth_gb']);
        });
    }
}; 