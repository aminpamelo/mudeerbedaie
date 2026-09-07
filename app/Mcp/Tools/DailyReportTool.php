<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Services\Funnel\FunnelStudioReportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DailyReportTool extends Tool
{
    use ScopesToMarketer;

    public function __construct(
        protected FunnelStudioReportService $reports
    ) {}

    protected string $name = 'daily_report';

    protected string $description = <<<'MARKDOWN'
        The marketer's day-by-day ads-vs-sales profit report over a 7, 30, or
        90 day window (default 30): for each day the Facebook ad spend, spend
        including 8% SST, funnel sales and orders, ROAS, and net (sales minus
        spend+SST). Also returns a per-calendar-month rollup and window totals.
        These figures match the Daily Reporting page in Funnel Studio exactly.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|in:7,30,90',
        ]);

        $user = $request->user();

        $report = $this->reports->dailyReport(
            $this->funnelIdsFor($user),
            $this->adAccountIdsFor($user),
            (int) ($validated['days'] ?? 30),
        );

        $report['can_see_spend'] = $this->canSeeSpend($user);

        return Response::json($report);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->enum([7, 30, 90])
                ->description('The reporting window in days: 7, 30, or 90. Defaults to 30.'),
        ];
    }
}
