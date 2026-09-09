<?php

namespace App\Events\Cekbot;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a Cekbot message is stored, so the admin inbox updates in
 * real time (no manual "Segar semula"). Broadcast synchronously (ShouldBroadcastNow)
 * because it is dispatched from an already-async queued webhook job.
 */
class CekbotMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $sessionId,
        public string $direction,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('cekbot-inbox');
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'session_id' => $this->sessionId,
            'direction' => $this->direction,
        ];
    }
}
