<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use App\Models\FunnelStepContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class AddFunnelStepTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'add_funnel_step';

    protected string $description = <<<'MARKDOWN'
        Add a new step (page) to a funnel: a landing, sales, checkout, upsell,
        downsell, thankyou, or optin page. Returns the new step's id. The step
        starts empty — use update_landing_page or the product tools to fill it.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'name' => 'required|string|max:255',
            'type' => 'required|in:landing,sales,checkout,upsell,downsell,thankyou,optin',
            'slug' => 'sometimes|nullable|string|max:255',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        $slug = Str::slug($validated['slug'] ?? $validated['name']) ?: 'step';
        $base = $slug;
        $i = 1;
        while ($funnel->steps()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        $step = $funnel->steps()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'type' => $validated['type'],
            'sort_order' => (int) $funnel->steps()->max('sort_order') + 1,
            'is_active' => true,
        ]);

        FunnelStepContent::create([
            'funnel_step_id' => $step->id,
            'content' => ['content' => [], 'root' => (object) []],
            'version' => 1,
            'is_published' => false,
        ]);

        return Response::json([
            'step_id' => $step->id,
            'name' => $step->name,
            'type' => $step->type,
            'slug' => $step->slug,
            'message' => 'Step added. Fill it with update_landing_page (HTML) or add products.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'name' => $schema->string()->description('Name of the step.')->required(),
            'type' => $schema->string()
                ->enum(['landing', 'sales', 'checkout', 'upsell', 'downsell', 'thankyou', 'optin'])
                ->description('The step type.')->required(),
            'slug' => $schema->string()->description('Optional URL slug (auto-generated from name if omitted).'),
        ];
    }
}
