<?php

namespace App\Jobs;

use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Services\Cekbot\CekbotBotService;
use App\Services\Cekbot\CekbotInboundIngestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Processes one Meta Cloud API webhook `value` block for a Cekbot number.
 *
 * The number is matched by metadata.phone_number_id. Inbound messages are
 * normalised into the shared CekbotInboundIngestor (so an official number gets
 * the same flows / auto-reply / AI / handover as a WAHA one); delivery statuses
 * update the corresponding message's ack.
 */
class ProcessCekbotCloudWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $value  One entry.changes[].value block.
     */
    public function __construct(public array $value) {}

    public function handle(CekbotBotService $bot, CekbotInboundIngestor $ingestor): void
    {
        $phoneNumberId = $this->value['metadata']['phone_number_id'] ?? null;

        if (! $phoneNumberId) {
            return;
        }

        $session = CekbotSession::query()
            ->where('provider', CekbotSession::PROVIDER_CLOUD_API)
            ->where('phone_number_id', $phoneNumberId)
            ->first();

        if (! $session) {
            return;
        }

        /** @var Collection<string, array<string, mixed>> $contacts */
        $contacts = collect($this->value['contacts'] ?? [])->keyBy('wa_id');

        foreach ($this->value['messages'] ?? [] as $message) {
            $contact = $contacts->get($message['from'] ?? '', []);
            $this->ingestMessage($session, $message, is_array($contact) ? $contact : [], $bot, $ingestor);
        }

        foreach ($this->value['statuses'] ?? [] as $status) {
            $this->applyStatus($status);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $contact
     */
    private function ingestMessage(CekbotSession $session, array $message, array $contact, CekbotBotService $bot, CekbotInboundIngestor $ingestor): void
    {
        $from = $message['from'] ?? null;

        if (! $from) {
            return;
        }

        $metaType = (string) ($message['type'] ?? 'text');

        $ingestor->ingest($session, [
            'provider_message_id' => $message['id'] ?? null,
            'from_me' => false,
            'chat_id' => $from,
            'name' => $contact['profile']['name'] ?? null,
            'type' => $this->resolveType($metaType),
            'body' => $this->extractBody($message, $metaType),
            // Cloud inbound media arrives as a media id (needs a token to fetch),
            // not a public URL — deferred; the raw payload is stored for later.
            'media_url' => null,
            'media_mime' => $message[$metaType]['mime_type'] ?? null,
            'timestamp' => isset($message['timestamp']) ? (int) $message['timestamp'] : null,
            'is_group' => false,
            'payload' => $message,
        ], $bot);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function applyStatus(array $status): void
    {
        $wamid = $status['id'] ?? null;
        $state = $status['status'] ?? null;

        if ($wamid && $state) {
            CekbotMessage::query()
                ->where('waha_message_id', $wamid)
                ->update(['ack' => (string) $state]);
        }
    }

    /**
     * Map Meta's message type onto Cekbot's internal type set.
     */
    private function resolveType(string $metaType): string
    {
        return match ($metaType) {
            'image', 'sticker' => 'image',
            'video' => 'video',
            'audio', 'voice' => 'audio',
            'document' => 'document',
            'location' => 'location',
            'contacts' => 'contact',
            default => 'text',
        };
    }

    /**
     * Extract a readable body for the message type. Quick-reply / list / button
     * taps are surfaced as their title text so keyword rules and flows match.
     *
     * @param  array<string, mixed>  $message
     */
    private function extractBody(array $message, string $type): ?string
    {
        return match ($type) {
            'text' => $message['text']['body'] ?? null,
            'image' => $message['image']['caption'] ?? null,
            'video' => $message['video']['caption'] ?? null,
            'document' => $message['document']['caption'] ?? ($message['document']['filename'] ?? null),
            'button' => $message['button']['text'] ?? null,
            'interactive' => $message['interactive']['button_reply']['title']
                ?? ($message['interactive']['list_reply']['title'] ?? null),
            'location' => $this->formatLocation($message['location'] ?? []),
            'contacts' => 'Contact shared',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $location
     */
    private function formatLocation(array $location): string
    {
        $lat = $location['latitude'] ?? 0;
        $lon = $location['longitude'] ?? 0;
        $name = $location['name'] ?? '';

        return $name ? "{$name} ({$lat}, {$lon})" : "Location: {$lat}, {$lon}";
    }
}
