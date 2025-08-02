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
        Schema::table('api_services', function (Blueprint $table) {
            $table->integer('service_id')->unique()->comment('ID từ API')->after('id');
            $table->string('name')->after('service_id');
            $table->string('type', 100)->after('name');
            $table->string('category', 100)->after('type');
            $table->decimal('rate', 10, 2)->after('category');
            $table->integer('min_quantity')->after('rate');
            $table->integer('max_quantity')->after('min_quantity');
            $table->boolean('refill')->default(false)->after('max_quantity');
            $table->boolean('cancel')->default(false)->after('refill');
            $table->integer('markup_percentage')->default(20)->comment('+20% giá')->after('cancel');
            $table->boolean('is_active')->default(true)->after('markup_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_services', function (Blueprint $table) {
            $table->dropColumn([
                'service_id',
                'name',
                'type',
                'category',
                'rate',
                'min_quantity',
                'max_quantity',
                'refill',
                'cancel',
                'markup_percentage',
                'is_active'
            ]);
        });
    }
};
