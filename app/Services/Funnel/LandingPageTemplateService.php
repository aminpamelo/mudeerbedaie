<?php

namespace App\Services\Funnel;

use App\Models\FunnelStep;
use App\Models\FunnelStepContent;
use App\Models\LandingPageTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Landing Library: snapshot a funnel step's page into a template, apply a
 * template to a step (copy-on-apply, as an unpublished draft so a live page
 * never changes until the owner publishes), and backfill the library from
 * existing landing/sales pages.
 */
class LandingPageTemplateService
{
    /** Step types whose page counts as a landing page for the import. */
    public const LANDING_STEP_TYPES = ['landing', 'sales', 'optin'];

    /**
     * Puck document wrapping raw HTML in a single Custom HTML block.
     *
     * @return array{content: array<int, array<string, mixed>>, root: array<string, mixed>}
     */
    public function htmlContent(string $html): array
    {
        return [
            'content' => [[
                'type' => 'CustomHtml',
                'props' => [
                    'id' => 'CustomHtml-'.Str::uuid(),
                    'html' => $html,
                    'backgroundColor' => '#ffffff',
                    'maxWidth' => '100%',
                    'align' => 'center',
                    'padding' => '0px',
                ],
            ]],
            'root' => ['props' => []],
        ];
    }

    /**
     * Snapshot the step's current page (draft, else published) as a template
     * and link the step to it.
     *
     * @param  array{name: string, description?: string|null, category?: string|null}  $attributes
     */
    public function createFromStep(FunnelStep $step, array $attributes, ?User $user): ?LandingPageTemplate
    {
        $content = $this->currentContent($step);

        if (! $content || empty($content->content['content'] ?? [])) {
            return null;
        }

        return DB::transaction(function () use ($step, $content, $attributes, $user) {
            $template = LandingPageTemplate::create([
                'created_by' => $user?->id,
                'source_funnel_step_id' => $step->id,
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'category' => $attributes['category'] ?? null,
                'content' => $content->content,
                'custom_css' => $content->custom_css,
                'custom_js' => $content->custom_js,
                'usage_count' => 1,
            ]);

            $step->update(['landing_page_template_id' => $template->id]);

            return $template;
        });
    }

    /**
     * Copy the template into the step as a new unpublished draft and record
     * which template the step now uses.
     */
    public function apply(LandingPageTemplate $template, FunnelStep $step): FunnelStepContent
    {
        return DB::transaction(function () use ($template, $step) {
            $latest = $step->draftContent;

            $attributes = [
                'content' => $template->content,
                'custom_css' => $template->custom_css,
                'custom_js' => $template->custom_js,
            ];

            // Never overwrite a live (published) row — add a new draft on top.
            if ($latest && ! $latest->is_published) {
                $latest->update(array_merge($attributes, ['version' => $latest->version + 1]));
                $draft = $latest;
            } else {
                $draft = $step->contents()->create(array_merge($attributes, [
                    'meta_title' => $latest?->meta_title,
                    'meta_description' => $latest?->meta_description,
                    'og_image' => $latest?->og_image,
                    'version' => ($latest?->version ?? 0) + 1,
                    'is_published' => false,
                ]));
            }

            if ((int) $step->landing_page_template_id !== $template->id) {
                $step->update(['landing_page_template_id' => $template->id]);
                $template->increment('usage_count');
            }

            return $draft;
        });
    }

    /**
     * Turn every unlinked landing/sales page that has content into a
     * template (one per step) and link the step to it.
     */
    public function importExisting(?User $user): int
    {
        $imported = 0;

        FunnelStep::query()
            ->whereIn('type', self::LANDING_STEP_TYPES)
            ->whereNull('landing_page_template_id')
            ->whereHas('funnel')
            ->with('funnel:id,name')
            ->orderBy('id')
            ->each(function (FunnelStep $step) use ($user, &$imported) {
                $template = $this->createFromStep($step, [
                    'name' => $step->funnel->name.' — '.$step->name,
                    'category' => $step->type === 'sales' ? 'sales' : null,
                    'description' => 'Imported from funnel "'.$step->funnel->name.'".',
                ], $user);

                if ($template) {
                    $imported++;
                }
            });

        return $imported;
    }

    public function currentContent(FunnelStep $step): ?FunnelStepContent
    {
        return $step->draftContent ?? $step->publishedContent;
    }
}
