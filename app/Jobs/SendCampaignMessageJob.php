<?php

namespace App\Jobs;

use App\Models\WhatsAppCampaignRecipient;
use App\Services\WhatsApp\WhatsAppBlastService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendCampaignMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300; // 5 minutes between retries

    public function __construct(public int $recipientId) {}

    /**
     * Serialize with every other WhatsApp send so the blast respects the
     * provider's rate limits instead of firing in parallel.
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('whatsapp-send'),
        ];
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    public function handle(WhatsAppService $whatsApp, WhatsAppBlastService $blast): void
    {
        $recipient = WhatsAppCampaignRecipient::with('campaign')->find($this->recipientId);

        if (! $recipient || ! $recipient->campaign) {
            return;
        }

        $campaign = $recipient->campaign;

        if ($campaign->status === 'cancelled') {
            return;
        }

        // A recipient left in 'sending' means a previous attempt crashed after
        // (possibly) delivering the message. Never re-send — that would be a
        // duplicate, billable message. Record it as failed for manual follow-up.
        if ($recipient->status === 'sending') {
            $recipient->update([
                'status' => 'failed',
                'error_message' => 'Interrupted before confirmation; not retried to avoid a duplicate message.',
            ]);
            $campaign->increment('failed_count');
            $this->finalizeIfDone($recipient);

            return;
        }

        // Already sent / delivered / read / failed / skipped.
        if ($recipient->status !== 'pending') {
            return;
        }

        // Respect allowed-hours / daily-limit throttling (shared with other sends).
        // Done BEFORE claiming so a deferral leaves the recipient re-runnable.
        if (! $whatsApp->canSendNow()) {
            $next = $whatsApp->getNextAllowedSendTime();
            $this->release($next ? (int) now()->diffInSeconds($next) : 3600);

            return;
        }

        $pauseUntil = Cache::get('whatsapp_batch_pause_until');
        if ($pauseUntil && now()->lt($pauseUntil)) {
            $this->release((int) now()->diffInSeconds($pauseUntil));

            return;
        }

        // Atomically claim the recipient (pending -> sending) so a retry after a
        // mid-send crash can never send a second message.
        $claimed = WhatsAppCampaignRecipient::query()
            ->where('id', $recipient->id)
            ->where('status', 'pending')
            ->update(['status' => 'sending']);

        if ($claimed === 0) {
            return; // claimed/processed by another attempt
        }

        if ($campaign->status === 'queued') {
            $campaign->update(['status' => 'sending']);
        }

        $components = $blast->buildComponents($recipient, $campaign->variable_mapping ?? []);

        $result = $whatsApp->sendTemplate(
            $recipient->phone,
            $campaign->template_name,
            $campaign->template_language,
            $components,
        );

        if ($result['success'] ?? false) {
            $recipient->update([
                'status' => 'sent',
                'wamid' => $result['message_id'] ?? null,
                'sent_at' => now(),
                'error_message' => null,
            ]);
            $campaign->increment('sent_count');
        } else {
            $recipient->update([
                'status' => 'failed',
                'error_message' => $result['error'] ?? 'Unknown error',
            ]);
            $campaign->increment('failed_count');
        }

        if ($whatsApp->shouldPauseBatch()) {
            Cache::put('whatsapp_batch_pause_until', now()->addSeconds($whatsApp->getBatchPauseDuration()));
        }

        $this->finalizeIfDone($recipient);

        // Gentle spacing between sends.
        sleep(random_int(1, 3));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendCampaignMessageJob failed', [
            'recipient_id' => $this->recipientId,
            'error' => $exception->getMessage(),
        ]);

        $recipient = WhatsAppCampaignRecipient::with('campaign')->find($this->recipientId);

        if ($recipient && in_array($recipient->status, ['pending', 'sending'], true)) {
            $recipient->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
            $recipient->campaign?->increment('failed_count');
            $this->finalizeIfDone($recipient);
        }
    }

    /**
     * Mark the campaign completed once no recipients remain in flight.
     */
    protected function finalizeIfDone(WhatsAppCampaignRecipient $recipient): void
    {
        $campaign = $recipient->campaign;

        if (! $campaign || $campaign->isFinished()) {
            return;
        }

        if ($campaign->recipients()->whereIn('status', ['pending', 'sending'])->doesntExist()) {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }
}
