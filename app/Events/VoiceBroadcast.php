<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class VoiceBroadcast implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $username;
    public $channel;
    public $audio;
    public $streamId;

    public function __construct($username, $channel, $audio, $streamId)
    {
        $this->username = $username;
        $this->channel = $channel;
        $this->audio = $audio;
        $this->streamId = $streamId;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('talkie.channel.' . $this->channel);
    }

    public function broadcastAs()
    {
        return 'VoiceBroadcast';
    }
}
