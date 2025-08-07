<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamSettingsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $streamId;
    public $setting;
    public $value;

    /**
     * Create a new event instance.
     */
    public function __construct($streamId, $setting, $value)
    {
        $this->streamId = $streamId;
        $this->setting = $setting; // 'loop', 'playlist_order', etc.
        $this->value = $value;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('stream.' . $this->streamId),
            new Channel('stream-settings')
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'stream_id' => $this->streamId,
            'setting' => $this->setting,
            'value' => $this->value,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'stream.settings.updated';
    }
}
