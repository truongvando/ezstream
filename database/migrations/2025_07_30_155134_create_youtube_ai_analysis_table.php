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
        Schema::create('youtube_ai_analysis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('youtube_channel_id')->constrained()->onDelete('cascade');
            $table->text('analysis_content');
            $table->decimal('extracted_cpm', 8, 3)->nullable();
            $table->string('cpm_source')->default('ai'); // ai, fallback
            $table->json('analysis_metadata')->nullable(); // Store additional data
            $table->timestamps();

            $table->index(['youtube_channel_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_ai_analysis');
    }
};
