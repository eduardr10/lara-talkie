<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Events\VoiceBroadcast;

Route::middleware(['web', 'auth'])->post('/talkie/audio', function (Request $request) {
    $request->validate([
        'channel' => 'required|integer|min:1|max:8',
        'username' => 'required|string',
        'audio' => 'required|string',
    ]);
    broadcast(new VoiceBroadcast($request->username, $request->channel, $request->audio))->toOthers();
    return response()->json(['status' => 'ok']);
});
