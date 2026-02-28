<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload  The raw webhook payload from Meta.
     */
    public function __construct(public array $payload) {}

    /**
     * Process the Meta WhatsApp webhook payload.
     *
     * Handles delivery status updates, incoming messages, and error notifications.
     */
    public function handle(): void
    {
        $entries = $this->payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                $value = $change['value'] ?? [];

                $this->processStatuses($value['statuses'] ?? []);
                $this->processMessages($value['messages'] ?? [], $value['contacts'] ?? []);
                $this->processErrors($value['errors'] ?? []);
            }
        }
    }

    /**
     * Process delivery status updates (sent, delivered, read, failed).
     *
     * @param  array<int, array<string, mixed>>  $statuses
     */
    private function processStatuses(array $statuses): void
    {
        foreach ($statuses as $status) {
            $messageId = $status['id'] ?? null;
            $statusValue = $status['status'] ?? null;

            if (! $messageId || ! $statusValue) {
                continue;
            }

            $log = NotificationLog::where('message_id', $messageId)->first();

            if (! $log) {
                Log::debug('WhatsApp webhook: no NotificationLog found for message_id', [
                    'message_id' => $messageId,
                    'status' => $statusValue,
                ]);

                continue;
            }

            match ($statusValue) {
                'sent' => $this->handleSentStatus($log),
                'delivered' => $log->markAsDelivered(),
                'read' => $log->markAsRead(),
                'failed' => $this->handleFailedStatus($log, $status['errors'] ?? []),
                default => Log::info('WhatsApp webhook: unhandled status', [
                    'message_id' => $messageId,
                    'status' => $statusValue,
                ]),
            };
        }
    }

    /**
     * Handle "sent" status — only update if not already sent or beyond.
     */
    private function handleSentStatus(NotificationLog $log): void
    {
        if ($log->isPending()) {
            $log->markAsSent();
        }
    }

    /**
     * Handle "failed" status with error details from the payload.
     *
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function handleFailedStatus(NotificationLog $log, array $errors): void
    {
        $errorDetails = collect($errors)->map(function (array $error) {
            $code = $error['code'] ?? 'unknown';
            $title = $error['title'] ?? 'Unknown error';

            return "[$code] $title";
        })->implode('; ');

        $log->markAsFailed($errorDetails ?: 'Unknown error');
    }

    /**
     * Process incoming messages (will be fully implemented in Task 4.3).
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $contacts
     */
    private function processMessages(array $messages, array $contacts): void
    {
        if (empty($messages)) {
            return;
        }

        Log::info('WhatsApp webhook: incoming messages received', [
            'count' => count($messages),
            'contacts' => collect($contacts)->pluck('wa_id')->toArray(),
        ]);
    }

    /**
     * Process error notifications from the webhook.
     *
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function processErrors(array $errors): void
    {
        foreach ($errors as $error) {
            Log::error('WhatsApp webhook: error notification received', [
                'code' => $error['code'] ?? 'unknown',
                'title' => $error['title'] ?? 'Unknown error',
                'message' => $error['message'] ?? '',
                'error_data' => $error['error_data'] ?? [],
            ]);
        }
    }
}
