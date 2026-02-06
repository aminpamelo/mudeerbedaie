<?php

declare(strict_types=1);

namespace App\Services\Funnel;

use App\Models\FunnelAutomation;
use App\Models\FunnelAutomationAction;
use App\Models\FunnelAutomationLog;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\ProductOrder;
use App\Services\MergeTag\MergeTagEngine;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FunnelAutomationService
{
    public function __construct(
        protected MergeTagEngine $mergeTagEngine,
        protected WhatsAppService $whatsAppService
    ) {}

    /**
     * Trigger automations for a specific event type.
     *
     * @param  string  $eventType  The trigger type (e.g., 'purchase', 'cart_abandonment')
     * @param  array  $context  The context data for the automation
     * @param  int|null  $funnelId  Optional funnel ID to filter automations
     */
    public function trigger(string $eventType, array $context, ?int $funnelId = null): void
    {
        $automations = $this->findMatchingAutomations($eventType, $context, $funnelId);

        foreach ($automations as $automation) {
            $this->executeAutomation($automation, $context);
        }
    }

    /**
     * Trigger automation for a purchase completed event.
     */
    public function triggerPurchaseCompleted(ProductOrder $productOrder, ?FunnelSession $session = null): void
    {
        // Build context from order
        $context = $this->buildPurchaseContext($productOrder, $session);

        // Get funnel_id from order metadata or FunnelOrder
        $funnelId = $productOrder->metadata['funnel_id'] ?? null;
        if (! $funnelId && $session) {
            $funnelId = $session->funnel_id;
        }

        // Also check FunnelOrder for funnel_id
        if (! $funnelId) {
            $funnelOrder = FunnelOrder::where('product_order_id', $productOrder->id)->first();
            if ($funnelOrder) {
                $funnelId = $funnelOrder->funnel_id;
            }
        }

        Log::info('FunnelAutomation: Triggering purchase_completed', [
            'order_id' => $productOrder->id,
            'order_number' => $productOrder->order_number,
            'funnel_id' => $funnelId,
            'session_id' => $session?->id,
        ]);

        // Trigger with 'purchase_completed' trigger type (matches automation config)
        $this->trigger('purchase_completed', $context, $funnelId);
    }

    /**
     * Find automations that match the given event and context.
     */
    protected function findMatchingAutomations(string $eventType, array $context, ?int $funnelId = null): \Illuminate\Support\Collection
    {
        $query = FunnelAutomation::query()
            ->active()
            ->with('actions')
            ->orderBy('priority', 'desc');

        // Filter by funnel if specified
        if ($funnelId) {
            $query->where(function ($q) use ($funnelId) {
                $q->where('funnel_id', $funnelId)
                    ->orWhereNull('funnel_id'); // Also include global automations
            });
        }

        return $query->get()->filter(function (FunnelAutomation $automation) use ($eventType, $context) {
            return $automation->matchesTrigger($eventType, $context);
        });
    }

    /**
     * Execute all actions in an automation.
     */
    protected function executeAutomation(FunnelAutomation $automation, array $context): void
    {
        Log::info('FunnelAutomation: Executing automation', [
            'automation_id' => $automation->id,
            'automation_name' => $automation->name,
            'trigger_type' => $automation->trigger_type,
        ]);

        // Set context for merge tag engine
        $this->mergeTagEngine->setContext($context);

        foreach ($automation->actions as $action) {
            $this->executeAction($automation, $action, $context);
        }
    }

    /**
     * Execute a single automation action.
     */
    protected function executeAction(FunnelAutomation $automation, FunnelAutomationAction $action, array $context): void
    {
        // Check if action has conditions that need to be evaluated
        if (! $action->evaluateConditions($context)) {
            $this->logAction($automation, $action, $context, 'skipped', [
                'reason' => 'Conditions not met',
            ]);

            return;
        }

        // Handle delay
        if ($action->hasDelay()) {
            $this->scheduleAction($automation, $action, $context);

            return;
        }

        // Execute based on action type
        $result = match ($action->action_type) {
            'send_whatsapp' => $this->executeSendWhatsApp($action, $context),
            'send_email' => $this->executeSendEmail($action, $context),
            'webhook' => $this->executeWebhook($action, $context),
            'add_tag' => $this->executeAddTag($action, $context),
            'remove_tag' => $this->executeRemoveTag($action, $context),
            default => ['success' => false, 'error' => 'Unknown action type: '.$action->action_type],
        };

        // Log the result
        $status = $result['success'] ? 'executed' : 'failed';
        $this->logAction($automation, $action, $context, $status, $result);
    }

    /**
     * Execute send_whatsapp action.
     */
    protected function executeSendWhatsApp(FunnelAutomationAction $action, array $context): array
    {
        $config = $action->action_config ?? [];
        $message = $config['message'] ?? $config['template'] ?? '';
        $phoneField = $config['phone_field'] ?? 'contact.phone';

        // Get phone number from context
        $phone = $this->getValueFromContext($phoneField, $context);

        if (empty($phone)) {
            return [
                'success' => false,
                'error' => 'No phone number found in context',
            ];
        }

        // Process merge tags in message
        $processedMessage = $this->mergeTagEngine->resolve($message);

        Log::info('FunnelAutomation: Sending WhatsApp', [
            'phone' => $phone,
            'message_length' => strlen($processedMessage),
        ]);

        // Send via WhatsApp service
        $result = $this->whatsAppService->send($phone, $processedMessage);

        return [
            'success' => $result['success'],
            'message_id' => $result['message_id'] ?? null,
            'error' => $result['error'] ?? null,
            'phone' => $phone,
        ];
    }

    /**
     * Execute send_email action.
     */
    protected function executeSendEmail(FunnelAutomationAction $action, array $context): array
    {
        $config = $action->action_config ?? [];
        $subject = $config['subject'] ?? 'Notification';
        $body = $config['body'] ?? $config['template'] ?? '';
        $emailField = $config['email_field'] ?? 'contact.email';

        // Get email from context
        $email = $this->getValueFromContext($emailField, $context);

        if (empty($email)) {
            return [
                'success' => false,
                'error' => 'No email address found in context',
            ];
        }

        // Process merge tags
        $processedSubject = $this->mergeTagEngine->resolve($subject);
        $processedBody = $this->mergeTagEngine->resolve($body);

        try {
            Mail::raw($processedBody, function ($message) use ($email, $processedSubject) {
                $message->to($email)
                    ->subject($processedSubject);
            });

            return [
                'success' => true,
                'email' => $email,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'email' => $email,
            ];
        }
    }

    /**
     * Execute webhook action.
     */
    protected function executeWebhook(FunnelAutomationAction $action, array $context): array
    {
        $config = $action->action_config ?? [];
        $url = $config['url'] ?? '';
        $method = strtoupper($config['method'] ?? 'POST');
        $headers = $config['headers'] ?? [];

        if (empty($url)) {
            return [
                'success' => false,
                'error' => 'No webhook URL configured',
            ];
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->{strtolower($method)}($url, $context);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute add_tag action.
     */
    protected function executeAddTag(FunnelAutomationAction $action, array $context): array
    {
        $config = $action->action_config ?? [];
        $tagName = $config['tag'] ?? $config['tag_name'] ?? '';

        // This would integrate with a CRM/tagging system
        // For now, just return success
        return [
            'success' => true,
            'tag' => $tagName,
            'action' => 'added',
        ];
    }

    /**
     * Execute remove_tag action.
     */
    protected function executeRemoveTag(FunnelAutomationAction $action, array $context): array
    {
        $config = $action->action_config ?? [];
        $tagName = $config['tag'] ?? $config['tag_name'] ?? '';

        return [
            'success' => true,
            'tag' => $tagName,
            'action' => 'removed',
        ];
    }

    /**
     * Schedule an action for later execution.
     */
    protected function scheduleAction(FunnelAutomation $automation, FunnelAutomationAction $action, array $context): void
    {
        $scheduledAt = $action->getScheduledTime();

        FunnelAutomationLog::create([
            'automation_id' => $automation->id,
            'action_id' => $action->id,
            'session_id' => $context['session_id'] ?? null,
            'contact_email' => $context['contact']['email'] ?? $context['order']['customer_email'] ?? null,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
            'result' => ['context' => $context],
        ]);

        Log::info('FunnelAutomation: Action scheduled', [
            'automation_id' => $automation->id,
            'action_id' => $action->id,
            'scheduled_at' => $scheduledAt->toDateTimeString(),
        ]);
    }

    /**
     * Log an action execution.
     */
    protected function logAction(
        FunnelAutomation $automation,
        FunnelAutomationAction $action,
        array $context,
        string $status,
        array $result
    ): void {
        FunnelAutomationLog::create([
            'automation_id' => $automation->id,
            'action_id' => $action->id,
            'session_id' => $context['session_id'] ?? null,
            'contact_email' => $context['contact']['email'] ?? $context['order']['customer_email'] ?? null,
            'status' => $status,
            'executed_at' => now(),
            'result' => $result,
        ]);

        Log::info('FunnelAutomation: Action logged', [
            'automation_id' => $automation->id,
            'action_id' => $action->id,
            'status' => $status,
        ]);
    }

    /**
     * Build context from a purchase order.
     * The context includes both model instances (for data providers) and flat data (for direct access).
     */
    protected function buildPurchaseContext(ProductOrder $order, ?FunnelSession $session = null): array
    {
        return [
            // Model instances for MergeTag data providers
            'product_order' => $order,
            'funnel_session' => $session,

            // Flat data for direct context access
            'session_id' => $session?->id,
            'contact' => [
                'name' => $order->customer_name,
                'email' => $order->guest_email ?? $order->user?->email,
                'phone' => $order->customer_phone,
                'first_name' => explode(' ', $order->customer_name ?? '')[0] ?? '',
            ],
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total_amount,
                'subtotal' => $order->subtotal,
                'currency' => $order->currency,
                'payment_method' => $order->payment_method,
                'status' => $order->status,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->guest_email ?? $order->user?->email,
                'customer_phone' => $order->customer_phone,
            ],
            'funnel' => [
                'id' => $order->metadata['funnel_id'] ?? $session?->funnel_id,
                'slug' => $order->metadata['funnel_slug'] ?? $session?->funnel?->slug,
            ],
        ];
    }

    /**
     * Get a value from nested context using dot notation.
     */
    protected function getValueFromContext(string $path, array $context): mixed
    {
        $segments = explode('.', $path);
        $value = $context;

        foreach ($segments as $segment) {
            if (is_array($value) && isset($value[$segment])) {
                $value = $value[$segment];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Process scheduled actions that are due.
     */
    public function processScheduledActions(): int
    {
        $dueActions = FunnelAutomationLog::due()
            ->with(['automation', 'action'])
            ->get();

        $processed = 0;

        foreach ($dueActions as $log) {
            if (! $log->automation || ! $log->action) {
                $log->markAsFailed(['error' => 'Automation or action not found']);

                continue;
            }

            $context = $log->result['context'] ?? [];
            $this->mergeTagEngine->setContext($context);

            $result = match ($log->action->action_type) {
                'send_whatsapp' => $this->executeSendWhatsApp($log->action, $context),
                'send_email' => $this->executeSendEmail($log->action, $context),
                'webhook' => $this->executeWebhook($log->action, $context),
                'add_tag' => $this->executeAddTag($log->action, $context),
                'remove_tag' => $this->executeRemoveTag($log->action, $context),
                default => ['success' => false, 'error' => 'Unknown action type'],
            };

            if ($result['success']) {
                $log->markAsExecuted($result);
            } else {
                $log->markAsFailed($result);
            }

            $processed++;
        }

        return $processed;
    }
}
