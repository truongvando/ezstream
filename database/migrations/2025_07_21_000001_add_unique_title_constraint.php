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
        // First, fix any existing duplicate titles
        $this->fixExistingDuplicates();
        
        // Then add the unique constraint
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->unique(['user_id', 'title'], 'unique_user_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->dropUnique('unique_user_title');
        });
    }

    /**
     * Fix existing duplicate titles before adding constraint
     */
    private function fixExistingDuplicates(): void
    {
        $duplicates = \DB::select("
            SELECT user_id, title, COUNT(*) as count 
            FROM stream_configurations 
            GROUP BY user_id, title 
            HAVING COUNT(*) > 1
        ");

        foreach ($duplicates as $duplicate) {
            $streams = \DB::select("
                SELECT id, title, created_at 
                FROM stream_configurations 
                WHERE user_id = ? AND title = ? 
                ORDER BY created_at ASC
            ", [$duplicate->user_id, $duplicate->title]);

            // Keep the first one, rename others
            foreach ($streams as $index => $stream) {
                if ($index > 0) { // Skip the first one
                    $newTitle = $stream->title . ' (' . ($index + 1) . ')';
                    
                    // Make sure new title is unique
                    $counter = $index + 1;
                    while (\DB::scalar("
                        SELECT COUNT(*) 
                        FROM stream_configurations 
                        WHERE user_id = ? AND title = ?
                    ", [$duplicate->user_id, $newTitle]) > 0) {
                        $counter++;
                        $newTitle = $stream->title . ' (' . $counter . ')';
                    }
                    
                    \DB::update("
                        UPDATE stream_configurations 
                        SET title = ? 
                        WHERE id = ?
                    ", [$newTitle, $stream->id]);
                    
                    echo "Fixed duplicate: Stream #{$stream->id} renamed to '{$newTitle}'\n";
                }
            }
        }
    }
};
