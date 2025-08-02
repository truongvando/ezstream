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
        Schema::table('tools', function (Blueprint $table) {
            // License configuration
            $table->enum('license_type', ['FREE', 'DEMO', 'MONTHLY', 'YEARLY', 'LIFETIME', 'CONSIGNMENT'])
                  ->default('LIFETIME')
                  ->after('price');
            
            $table->integer('demo_days')->nullable()->after('license_type');
            $table->decimal('monthly_price', 8, 2)->nullable()->after('demo_days');
            $table->decimal('yearly_price', 8, 2)->nullable()->after('monthly_price');
            
            // Tool ownership
            $table->boolean('is_own_tool')->default(true)->after('yearly_price');
            $table->string('owner_name')->nullable()->after('is_own_tool');
            $table->string('owner_contact')->nullable()->after('owner_name');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('owner_contact'); // For consignment
            
            // License limits
            $table->integer('max_devices')->default(1)->after('commission_rate');
            $table->boolean('allow_transfer')->default(true)->after('max_devices');
            
            // Tool metadata
            $table->string('version')->default('1.0.0')->after('allow_transfer');
            $table->json('changelog')->nullable()->after('version');
            $table->timestamp('last_updated')->nullable()->after('changelog');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn([
                'license_type',
                'demo_days', 
                'monthly_price',
                'yearly_price',
                'is_own_tool',
                'owner_name',
                'owner_contact',
                'commission_rate',
                'max_devices',
                'allow_transfer',
                'version',
                'changelog',
                'last_updated'
            ]);
        });
    }
};
