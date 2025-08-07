<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlaylistUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $streamId;
    public $fileIds;
    public $action;
    public $affectedFiles;

    /**
     * Create a new event instance.
     */
    public function __construct($streamId, $fileIds = null, $action = 'updated', $affectedFiles = null)
    {
        $this->streamId = $streamId;
        $this->fileIds = $fileIds;
        $this->action = $action; // 'updated', 'added', 'removed', 'reordered'
        $this->affectedFiles = $affectedFiles;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('stream.' . $this->streamId),
            new Channel('playlist-updates')
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'stream_id' => $this->streamId,
            'action' => $this->action,
            'file_ids' => $this->fileIds,
            'affected_files' => $this->affectedFiles,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'playlist.updated';
    }
}
