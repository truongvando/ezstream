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
        Schema::create('stream_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_configuration_id')->constrained()->onDelete('cascade');
            $table->string('stage'); // 'preparing', 'connecting', 'starting', 'completed'
            $table->integer('progress_percentage')->default(0); // 0-100
            $table->string('message'); // User-friendly message
            $table->json('details')->nullable(); // Technical details
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['stream_configuration_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stream_progress');
    }
};
