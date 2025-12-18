<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('talkie.channel.{channel}', function ($user, $channel) {
    return ['id' => $user->id, 'name' => $user->name];
});
