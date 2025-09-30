<?php

namespace App\Events;

use App\Models\ImageUpload;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImageProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $upload;
    /**
     * Create a new event instance.
     */
    public function __construct(ImageUpload $upload)
    {
        $this->upload = $upload;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('uploads.' . $this->upload->user_id),
        ];
    }
}
