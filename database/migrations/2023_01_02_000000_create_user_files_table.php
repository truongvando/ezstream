<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('disk')->default('local');
            $table->text('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->bigInteger('size');
            $table->string('status')->default('ready');
            $table->string('stream_video_id')->nullable();
            $table->json('stream_metadata')->nullable();
            $table->boolean('auto_delete_after_stream')->default(false);
            $table->timestamp('scheduled_deletion_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('stream_video_id');
            $table->index('disk');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_files');
    }
};
