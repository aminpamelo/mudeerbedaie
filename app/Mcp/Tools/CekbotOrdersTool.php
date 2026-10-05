<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Services\Cekbot\CekbotReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CekbotOrdersTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'cekbot_orders';

    protected string $description = <<<'MARKDOWN'
        List orders created by the Cekbot WhatsApp chatbot: order number, date,
        customer, phone, flow, items, total (RM), payment method, paid or not,
        status. Filter by period, flow id (from cekbot_flows) and payment.
        Newest first, up to 100 rows.
        MARKDOWN;

    public function __construct(private CekbotReport $report) {}

    public function handle(Request $request): Response
    {
        if (! $this->canSeeCekbot($request->user())) {
            return Response::error('You do not have access to Cekbot data.');
        }

        $validated = $request->validate([
            'period' => 'sometimes|in:'.implode(',', CekbotReport::PERIODS),
            'start_date' => 'required_if:period,custom|nullable|date',
            'end_date' => 'required_if:period,custom|nullable|date|after_or_equal:start_date',
            'flow_id' => 'sometimes|nullable|integer',
            'payment' => 'sometimes|in:all,paid,pending',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $orders = $this->report->orders($validated + ['period' => '30d']);

        return Response::json(['currency' => 'RM', 'count' => count($orders), 'orders' => $orders]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->enum(CekbotReport::PERIODS)->description('Default 30d. Use custom with start_date/end_date.'),
            'start_date' => $schema->string()->description('YYYY-MM-DD, required when period=custom.'),
            'end_date' => $schema->string()->description('YYYY-MM-DD, required when period=custom.'),
            'flow_id' => $schema->integer()->description('Only orders from this flow (id from cekbot_flows).'),
            'payment' => $schema->string()->enum(['all', 'paid', 'pending'])->description('Default all.'),
            'limit' => $schema->integer()->description('Max rows (1-100, default 50).'),
        ];
    }
}
