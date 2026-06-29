<?php

namespace App\Jobs;

use App\Models\AiSalesPage;
use App\Services\Ai\SalesPageBrief;
use App\Services\Ai\SalesPageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateSalesPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public AiSalesPage $page,
        public ?string $refineInstruction = null,
    ) {
        $this->onQueue(config('ai_sales_pages.queue', 'default'));
    }

    public function timeout(): int
    {
        return (int) config('ai_sales_pages.timeout', 180) + 30;
    }

    public function handle(SalesPageGenerator $generator): void
    {
        $page = $this->page->fresh();

        if (! $page) {
            return;
        }

        // A "fresh design / redesign" request must regenerate from scratch (no current
        // HTML to anchor to), otherwise the model just tweaks and looks identical.
        $isRedesign = $this->refineInstruction !== null && $this->isRedesignRequest($this->refineInstruction);

        $brief = new SalesPageBrief(
            title: $page->title,
            prompt: (string) $page->prompt,
            targetAudience: $page->target_audience,
            tone: $page->tone,
            designNotes: $page->design_notes,
            stylePreset: $page->style_preset,
            assets: $this->resolveAssets($page),
            brand: (array) config('ai_sales_pages.brand', []),
            currentHtml: ($this->refineInstruction !== null && ! $isRedesign) ? $page->html : null,
            refineInstruction: $isRedesign ? null : $this->refineInstruction,
            extraDirection: $isRedesign ? $this->refineInstruction : null,
        );

        $html = $generator->generate($brief);

        $page->forceFill([
            'html' => $html,
            'model' => (string) config('ai_sales_pages.model', 'gpt-4o'),
            'generation_status' => 'idle',
            'generation_error' => null,
        ])->save();

        $page->snapshotVersion(
            generatedBy: 'ai',
            label: $this->refineInstruction ? 'AI refinement' : 'AI generation',
            userId: $page->user_id,
        );

        if ($this->refineInstruction) {
            $page->messages()->create([
                'role' => 'assistant',
                'content' => "Done — I've updated the page based on your request. The live preview now reflects the change. Want anything else adjusted?",
                'status' => 'ok',
            ]);
        }
    }

    /**
     * Detect when the user is asking for a brand-new / redesigned look rather than a tweak.
     */
    private function isRedesignRequest(string $text): bool
    {
        return (bool) preg_match(
            '/(fresh\s*design|re-?design|different\s*(layout|design|look|style)|new\s*(layout|design|look|style)|'
            .'start\s*over|from\s*scratch|tukar\s*(layout|reka\s*bentuk|design|gaya)|reka\s*bentuk\s*(baru|lain|berbeza)|'
            .'design\s*(baru|lain|berbeza)|buat\s*(lain|baru)|layout\s*(baru|lain|berbeza))/i',
            $text
        );
    }

    /**
     * @return list<array{url: string, alt: string, title: string}>
     */
    private function resolveAssets(AiSalesPage $page): array
    {
        return $page->media()
            ->get()
            ->map(fn ($media): array => [
                'url' => $media->url,
                'alt' => (string) ($media->alt_text ?? ''),
                'title' => (string) ($media->title ?? ''),
            ])
            ->all();
    }

    public function failed(?Throwable $exception): void
    {
        $page = $this->page->fresh();

        $page?->forceFill([
            'generation_status' => 'failed',
            'generation_error' => $exception?->getMessage() ?? 'Generation failed.',
        ])->save();

        if ($page && $this->refineInstruction) {
            $page->messages()->create([
                'role' => 'assistant',
                'content' => 'Sorry, I could not apply that change ('.($exception?->getMessage() ?? 'generation failed').'). Please try rephrasing, or try again.',
                'status' => 'error',
            ]);
        }
    }
}
