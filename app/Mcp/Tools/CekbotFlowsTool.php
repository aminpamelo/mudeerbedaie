<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Services\Cekbot\CekbotReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CekbotFlowsTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'cekbot_flows';

    protected string $description = <<<'MARKDOWN'
        List Cekbot flows (guided WhatsApp sales funnels): name, WhatsApp
        number, active or not, mode (ai / menu / info), trigger keywords,
        number of trigger ads and packages, plus funnel numbers for the period
        (started, active, abandoned, orders, conversion %, revenue RM).
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
            'active_only' => 'sometimes|boolean',
        ]);

        $flows = collect($this->report->flowStats($validated + ['period' => '30d']))
            ->when($validated['active_only'] ?? false, fn ($c) => $c->where('is_active', true))
            ->values();

        return Response::json(['currency' => 'RM', 'count' => $flows->count(), 'flows' => $flows]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->enum(CekbotReport::PERIODS)->description('Window for the funnel numbers. Default 30d.'),
            'start_date' => $schema->string()->description('YYYY-MM-DD, required when period=custom.'),
            'end_date' => $schema->string()->description('YYYY-MM-DD, required when period=custom.'),
            'active_only' => $schema->boolean()->description('Only active flows.'),
        ];
    }
}
