<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FunnelAnalytics;
use App\Models\FunnelStep;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class FunnelAnalyticsTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'funnel_analytics';

    protected string $description = <<<'MARKDOWN'
        Read a single funnel's traffic analytics over a 7, 30, or 90 day window
        (default 30): total visitors, pageviews, conversions, revenue, and
        conversion rate — overall and per step.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'days' => 'sometimes|integer|in:7,30,90',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $days = (int) ($validated['days'] ?? 30);
        $from = now()->subDays($days - 1)->toDateString();

        $totals = FunnelAnalytics::query()
            ->where('funnel_id', $funnel->id)
            ->where('date', '>=', $from)
            ->selectRaw('SUM(unique_visitors) v, SUM(pageviews) pv, SUM(conversions) c, SUM(revenue) r')
            ->first();

        $visitors = (int) ($totals->v ?? 0);
        $conversions = (int) ($totals->c ?? 0);

        $byStep = FunnelStep::query()
            ->where('funnel_id', $funnel->id)
            ->leftJoin('funnel_analytics', function ($join) use ($from) {
                $join->on('funnel_analytics.funnel_step_id', '=', 'funnel_steps.id')
                    ->where('funnel_analytics.date', '>=', $from);
            })
            ->groupBy('funnel_steps.id', 'funnel_steps.name', 'funnel_steps.type', 'funnel_steps.sort_order')
            ->orderBy('funnel_steps.sort_order')
            ->selectRaw('funnel_steps.name, funnel_steps.type, SUM(funnel_analytics.unique_visitors) v, SUM(funnel_analytics.conversions) c, SUM(funnel_analytics.revenue) r')
            ->get()
            ->map(fn ($s) => [
                'step' => $s->name,
                'type' => $s->type,
                'visitors' => (int) ($s->v ?? 0),
                'conversions' => (int) ($s->c ?? 0),
                'revenue' => round((float) ($s->r ?? 0), 2),
            ]);

        return Response::json([
            'funnel_uuid' => $funnel->uuid,
            'days' => $days,
            'summary' => [
                'visitors' => $visitors,
                'pageviews' => (int) ($totals->pv ?? 0),
                'conversions' => $conversions,
                'revenue' => round((float) ($totals->r ?? 0), 2),
                'conversion_rate' => $visitors > 0 ? round(($conversions / $visitors) * 100, 2) : 0,
            ],
            'steps' => $byStep,
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'days' => $schema->integer()->enum([7, 30, 90])->description('Window in days: 7, 30, or 90. Defaults to 30.'),
        ];
    }
}
