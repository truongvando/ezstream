<?php

namespace App\Events;

use App\Models\UserFile;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userFile;

    /**
     * Create a new event instance.
     */
    public function __construct(UserFile $userFile)
    {
        $this->userFile = $userFile;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userFile->user_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->userFile->id,
            'file_name' => $this->userFile->original_name,
            'processing_status' => $this->userFile->stream_metadata['processing_status'] ?? 'unknown',
            'stream_video_id' => $this->userFile->stream_video_id,
            'hls_url' => $this->userFile->stream_metadata['hls_url'] ?? null,
            'updated_at' => $this->userFile->updated_at->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'file.status.updated';
    }
}
