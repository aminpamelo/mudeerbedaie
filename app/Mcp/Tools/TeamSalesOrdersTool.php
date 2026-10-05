<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\ProductOrder;
use App\Services\Reports\TeamSalesReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class TeamSalesOrdersTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'team_sales_orders';

    protected string $description = <<<'MARKDOWN'
        List Team Sales orders (like the admin page's order list / CSV): order
        number, date, customer, salesperson, items, total (RM), payment method
        and status. Filter by period, salesperson (name or id) and status.
        Newest first, up to 100 rows.
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
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $salespersonId = 'all';
        if (filled($validated['salesperson'] ?? null)) {
            $needle = mb_strtolower(trim((string) $validated['salesperson']));
            $match = $needle === 'unassigned' ? ['id' => 'unassigned'] : $this->report->salespeople()
                ->first(fn (array $p) => (string) $p['id'] === $needle || str_contains(mb_strtolower($p['name']), $needle));

            if ($match === null) {
                return Response::error('No salesperson matches "'.$validated['salesperson'].'".');
            }
            $salespersonId = $match['id'];
        }

        $orders = $this->report->query([
            'period' => $validated['period'] ?? 'this_month',
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'salesperson_id' => $salespersonId,
            'status' => $validated['status'] ?? 'all',
        ])
            ->with('items:id,order_id,product_name,quantity_ordered')
            ->latest('order_date')
            ->limit($validated['limit'] ?? 50)
            ->get()
            ->map(fn (ProductOrder $o) => [
                'order_number' => $o->order_number,
                'date' => $o->order_date?->toDateTimeString(),
                'customer' => $o->customer_name,
                'salesperson' => $o->metadata['salesperson_name'] ?? 'Unassigned',
                'items' => $o->items->map(fn ($i) => trim($i->product_name.' x'.$i->quantity_ordered))->implode(', '),
                'total' => (float) $o->total_amount,
                'payment_method' => $o->payment_method,
                'status' => $o->status === 'cancelled' ? 'cancelled' : ($o->paid_time ? 'paid' : 'pending'),
            ]);

        return Response::json(['currency' => 'RM', 'count' => $orders->count(), 'orders' => $orders]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->enum(TeamSalesReport::PERIODS)->description('Date window on order date. Default this_month.'),
            'start_date' => $schema->string()->description('YYYY-MM-DD, required when period=custom.'),
            'end_date' => $schema->string()->description('YYYY-MM-DD, required when period=custom.'),
            'salesperson' => $schema->string()->description('Salesperson name (partial ok), user id, or "unassigned".'),
            'status' => $schema->string()->enum(TeamSalesReport::STATUSES)->description('Payment status filter. Default all.'),
            'limit' => $schema->integer()->description('Max rows (1-100, default 50).'),
        ];
    }
}
