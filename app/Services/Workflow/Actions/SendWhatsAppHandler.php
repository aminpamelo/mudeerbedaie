<?php

namespace App\Services\Workflow\Actions;

use App\Models\MessageTemplate;
use App\Models\Student;
use App\Services\MergeTag\MergeTagEngine;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Support\Facades\Log;

class SendWhatsAppHandler implements ActionHandlerInterface
{
    protected MergeTagEngine $mergeTagEngine;

    public function __construct(
        private WhatsAppManager $whatsAppManager,
    ) {
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

            // Send via configured WhatsApp provider
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
     * Send WhatsApp message via the configured provider.
     */
    protected function sendWhatsAppMessage(string $phone, string $message): bool
    {
        $provider = $this->whatsAppManager->provider();

        if (! $provider->isConfigured()) {
            Log::warning('WhatsApp provider not configured, logging message instead', [
                'provider' => $provider->getName(),
                'phone' => $phone,
                'message' => substr($message, 0, 100).'...',
            ]);

            return true;
        }

        $result = $provider->send($phone, $message);

        if ($result['success']) {
            Log::info('WhatsApp message sent via provider', [
                'provider' => $provider->getName(),
                'phone' => $phone,
                'message_id' => $result['message_id'] ?? null,
            ]);

            return true;
        }

        Log::error('WhatsApp provider send failed', [
            'provider' => $provider->getName(),
            'phone' => $phone,
            'error' => $result['error'] ?? 'Unknown error',
        ]);

        return false;
    }
}
