<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Services\Reports\TeamSalesReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class TeamSalesReportTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'team_sales_report';

    protected string $description = <<<'MARKDOWN'
        Sales Department → Team Sales report (same numbers as the admin page):
        total revenue (RM, excludes cancelled), orders, average order value,
        items, paid/pending/cancelled split, and a ranking of every
        salesperson (revenue, orders, AOV, last sale). Filter by period,
        salesperson (name or id, or "unassigned") and payment status. Set
        monthly_year to also get a month-by-month breakdown per salesperson.
        Call with no salesperson to see who the salespeople are.
        MARKDOWN;

    public function __construct(private TeamSalesReport $report) {}

    public function handle(Request $request): Response
    {
        if (! $this->canSeeTeamSales($request->user())) {
            return Response::error('You do not have access to Team Sales data.');
        }

        $validated = $request->validate([
            'period' => 'sometimes|in:'.implode(',', TeamSalesReport::PERIODS),
            'start_date' => 'required_if:period,custom|nullable|date',
            'end_date' => 'required_if:period,custom|nullable|date|after_or_equal:start_date',
            'salesperson' => 'sometimes|nullable|string|max:100',
            'status' => 'sometimes|in:'.implode(',', TeamSalesReport::STATUSES),
            'monthly_year' => 'sometimes|nullable|integer|min:2020|max:2100',
        ]);

        $salespeople = $this->report->salespeople();
        $salespersonId = 'all';

        if (filled($validated['salesperson'] ?? null)) {
            $needle = trim((string) $validated['salesperson']);
            $match = mb_strtolower($needle) === 'unassigned'
                ? 'unassigned'
                : $salespeople->first(fn (array $p) => (string) $p['id'] === $needle || str_contains(mb_strtolower($p['name']), mb_strtolower($needle)));

            if ($match === null) {
                return Response::error("No salesperson matches \"{$needle}\". Salespeople: ".$salespeople->pluck('name')->implode(', '));
            }

            $salespersonId = $match === 'unassigned' ? 'unassigned' : $match['id'];
        }

        $filters = [
            'period' => $validated['period'] ?? 'this_month',
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'salesperson_id' => $salespersonId,
            'status' => $validated['status'] ?? 'all',
        ];
        [$from, $to] = $this->report->range($filters);

        $payload = [
            'currency' => 'RM',
            'period' => ['name' => $filters['period'], 'from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'salesperson' => $salespersonId === 'all' ? 'all' : ($salespersonId === 'unassigned' ? 'unassigned' : $salespeople->firstWhere('id', $salespersonId)['name']),
            'status' => $filters['status'],
        ] + $this->report->summary($filters);

        if (filled($validated['monthly_year'] ?? null)) {
            $payload['monthly'] = $this->report->monthly($filters, (int) $validated['monthly_year']);
        }

        $payload['all_salespeople'] = $salespeople->values();

        return Response::json($payload);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->enum(TeamSalesReport::PERIODS)->description('Date window on order date. Default this_month. Use custom with start_date/end_date.'),
            'start_date' => $schema->string()->description('YYYY-MM-DD, required when period=custom.'),
            'end_date' => $schema->string()->description('YYYY-MM-DD, required when period=custom.'),
            'salesperson' => $schema->string()->description('Salesperson name (partial ok), user id, or "unassigned". Omit for everyone.'),
            'status' => $schema->string()->enum(TeamSalesReport::STATUSES)->description('Payment status filter. Default all.'),
            'monthly_year' => $schema->integer()->description('Also return a month-by-month breakdown for this year, e.g. 2026.'),
        ];
    }
}
