<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListFunnelsTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'list_funnels';

    protected string $description = <<<'MARKDOWN'
        List the sales funnels the current marketer can access, with each
        funnel's uuid, name, status, public URL, and its sales in the last 30
        days. Use the returned uuid to reference a funnel in other tools.
        Optionally filter by status or search by name.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:draft,published,archived',
            'search' => 'sometimes|string|max:100',
        ]);

        $user = $request->user();
        $funnelIds = $this->funnelIdsFor($user);

        $revenueByFunnel = FunnelOrder::query()
            ->whereIn('funnel_id', $funnelIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('funnel_id, SUM(funnel_revenue) as revenue, COUNT(*) as orders')
            ->groupBy('funnel_id')
            ->get()
            ->keyBy('funnel_id');

        $funnels = Funnel::query()
            ->whereIn('id', $funnelIds)
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($validated['search'] ?? null, fn (Builder $q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'uuid', 'name', 'slug', 'status', 'published_at'])
            ->map(fn (Funnel $funnel) => [
                'uuid' => $funnel->uuid,
                'name' => $funnel->name,
                'status' => $funnel->status,
                'public_url' => $funnel->status === 'published' && $funnel->slug
                    ? route('funnel.show', $funnel->slug)
                    : null,
                'published_at' => $funnel->published_at?->toIso8601String(),
                'sales_30d' => round((float) ($revenueByFunnel[$funnel->id]->revenue ?? 0), 2),
                'orders_30d' => (int) ($revenueByFunnel[$funnel->id]->orders ?? 0),
            ]);

        return Response::json([
            'count' => $funnels->count(),
            'funnels' => $funnels,
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['draft', 'published', 'archived'])
                ->description('Only return funnels with this status.'),
            'search' => $schema->string()
                ->description('Filter funnels whose name contains this text.'),
        ];
    }
}
