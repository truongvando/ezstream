<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add storage mode setting
        Setting::updateOrCreate(
            ['key' => 'storage_mode'],
            ['value' => 'server'] // Default to server for cost savings
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove storage mode setting
        Setting::where('key', 'storage_mode')->delete();
    }
};
