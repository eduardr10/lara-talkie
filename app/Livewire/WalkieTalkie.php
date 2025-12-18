<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use App\Events\VoiceBroadcast;
use Illuminate\Support\Facades\Cache;

class WalkieTalkie extends Component
{
    public $username;
    public $channel = 1;
    public $isTalking = false;
    public $isReceiving = false;
    public $audioData;
    public $talkingUser = null;

    public function mount()
    {
        $this->username = Auth::user()->name;
    }

    public function updatedChannel($value)
    {
        $this->channel = (int) $value;
        $this->isReceiving = false;
        $this->isTalking = false;
        $this->talkingUser = null;
    }

    public function startTalking()
    {
        // Half duplex: solo uno puede hablar por canal
        $lockKey = 'talkie:channel:' . $this->channel . ':lock';
        if (Cache::lock($lockKey, 62)->get()) {
            $this->isTalking = true;
            $this->talkingUser = $this->username;
        }
    }

    public function stopTalking()
    {
        $lockKey = 'talkie:channel:' . $this->channel . ':lock';
        Cache::lock($lockKey)->release();
        $this->isTalking = false;
        $this->talkingUser = null;
    }

    public function sendAudio($audio)
    {
        // $audio: base64 string
        broadcast(new VoiceBroadcast($this->username, $this->channel, $audio))->toOthers();
        $this->audioData = null;
    }

    protected $listeners = ['receiveAudio' => 'onReceiveAudio'];

    public function onReceiveAudio($payload)
    {
        if ($payload['channel'] == $this->channel) {
            $this->isReceiving = true;
            $this->talkingUser = $payload['username'];
            $this->audioData = $payload['audio'];
        }
    }

    public function render()
    {
        return view('livewire.walkie-talkie', [
            'channel' => $this->channel,
            'isTalking' => $this->isTalking,
            'isReceiving' => $this->isReceiving,
            'talkingUser' => $this->talkingUser,
            'audioData' => $this->audioData,
        ])->extends('layouts.app')->section('content');
    }
    public function toJSON()
    {
        // Workaround for MethodNotFoundException: toJSON
        return;
    }
}
