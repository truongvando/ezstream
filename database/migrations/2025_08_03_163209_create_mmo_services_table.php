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
        Schema::create('mmo_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->text('detailed_description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('category')->default('MMO');
            $table->json('features')->nullable(); // Array of features
            $table->string('delivery_time')->default('1-24 hours'); // Estimated delivery
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('requirements')->nullable(); // What customer needs to provide
            $table->text('notes')->nullable(); // Admin notes
            $table->timestamps();

            $table->index(['is_active', 'is_featured']);
            $table->index(['category', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mmo_services');
    }
};
