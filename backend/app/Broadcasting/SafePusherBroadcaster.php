<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;
use Throwable;

class SafePusherBroadcaster extends PusherBroadcaster
{
    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        try {
            parent::broadcast($channels, $event, $payload);
        } catch (BroadcastException $e) {
            Log::warning('Realtime broadcast skipped (WebSocket server unreachable): ' . $e->getMessage());
        } catch (Throwable $e) {
            Log::warning('Realtime broadcast skipped (unexpected error): ' . $e->getMessage());
        }
    }
}
