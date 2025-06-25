<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BufferedStreamingService;

class StreamBufferCommand extends Command
{
    protected $signature = 'stream:buffer {file_id} {source_url} {buffer_dir}';
    protected $description = 'Manage streaming buffer for a file';

    public function handle()
    {
        $fileId = $this->argument('file_id');
        $sourceUrl = $this->argument('source_url');
        $bufferDir = $this->argument('buffer_dir');
        
        $this->info("Starting buffer management for file: {$fileId}");
        
        $service = new BufferedStreamingService();
        $service->manageBuffer($fileId, $sourceUrl, $bufferDir);
    }
} 