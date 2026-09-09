<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FunnelOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class FunnelOrdersTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'funnel_orders';

    protected string $description = <<<'MARKDOWN'
        Read a single funnel's recent orders: order number, customer, revenue,
        type (main/upsell/downsell/bump), payment status, and date. Optionally
        filter by type or a date window (today, 7d, 30d). Returns up to 50.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'type' => 'sometimes|in:main,upsell,downsell,bump',
            'date' => 'sometimes|in:today,7d,30d',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $orders = FunnelOrder::query()
            ->where('funnel_id', $funnel->id)
            ->when($validated['type'] ?? null, fn ($q, $t) => $q->where('order_type', $t))
            ->when(($validated['date'] ?? null) === 'today', fn ($q) => $q->whereDate('created_at', today()))
            ->when(($validated['date'] ?? null) === '7d', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
            ->when(($validated['date'] ?? null) === '30d', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))
            ->with(['productOrder:id,order_number,customer_name,guest_email,payment_status', 'step:id,name'])
            ->latest()->limit(50)->get()
            ->map(fn (FunnelOrder $o) => [
                'order_number' => $o->productOrder?->order_number ?? 'N/A',
                'customer' => $o->productOrder?->customer_name ?: $o->productOrder?->guest_email ?: 'Unknown',
                'revenue' => (float) $o->funnel_revenue,
                'type' => $o->order_type,
                'payment_status' => $o->productOrder?->payment_status ?? 'unknown',
                'step' => $o->step?->name,
                'created_at' => $o->created_at->toIso8601String(),
            ]);

        return Response::json([
            'funnel_uuid' => $funnel->uuid,
            'count' => $orders->count(),
            'orders' => $orders,
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'type' => $schema->string()->enum(['main', 'upsell', 'downsell', 'bump'])->description('Filter by order type.'),
            'date' => $schema->string()->enum(['today', '7d', '30d'])->description('Filter by date window.'),
        ];
    }
}
