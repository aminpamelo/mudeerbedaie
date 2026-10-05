<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Services\Cekbot\CekbotReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CekbotOverviewTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'cekbot_overview';

    protected string $description = <<<'MARKDOWN'
        Cekbot (WhatsApp chatbot) summary for a period: new conversations,
        inbound messages, leads by category, orders the bot created (count,
        revenue RM, paid, cancelled), and a per-flow funnel (started, active,
        abandoned, orders, conversion %, revenue).
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
        ]);

        return Response::json(['currency' => 'RM'] + $this->report->overview($validated + ['period' => '30d']));
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
        ];
    }
}
