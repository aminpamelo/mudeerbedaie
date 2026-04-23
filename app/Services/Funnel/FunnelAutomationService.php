<?php

declare(strict_types=1);

namespace App\Services\Funnel;

use App\Models\FunnelAutomation;
use App\Models\FunnelAutomationAction;
use App\Models\FunnelAutomationLog;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\ProductOrder;
use App\Models\WhatsAppTemplate;
use App\Services\MergeTag\MergeTagEngine;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FunnelAutomationService
{
    public function __construct(
        protected MergeTagEngine $mergeTagEngine,
        protected WhatsAppService $whatsAppService,
        protected WhatsAppManager $whatsAppManager
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
        $provider = $config['provider'] ?? 'onsend';
        $phoneField = $config['phone_field'] ?? 'contact.phone';

        // Get phone number from context
        $phone = $this->getValueFromContext($phoneField, $context);

        if (empty($phone)) {
            return [
                'success' => false,
                'error' => 'No phone number found in context',
            ];
        }

        if ($provider === 'waba') {
            return $this->executeSendWhatsAppWaba($config, $phone, $context);
        }

        return $this->executeSendWhatsAppOnsend($config, $phone);
    }

    /**
     * Execute WhatsApp send via Onsend provider.
     */
    protected function executeSendWhatsAppOnsend(array $config, string $phone): array
    {
        $message = $config['message'] ?? $config['template'] ?? '';
        $processedMessage = $this->mergeTagEngine->resolve($message);

        Log::info('FunnelAutomation: Sending WhatsApp via Onsend', [
            'phone' => $phone,
            'message_length' => strlen($processedMessage),
        ]);

        $result = $this->whatsAppService->send($phone, $processedMessage);

        return [
            'success' => $result['success'],
            'message_id' => $result['message_id'] ?? null,
            'error' => $result['error'] ?? null,
            'phone' => $phone,
            'provider' => 'onsend',
        ];
    }

    /**
     * Execute WhatsApp send via WABA template provider.
     */
    protected function executeSendWhatsAppWaba(array $config, string $phone, array $context): array
    {
        // Look up template
        $template = null;
        if (! empty($config['template_id'])) {
            $template = WhatsAppTemplate::find($config['template_id']);
        }

        $templateName = $template?->name ?? $config['template_name'] ?? '';
        $templateLanguage = $template?->language ?? $config['template_language'] ?? 'en';

        if (empty($templateName)) {
            return [
                'success' => false,
                'error' => 'No WhatsApp template configured',
            ];
        }

        // Check template is approved (if we found it locally)
        if ($template && $template->status !== 'APPROVED') {
            return [
                'success' => false,
                'error' => "Template '{$templateName}' is not approved (status: {$template->status})",
            ];
        }

        // Resolve merge tags in template variables.
        // Preference order:
        //   1. action_config['template_variables'] (per-action override)
        //   2. template.variable_mappings (template-level defaults — shared across funnel/ecommerce)
        $templateVariables = $config['template_variables'] ?? [];
        if (empty($templateVariables) && $template && ! empty($template->variable_mappings)) {
            $templateVariables = $this->normalizeTemplateMappings($template->variable_mappings);
        }
        $components = $this->buildWabaComponents($templateVariables, $context);

        Log::info('FunnelAutomation: Sending WhatsApp via WABA template', [
            'phone' => $phone,
            'template' => $templateName,
            'language' => $templateLanguage,
        ]);

        // Send via Meta Cloud Provider directly
        $metaProvider = $this->whatsAppManager->provider();
        if (! ($metaProvider instanceof \App\Services\WhatsApp\MetaCloudProvider)) {
            // Force Meta provider for WABA sends
            $metaProvider = app(\App\Services\WhatsApp\MetaCloudProvider::class);
        }

        $result = $metaProvider->sendTemplate($phone, $templateName, $templateLanguage, $components);

        return [
            'success' => $result['success'],
            'message_id' => $result['message_id'] ?? null,
            'error' => $result['error'] ?? null,
            'phone' => $phone,
            'provider' => 'waba',
            'template_name' => $templateName,
        ];
    }

    /**
     * Normalize template.variable_mappings into the shape buildWabaComponents expects.
     *
     * Template mappings look like ['body' => [1 => 'contact.name', 2 => 'order.number']].
     * The `custom` sentinel is skipped (those carry literal text set elsewhere).
     * Certificate-only keys (student_name, certificate_name, ...) are left as-is so
     * the resolver returns empty string for non-certificate contexts without crashing.
     *
     * @param  array<string, array<int, string>>  $mappings
     * @return array<string, array<int, string>>
     */
    protected function normalizeTemplateMappings(array $mappings): array
    {
        $normalized = [];

        foreach ($mappings as $componentType => $variables) {
            if (! is_array($variables)) {
                continue;
            }

            $type = strtolower((string) $componentType);

            foreach ($variables as $index => $field) {
                if (! is_string($field) || $field === '' || $field === 'custom') {
                    continue;
                }

                // Wrap as a merge tag so resolveContextValue looks the field up in context.
                // Keys not present in context resolve to empty string (safe for cross-context templates).
                $normalized[$type][$index] = '{{'.$field.'}}';
            }
        }

        return $normalized;
    }

    /**
     * Build WABA components array from template variables config.
     *
     * Resolves merge tags like {{contact.name}} to actual values from context,
     * then formats them into the Meta API components structure.
     */
    protected function buildWabaComponents(array $templateVariables, array $context): array
    {
        $components = [];

        foreach ($templateVariables as $componentType => $variables) {
            if (empty($variables)) {
                continue;
            }

            $parameters = [];
            // Sort by key to ensure correct order (1, 2, 3...)
            ksort($variables);

            foreach ($variables as $index => $mergeTag) {
                $resolved = $this->resolveContextValue($mergeTag, $context);
                $parameters[] = [
                    'type' => 'text',
                    'text' => $resolved,
                ];
            }

            if (! empty($parameters)) {
                $components[] = [
                    'type' => $componentType,
                    'parameters' => $parameters,
                ];
            }
        }

        return $components;
    }

    /**
     * Resolve a merge tag or plain value from context.
     *
     * Handles {{contact.name}}, {{order.number}}, etc.
     * Falls back to the raw string if not a merge tag pattern.
     */
    protected function resolveContextValue(string $value, array $context): string
    {
        // Check if it's a merge tag pattern like {{contact.name}} or {{contact.name|default:"there"}}
        if (preg_match('/^\{\{(.+?)\}\}$/', trim($value), $matches)) {
            $key = $matches[1];

            // Handle default values: contact.name|default:"there"
            $default = '';
            if (str_contains($key, '|default:')) {
                [$key, $defaultPart] = explode('|default:', $key, 2);
                $default = trim($defaultPart, '"\'');
            }

            $resolved = $this->getValueFromContext(trim($key), $context);

            return ! empty($resolved) ? (string) $resolved : $default;
        }

        return $value;
    }

    /**
     * Execute send_email action.
     */
    protected function executeSendEmail(FunnelAutomationAction $action, array $context): array
    {
        $config = $action->action_config ?? [];
        $emailSource = $config['email_source'] ?? 'custom';
        $emailField = $config['email_field'] ?? 'contact.email';

        // Get email from context
        $email = $this->getValueFromContext($emailField, $context);

        if (empty($email)) {
            return [
                'success' => false,
                'error' => 'No email address found in context',
            ];
        }

        // Resolve subject and body based on source
        if ($emailSource === 'template' && ! empty($config['template_id'])) {
            $template = \App\Models\FunnelEmailTemplate::find($config['template_id']);

            if (! $template) {
                return [
                    'success' => false,
                    'error' => 'Email template not found (ID: '.$config['template_id'].')',
                    'email' => $email,
                ];
            }

            // Subject: use override if provided, else template subject
            $subject = ! empty($config['subject']) ? $config['subject'] : ($template->subject ?? 'Notification');
            $body = $template->getEffectiveContent();
            $isHtml = $template->isVisualEditor() && $template->html_content;
        } else {
            // Inline/custom content (backward compatible)
            $subject = $config['subject'] ?? 'Notification';
            $body = $config['body'] ?? $config['content'] ?? $config['template'] ?? '';
            $isHtml = false;
        }

        // Process merge tags
        $processedSubject = $this->mergeTagEngine->resolve($subject);
        $processedBody = $this->mergeTagEngine->resolve($body);

        try {
            if ($isHtml) {
                Mail::html($processedBody, function ($message) use ($email, $processedSubject) {
                    $message->to($email)
                        ->subject($processedSubject);
                });
            } else {
                Mail::raw($processedBody, function ($message) use ($email, $processedSubject) {
                    $message->to($email)
                        ->subject($processedSubject);
                });
            }

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
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();
        $itemNames = $items->map(fn ($item) => $item->product_name)->filter()->values();
        $currency = $order->currency ?: 'MYR';

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
                'address' => $order->shipping_address,
            ],
            'order' => [
                'id' => $order->id,
                'number' => $order->order_number,
                'order_number' => $order->order_number,
                'total' => $currency.' '.number_format((float) $order->total_amount, 2),
                'total_raw' => (string) $order->total_amount,
                'subtotal' => number_format((float) $order->subtotal, 2),
                'currency' => $currency,
                'payment_method' => $order->payment_method,
                'status' => $order->status,
                'items_count' => (string) $items->count(),
                'items_list' => $itemNames->implode(', '),
                'first_item_name' => $itemNames->first() ?? '',
                'discount_amount' => number_format((float) $order->discount_amount, 2),
                'coupon_code' => $order->coupon_code ?? '',
                'date' => $order->order_date?->format('Y-m-d') ?? $order->created_at?->format('Y-m-d') ?? '',
                'tracking_number' => $order->tracking_id ?? '',
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
