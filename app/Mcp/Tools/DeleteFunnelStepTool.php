<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteFunnelStepTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'delete_funnel_step';

    protected string $description = <<<'MARKDOWN'
        Delete a step from a funnel. Provide the funnel_uuid and step_id. This
        removes the step and its content; remaining steps are re-ordered. A
        funnel must keep at least one step to stay viewable.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'step_id' => 'required|integer',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $step = $funnel->steps()->whereKey($validated['step_id'])->first();
        if (! $step) {
            return Response::error('That step was not found in this funnel.');
        }

        if ($funnel->steps()->count() <= 1) {
            return Response::error('Cannot delete the funnel\'s only step — a funnel needs at least one step.');
        }

        DB::transaction(function () use ($funnel, $step) {
            $step->contents()->delete();
            $order = $step->sort_order;
            $step->update(['slug' => $step->slug.'__deleted__'.$step->id]);
            $step->delete();
            $funnel->steps()->where('sort_order', '>', $order)->decrement('sort_order');
        });

        return Response::json(['message' => 'Step deleted.']);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'step_id' => $schema->integer()->description('The id of the step to delete.')->required(),
        ];
    }
}
