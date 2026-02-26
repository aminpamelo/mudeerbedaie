<?php

namespace App\Services\Workflow\Actions;

use App\Models\MessageTemplate;
use App\Models\Student;
use App\Services\MergeTag\MergeTagEngine;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsAppHandler implements ActionHandlerInterface
{
    protected MergeTagEngine $mergeTagEngine;

    public function __construct()
    {
        $this->mergeTagEngine = new MergeTagEngine;
    }

    public function execute(Student $student, array $config, array $context = []): array
    {
        $templateId = $config['template_id'] ?? null;
        $message = $config['message'] ?? null;

        // Build context for merge tag resolution
        $mergeContext = array_merge([
            'student' => $student,
            'name' => $student->name,
            'email' => $student->email,
            'phone' => $student->phone,
        ], $context);

        // Get phone from context (support both funnel orders and student)
        $phone = $this->getPhoneNumber($mergeContext);

        if (! $phone) {
            return [
                'success' => false,
                'message' => 'No phone number found for contact',
            ];
        }

        try {
            // If using a template
            if ($templateId) {
                $template = MessageTemplate::find($templateId);
                if (! $template) {
                    return [
                        'success' => false,
                        'message' => 'WhatsApp template not found',
                    ];
                }
                $message = $template->body;
            }

            if (! $message) {
                return [
                    'success' => false,
                    'message' => 'WhatsApp message is required',
                ];
            }

            // Use MergeTagEngine to resolve merge tags
            $message = $this->resolveMessage($message, $mergeContext);

            // Format phone number (remove non-digits, ensure country code)
            $formattedPhone = $this->formatPhoneNumber($phone);

            // Send via WhatsApp API (OnSend API)
            $sent = $this->sendWhatsAppMessage($formattedPhone, $message);

            if (! $sent) {
                return [
                    'success' => false,
                    'message' => 'Failed to send WhatsApp message',
                ];
            }

            Log::info('WhatsApp message sent', [
                'phone' => $formattedPhone,
                'student_id' => $student->id ?? null,
                'order_id' => $context['product_order']->id ?? $context['order']->id ?? null,
            ]);

            return [
                'success' => true,
                'message' => 'WhatsApp message sent successfully',
                'data' => [
                    'phone' => $formattedPhone,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp message', [
                'student_id' => $student->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send WhatsApp: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Resolve merge tags in the message using MergeTagEngine.
     */
    protected function resolveMessage(string $message, array $context): string
    {
        // Set context and resolve all merge tags
        $this->mergeTagEngine->setContext($context);

        return $this->mergeTagEngine->resolve($message);
    }

    /**
     * Get phone number from context (prioritizes order/contact phone over student phone).
     */
    protected function getPhoneNumber(array $context): ?string
    {
        // Priority: product_order -> order -> funnel_session -> student -> direct context
        $order = $context['product_order'] ?? $context['order'] ?? null;
        if ($order && $order->customer_phone) {
            return $order->customer_phone;
        }

        $session = $context['funnel_session'] ?? $context['session'] ?? null;
        if ($session && $session->phone) {
            return $session->phone;
        }

        $student = $context['student'] ?? null;
        if ($student && $student->phone) {
            return $student->phone;
        }

        // Direct context values
        return $context['phone'] ?? $context['customer_phone'] ?? null;
    }

    /**
     * Format phone number for WhatsApp.
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove non-digits and plus sign
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Remove leading plus if present
        $phone = ltrim($phone, '+');

        // Add Malaysia country code if not present
        if (str_starts_with($phone, '0')) {
            $phone = '60'.substr($phone, 1);
        } elseif (! str_starts_with($phone, '60')) {
            $phone = '60'.$phone;
        }

        return $phone;
    }

    /**
     * Send WhatsApp message via OnSend API.
     */
    protected function sendWhatsAppMessage(string $phone, string $message): bool
    {
        // Check if WhatsApp API is configured
        $apiUrl = config('services.whatsapp.api_url') ?? config('services.onsend.api_url');
        $apiToken = config('services.whatsapp.api_token') ?? config('services.onsend.api_token');
        $deviceId = config('services.whatsapp.device_id') ?? config('services.onsend.device_id');

        if (! $apiUrl || ! $apiToken) {
            Log::warning('WhatsApp API not configured, logging message instead', [
                'phone' => $phone,
                'message' => substr($message, 0, 100).'...',
            ]);

            // Return true for development/testing
            return true;
        }

        try {
            // OnSend API format
            $payload = [
                'phone' => $phone,
                'message' => $message,
            ];

            if ($deviceId) {
                $payload['device_id'] = $deviceId;
            }

            $response = Http::withToken($apiToken)
                ->timeout(30)
                ->post($apiUrl, $payload);

            if ($response->successful()) {
                Log::info('WhatsApp message sent via API', [
                    'phone' => $phone,
                    'response' => $response->json(),
                ]);

                return true;
            }

            Log::error('WhatsApp API returned error', [
                'phone' => $phone,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('WhatsApp API error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
