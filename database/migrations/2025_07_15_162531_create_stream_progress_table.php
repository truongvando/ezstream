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
            $table->unsignedBigInteger('stream_configuration_id');
            $table->string('stage', 50);
            $table->tinyInteger('progress_percentage')->default(0);
            $table->text('message');
            $table->json('details')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('stream_configuration_id')->references('id')->on('stream_configurations')->onDelete('cascade');
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
