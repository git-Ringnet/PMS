<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notify reservation screens after the enclosing write transaction commits.
 *
 * Booking flows update several related records in one transaction. Deferring
 * the broadcast prevents another screen from reloading a partially-written
 * booking (or receiving a notification for a transaction that later rolls
 * back).
 */
class ReservationUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reservationId;
    public $action;
    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct($reservationId, $action = null, $message = null)
    {
        $this->reservationId = $reservationId;
        $this->action = $action;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('pms-channel'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'reservation.updated';
    }
}
