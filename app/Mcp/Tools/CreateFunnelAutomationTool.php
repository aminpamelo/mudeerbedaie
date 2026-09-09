<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FunnelAutomation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateFunnelAutomationTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'create_funnel_automation';

    protected string $description = <<<'MARKDOWN'
        Create an automation on a funnel: when a trigger fires, run one action.
        Triggers: purchase, cart_abandonment, optin, upsell_accepted,
        upsell_declined, page_view. Actions: send_email (needs subject +
        message), send_whatsapp (needs message), webhook (needs webhook_url).
        Optionally delay the action by delay_minutes. Created inactive-safe with
        is_active default true; verify it in the funnel's Automations tab.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'name' => 'required|string|max:255',
            'trigger' => 'required|in:purchase,cart_abandonment,optin,upsell_accepted,upsell_declined,page_view',
            'action' => 'required|in:send_email,send_whatsapp,webhook',
            'subject' => 'sometimes|nullable|string|max:255',
            'message' => 'sometimes|nullable|string|max:5000',
            'webhook_url' => 'sometimes|nullable|url|max:2048',
            'delay_minutes' => 'sometimes|integer|min:0|max:43200',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $config = match ($validated['action']) {
            'send_email' => ['subject' => $validated['subject'] ?? '', 'content' => $validated['message'] ?? '', 'email_field' => 'customer_email'],
            'send_whatsapp' => ['provider' => 'onsend', 'message' => $validated['message'] ?? '', 'phone_field' => 'customer_phone'],
            'webhook' => ['url' => $validated['webhook_url'] ?? '', 'method' => 'POST'],
        };

        if ($validated['action'] === 'send_email' && (empty($config['subject']) || empty($config['content']))) {
            return Response::error('send_email needs both a subject and a message.');
        }
        if ($validated['action'] === 'send_whatsapp' && empty($config['message'])) {
            return Response::error('send_whatsapp needs a message.');
        }
        if ($validated['action'] === 'webhook' && empty($config['url'])) {
            return Response::error('webhook needs a webhook_url.');
        }

        $automation = DB::transaction(function () use ($funnel, $validated, $config) {
            $automation = FunnelAutomation::create([
                'uuid' => (string) Str::uuid(),
                'name' => $validated['name'],
                'funnel_id' => $funnel->id,
                'trigger_type' => $validated['trigger'],
                'trigger_config' => [],
                'is_active' => true,
                'priority' => 0,
            ]);

            $automation->actions()->create([
                'action_type' => $validated['action'],
                'action_config' => $config,
                'delay_minutes' => (int) ($validated['delay_minutes'] ?? 0),
                'sort_order' => 0,
            ]);

            return $automation;
        });

        return Response::json([
            'automation_id' => $automation->id,
            'name' => $automation->name,
            'trigger_type' => $automation->trigger_type,
            'action' => $validated['action'],
            'is_active' => true,
            'message' => 'Automation created. Confirm the message and phone/email mapping in the funnel\'s Automations tab.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'name' => $schema->string()->description('A name for the automation.')->required(),
            'trigger' => $schema->string()
                ->enum(['purchase', 'cart_abandonment', 'optin', 'upsell_accepted', 'upsell_declined', 'page_view'])
                ->description('What fires the automation.')->required(),
            'action' => $schema->string()->enum(['send_email', 'send_whatsapp', 'webhook'])
                ->description('What the automation does.')->required(),
            'subject' => $schema->string()->description('Email subject (for send_email).'),
            'message' => $schema->string()->description('Email body or WhatsApp message text.'),
            'webhook_url' => $schema->string()->description('URL to POST to (for webhook).'),
            'delay_minutes' => $schema->integer()->description('Delay before running the action, in minutes.')->min(0),
        ];
    }
}
