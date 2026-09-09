<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesToMarketer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateFunnelSettingsTool extends Tool
{
    use ScopesToMarketer;

    protected string $name = 'update_funnel_settings';

    protected string $description = <<<'MARKDOWN'
        Update a funnel's general settings (the Settings tab): its name, URL
        slug, description, and SEO meta title/description. Provide funnel_uuid
        and the fields to change. Changing the slug changes the public URL.
        MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'funnel_uuid' => 'required|string',
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'meta_title' => 'sometimes|nullable|string|max:255',
            'meta_description' => 'sometimes|nullable|string|max:500',
        ]);

        $funnel = $this->findScopedFunnel($request->user(), $validated['funnel_uuid']);
        if (! $funnel) {
            return Response::error('That funnel was not found or you do not have access to it.');
        }

        if (array_key_exists('slug', $validated)) {
            $slug = Str::slug($validated['slug']);
            if ($slug === '') {
                return Response::error('The slug must contain at least one letter or number.');
            }
            if (\App\Models\Funnel::where('slug', $slug)->where('id', '!=', $funnel->id)->exists()) {
                return Response::error("The slug \"{$slug}\" is already taken by another funnel.");
            }
            $funnel->slug = $slug;
        }

        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $validated)) {
                $funnel->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('meta_title', $validated) || array_key_exists('meta_description', $validated)) {
            $settings = $funnel->settings ?? [];
            if (array_key_exists('meta_title', $validated)) {
                $settings['meta_title'] = $validated['meta_title'];
            }
            if (array_key_exists('meta_description', $validated)) {
                $settings['meta_description'] = $validated['meta_description'];
            }
            $funnel->settings = $settings;
        }

        $funnel->save();

        return Response::json([
            'funnel_uuid' => $funnel->uuid,
            'name' => $funnel->name,
            'slug' => $funnel->slug,
            'public_url' => $funnel->status === 'published' ? route('funnel.show', $funnel->slug) : null,
            'message' => 'Funnel settings updated.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_uuid' => $schema->string()->description('The uuid of the funnel.')->required(),
            'name' => $schema->string()->description('New funnel name.'),
            'slug' => $schema->string()->description('New URL slug (changes the public /f/{slug} URL).'),
            'description' => $schema->string()->description('New description.'),
            'meta_title' => $schema->string()->description('SEO meta title.'),
            'meta_description' => $schema->string()->description('SEO meta description.'),
        ];
    }
}
