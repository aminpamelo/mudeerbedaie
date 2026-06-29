<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendCampaignMessageJob;
use App\Models\ProductOrder;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\DB;

class WhatsAppBlastService
{
    /**
     * Order fields offered in the compose UI to fill template variables.
     *
     * @var array<string, string>
     */
    public const ORDER_FIELDS = [
        'customer_name' => 'Customer name',
        'order_number' => 'Order number',
        'order_total' => 'Order total (with currency)',
        'order_status' => 'Order status',
        'payment_status' => 'Payment status',
        'store_name' => 'Store name',
    ];

    /**
     * Build a campaign from selected orders and queue the sends.
     *
     * Recipients are de-duplicated by normalised phone; orders without a
     * contactable phone (or duplicates) are counted as skipped.
     *
     * @param  array<int>  $orderIds
     * @param  array<string, array<int, mixed>>  $variableMapping
     */
    public function createFromOrders(array $orderIds, WhatsAppTemplate $template, array $variableMapping, ?int $createdBy = null): WhatsAppCampaign
    {
        $orders = ProductOrder::whereIn('id', $orderIds)->get();

        $recipients = [];
        $skipped = 0;

        foreach ($orders as $order) {
            $phone = $this->normalizePhone($order->getCustomerPhone());

            if ($phone === null || isset($recipients[$phone])) {
                $skipped++;

                continue;
            }

            $recipients[$phone] = [
                'order_id' => $order->id,
                'name' => $order->getCustomerName(),
            ];
        }

        $count = count($recipients);

        return DB::transaction(function () use ($template, $variableMapping, $createdBy, $recipients, $skipped, $count) {
            $campaign = WhatsAppCampaign::create([
                'name' => $this->defaultName($template, $count),
                'source' => 'orders_bulk',
                'whatsapp_template_id' => $template->id,
                'template_name' => $template->name,
                'template_language' => $template->language,
                'variable_mapping' => $variableMapping,
                'status' => $count > 0 ? 'queued' : 'completed',
                'total_recipients' => $count,
                'skipped_count' => $skipped,
                'estimated_cost_usd' => $this->estimateCost($template, $count),
                'created_by' => $createdBy,
                'started_at' => now(),
                'completed_at' => $count > 0 ? null : now(),
            ]);

            foreach ($recipients as $phone => $info) {
                $recipient = $campaign->recipients()->create([
                    'product_order_id' => $info['order_id'],
                    'customer_name' => $info['name'],
                    'phone' => $phone,
                    'status' => 'pending',
                ]);

                SendCampaignMessageJob::dispatch($recipient->id);
            }

            return $campaign;
        });
    }

    /**
     * Preview how many of the selected orders can be reached.
     *
     * @param  array<int>  $orderIds
     * @return array{recipients: int, skipped: int, sample: ?ProductOrder}
     */
    public function previewRecipients(array $orderIds): array
    {
        $orders = ProductOrder::whereIn('id', $orderIds)->get();

        $seen = [];
        $skipped = 0;
        $sample = null;

        foreach ($orders as $order) {
            $phone = $this->normalizePhone($order->getCustomerPhone());

            if ($phone === null || isset($seen[$phone])) {
                $skipped++;

                continue;
            }

            $seen[$phone] = true;
            $sample ??= $order;
        }

        return [
            'recipients' => count($seen),
            'skipped' => $skipped,
            'sample' => $sample,
        ];
    }

    /**
     * Build Meta template components for a recipient from the variable mapping.
     *
     * @param  array<string, array<int, mixed>>  $variableMapping
     * @return array<int, array{type: string, parameters: array<int, array{type: string, text: string}>}>
     */
    public function buildComponents(WhatsAppCampaignRecipient $recipient, array $variableMapping): array
    {
        $order = $recipient->order;
        $components = [];

        foreach ($variableMapping as $componentType => $vars) {
            if (! is_array($vars) || $vars === []) {
                continue;
            }

            ksort($vars);
            $parameters = [];

            foreach ($vars as $def) {
                $parameters[] = [
                    'type' => 'text',
                    'text' => $this->resolveVariable($def, $order, $recipient),
                ];
            }

            if ($parameters !== []) {
                $components[] = [
                    'type' => $componentType,
                    'parameters' => $parameters,
                ];
            }
        }

        return $components;
    }

