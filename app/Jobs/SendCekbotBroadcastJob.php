<?php

namespace App\Jobs;

use App\Models\CekbotBroadcast;
use App\Models\CekbotBroadcastRecipient;
use App\Models\CekbotMessage;
use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Sends a Cekbot broadcast to its recipients, throttled to reduce WhatsApp ban
 * risk. Each sent message is also recorded in the recipient's conversation so
 * it shows up in the inbox.
 */
class SendCekbotBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /** Seconds to wait between messages (ban-risk throttle). */
    private const THROTTLE_SECONDS = 2;

    public function __construct(public int $broadcastId) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('cekbot-broadcast-'.$this->broadcastId))->dontRelease()];
    }

    public function handle(WahaSessionManager $waha): void
    {
        $broadcast = CekbotBroadcast::with('session')->find($this->broadcastId);

        if (! $broadcast || $broadcast->status === CekbotBroadcast::STATUS_SENT) {
            return;
        }

        $broadcast->update([
            'status' => CekbotBroadcast::STATUS_SENDING,
            'started_at' => $broadcast->started_at ?? now(),
        ]);

        $session = $broadcast->session;

        $broadcast->recipients()
            ->where('status', 'pending')
            ->with('conversation')
            ->chunkById(100, function ($recipients) use ($broadcast, $session, $waha) {
                $throttle = (int) config('cekbot.broadcast_throttle_seconds', self::THROTTLE_SECONDS);
                foreach ($recipients as $recipient) {
                    $this->sendOne($broadcast, $session, $recipient, $waha);
                    if ($throttle > 0) {
                        sleep($throttle);
                    }
                }
            });

        $broadcast->update([
            'status' => CekbotBroadcast::STATUS_SENT,
            'completed_at' => now(),
        ]);
    }

    private function sendOne(CekbotBroadcast $broadcast, $session, CekbotBroadcastRecipient $recipient, WahaSessionManager $waha): void
    {
        $conversation = $recipient->conversation;

        if (! $conversation) {
            $recipient->update(['status' => 'failed', 'error' => 'Conversation missing']);

            return;
        }

        $text = str_replace('{name}', (string) ($conversation->name ?? ''), $broadcast->message);

        $result = $waha->sendText($session->session_name, $conversation->chat_id, $text);

        CekbotMessage::create([
            'cekbot_conversation_id' => $conversation->id,
            'cekbot_session_id' => $session->id,
            'waha_message_id' => $result['message_id'] ?? null,
            'direction' => CekbotMessage::DIRECTION_OUT,
            'from_me' => true,
            'type' => 'text',
            'body' => $text,
            'ack' => $result['success'] ? 'sent' : 'failed',
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now(), 'last_message_preview' => Str::limit($text, 255)]);

        if ($result['success']) {
            $recipient->update(['status' => 'sent', 'sent_at' => now()]);
            $broadcast->increment('sent_count');
        } else {
            $recipient->update(['status' => 'failed', 'error' => $result['error'] ?? 'Gagal']);
            $broadcast->increment('failed_count');
        }
    }
}
