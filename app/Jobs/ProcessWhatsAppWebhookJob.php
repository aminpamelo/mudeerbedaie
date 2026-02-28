<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\Student;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

            // Update NotificationLog
            $log = NotificationLog::where('message_id', $messageId)->first();

            if ($log) {
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
            } else {
                Log::debug('WhatsApp webhook: no NotificationLog found for message_id', [
                    'message_id' => $messageId,
                    'status' => $statusValue,
                ]);
            }

            // Also update WhatsAppMessage by wamid
            $whatsappMessage = WhatsAppMessage::where('wamid', $messageId)->first();

            if ($whatsappMessage) {
                $whatsappMessage->update([
                    'status' => $statusValue,
                    'status_updated_at' => now(),
                ]);

                if ($statusValue === 'failed') {
                    $errors = $status['errors'] ?? [];
                    $errorCode = $errors[0]['code'] ?? null;
                    $errorMessage = collect($errors)->map(function (array $error) {
                        return '['.($error['code'] ?? 'unknown').'] '.($error['title'] ?? 'Unknown error');
                    })->implode('; ');

                    $whatsappMessage->update([
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage ?: 'Unknown error',
                    ]);
                }
            }
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
     * Process incoming messages from the webhook.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $contacts
     */
    private function processMessages(array $messages, array $contacts): void
    {
        if (empty($messages)) {
            return;
        }

        // Index contacts by wa_id for easy lookup
        $contactsByWaId = collect($contacts)->keyBy('wa_id');

        foreach ($messages as $message) {
            $phoneNumber = $message['from'] ?? null;

            if (! $phoneNumber) {
                continue;
            }

            // Get contact name from matching contact
            $contactName = null;
            $contact = $contactsByWaId->get($phoneNumber);
            if ($contact) {
                $contactName = $contact['profile']['name'] ?? null;
            }

            // Find or create conversation
            $conversation = WhatsAppConversation::firstOrCreate(
                ['phone_number' => $phoneNumber],
                [
                    'contact_name' => $contactName,
                    'status' => 'active',
                ]
            );

            // Update contact name if we have a new one
            if ($contactName && $conversation->contact_name !== $contactName) {
                $conversation->update(['contact_name' => $contactName]);
            }

            // Try to match phone to a student
            if (! $conversation->student_id) {
                $student = $this->findStudentByPhone($phoneNumber);
                if ($student) {
                    $conversation->update(['student_id' => $student->id]);
                }
            }

            // Extract message body based on type
            $messageType = $message['type'] ?? 'text';
            $body = $this->extractMessageBody($message, $messageType);

            // Create the WhatsAppMessage
            WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'wamid' => $message['id'] ?? null,
                'type' => $messageType,
                'body' => $body,
                'media_url' => $message[$messageType]['link'] ?? null,
                'media_mime_type' => $message[$messageType]['mime_type'] ?? null,
                'media_filename' => $message[$messageType]['filename'] ?? null,
                'status' => 'delivered',
                'metadata' => $message,
            ]);

            // Update conversation
            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => $body ? Str::limit($body, 255) : "[$messageType]",
                'unread_count' => $conversation->unread_count + 1,
                'is_service_window_open' => true,
                'service_window_expires_at' => now()->addHours(24),
            ]);
        }

        Log::info('WhatsApp webhook: incoming messages processed', [
            'count' => count($messages),
        ]);
    }

    /**
     * Extract message body text based on message type.
     *
     * @param  array<string, mixed>  $message
     */
    private function extractMessageBody(array $message, string $type): ?string
    {
        return match ($type) {
            'text' => $message['text']['body'] ?? null,
            'image' => $message['image']['caption'] ?? null,
            'video' => $message['video']['caption'] ?? null,
            'document' => $message['document']['caption'] ?? $message['document']['filename'] ?? null,
            'audio' => null,
            'sticker' => null,
            'location' => $this->formatLocation($message['location'] ?? []),
            'contacts' => 'Contact shared',
            default => null,
        };
    }

    /**
     * Format a location message body.
     *
     * @param  array<string, mixed>  $location
     */
    private function formatLocation(array $location): string
    {
        $lat = $location['latitude'] ?? 0;
        $lon = $location['longitude'] ?? 0;
        $name = $location['name'] ?? '';

        return $name ? "{$name} ({$lat}, {$lon})" : "Location: {$lat}, {$lon}";
    }

    /**
     * Try to find a student by matching the incoming phone number.
     *
     * Handles Malaysia format: incoming might be 60123456789, student phone might be 0123456789
     */
    private function findStudentByPhone(string $phoneNumber): ?Student
    {
        // Try direct match first
        $student = Student::where('phone', $phoneNumber)->first();
        if ($student) {
            return $student;
        }

        // Try Malaysia format: strip leading 60 and add leading 0
        if (str_starts_with($phoneNumber, '60')) {
            $localNumber = '0'.substr($phoneNumber, 2);
            $student = Student::where('phone', $localNumber)->first();
            if ($student) {
                return $student;
            }
        }

        // Try adding 60 prefix if number starts with 0
        if (str_starts_with($phoneNumber, '0')) {
            $internationalNumber = '60'.substr($phoneNumber, 1);
            $student = Student::where('phone', $internationalNumber)->first();
            if ($student) {
                return $student;
            }
        }

        return null;
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