    /**
     * Render the template body with variables resolved for a sample order
     * (used for the compose preview).
     *
     * @param  array<string, array<int, mixed>>  $variableMapping
     */
    public function renderBodyPreview(WhatsAppTemplate $template, array $variableMapping, ?ProductOrder $order): string
    {
        $body = $this->bodyText($template);
        $bodyVars = $variableMapping['body'] ?? [];

        if (is_array($bodyVars)) {
            foreach ($bodyVars as $index => $def) {
                $value = $this->resolveVariable($def, $order, null, $order?->getCustomerName());
                $body = str_replace('{{'.$index.'}}', $value !== '' ? $value : '{{'.$index.'}}', $body);
            }
        }

        return $body;
    }

    /**
     * Extract the BODY text of a template (with {{n}} placeholders).
     */
    public function bodyText(WhatsAppTemplate $template): string
    {
        foreach ((array) $template->components as $component) {
            if (strtoupper($component['type'] ?? '') === 'BODY') {
                return (string) ($component['text'] ?? '');
            }
        }

        return '';
    }

    /**
     * Count how many {{n}} placeholders a given component type declares.
     */
    public function variableCount(WhatsAppTemplate $template, string $componentType = 'BODY'): int
    {
        foreach ((array) $template->components as $component) {
            if (strtoupper($component['type'] ?? '') === strtoupper($componentType)) {
                preg_match_all('/\{\{(\d+)\}\}/', (string) ($component['text'] ?? ''), $matches);

                return $matches[1] === [] ? 0 : (int) max($matches[1]);
            }
        }

        return 0;
    }

    public function estimateCost(WhatsAppTemplate $template, int $recipients): float
    {
        $country = config('whatsapp-pricing.default_country', 'MY');
        $category = $template->category ?: 'marketing';
        $rate = (float) config("whatsapp-pricing.rates.{$country}.{$category}", config("whatsapp-pricing.rates.{$country}.marketing", 0.086));

        return round($recipients * $rate, 4);
    }

    /**
     * Normalise a raw phone to a digits-only Malaysian mobile in international
     * form (60 1X XXXXXXX), or null if it isn't a plausible Malaysian mobile.
     *
     * Intentionally strict: blasts cost money and reach real people, so anything
     * we can't confidently map to a Malaysian mobile is skipped rather than risk
     * messaging the wrong number.
     */
    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || str_contains($phone, '*')) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            // International dialing prefix — drop it; the rest must carry a country code.
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            // Local Malaysian number with a trunk 0 -> 60 + national number.
            $digits = '60'.ltrim($digits, '0');
        } elseif (str_starts_with($digits, '1') && strlen($digits) <= 10) {
            // Malaysian mobile typed without the leading 0.
            $digits = '60'.$digits;
        }

        // Only accept Malaysian mobiles (60 + 1X + 7-9 digits). Landlines and
        // foreign numbers (which can't receive these template blasts reliably)
        // are skipped.
        return preg_match('/^601\d{7,9}$/', $digits) === 1 ? $digits : null;
    }

    /**
     * @param  mixed  $def
     */
    protected function resolveVariable($def, ?ProductOrder $order, ?WhatsAppCampaignRecipient $recipient, ?string $fallbackName = null): string
    {
        if (! is_array($def)) {
            return (string) $def;
        }

        if (($def['source'] ?? 'static') === 'static') {
            return (string) ($def['value'] ?? '');
        }

        return $this->orderFieldValue((string) ($def['field'] ?? ''), $order, $recipient?->customer_name ?? $fallbackName);
    }

    protected function orderFieldValue(string $field, ?ProductOrder $order, ?string $fallbackName): string
    {
        return match ($field) {
            'customer_name' => $fallbackName ?: ($order?->getCustomerName() ?? ''),
            'order_number' => (string) ($order?->order_number ?? ''),
            'order_total' => $order ? trim(((string) $order->currency).' '.number_format((float) $order->total_amount, 2)) : '',
            'order_status' => ucwords((string) ($order?->status ?? '')),
            'payment_status' => ucwords((string) ($order?->payment_status ?? '')),
            'store_name' => (string) config('store.name', config('app.name', '')),
            default => '',
        };
    }

    protected function defaultName(WhatsAppTemplate $template, int $count): string
    {
        return $template->name.' — '.now()->format('d M Y, H:i').' ('.$count.' recipient'.($count === 1 ? '' : 's').')';
    }
}
