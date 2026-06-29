<?php

use App\Jobs\GenerateSalesPageJob;
use App\Models\AiSalesPage;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelStepContent;
use App\Models\Media;
use App\Services\Ai\SalesPageRenderer;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Title('AI Sales Page Builder')]
class extends Component {
    public int $pageId;

    public string $uuid = '';

    // Brief
    public string $title = '';

    public string $slug = '';

    public string $prompt = '';

    public string $targetAudience = '';

    public string $tone = 'Professional';

    public string $designNotes = '';

    public string $stylePreset = 'auto';

    // Working draft
    public ?string $html = '';

    public ?string $customCss = '';

    public ?string $customJs = '';

    // SEO
    public string $metaTitle = '';

    public string $metaDescription = '';

    public ?int $ogImageMediaId = null;

    public ?string $ogImageUrl = null;

    // Brand assets
    public array $assetIds = [];

    /** @var list<array{id:int,url:string,title:string}> */
    public array $assets = [];

    // Lifecycle / status
    public string $status = 'draft';

    public string $generationStatus = 'idle';

    public ?string $generationError = null;

    public string $chatInput = '';

    // UI
    public string $activeTab = 'generate';

    public bool $showVersions = false;

    public bool $showFunnelModal = false;

    public ?int $linkedFunnelId = null;

    public ?string $funnelBuilderUrl = null;

    public string $previewDoc = '';

    public int $previewKey = 0;

    public function mount(string $page): void
    {
        $model = AiSalesPage::where('uuid', $page)->firstOrFail();
        $this->hydrateFrom($model);
        $this->refreshPreview();
    }

    private function hydrateFrom(AiSalesPage $model): void
    {
        $this->pageId = $model->id;
        $this->uuid = $model->uuid;
        $this->title = $model->title;
        $this->slug = $model->slug;
        $this->prompt = (string) $model->prompt;
        $this->targetAudience = (string) $model->target_audience;
        $this->tone = $model->tone ?: 'Professional';
        $this->designNotes = (string) $model->design_notes;
        $this->stylePreset = $model->style_preset ?: 'auto';
        $this->html = $model->html;
        $this->customCss = $model->custom_css;
        $this->customJs = $model->custom_js;
        $this->metaTitle = (string) $model->meta_title;
        $this->metaDescription = (string) $model->meta_description;
        $this->ogImageMediaId = $model->og_image_media_id;
        $this->ogImageUrl = $model->ogImage?->url;
        $this->status = $model->status;
        $this->generationStatus = $model->generation_status;
        $this->generationError = $model->generation_error;
        $this->linkedFunnelId = $model->funnel_id;
        if ($model->funnel) {
            $this->funnelBuilderUrl = $model->funnel->getBuilderUrl();
        }
        $this->assetIds = $model->media()->pluck('media.id')->all();
        $this->assets = $model->media()->get()->map(fn (Media $m): array => [
            'id' => $m->id,
            'url' => $m->url,
            'title' => (string) ($m->title ?? $m->original_filename),
        ])->all();
    }

    private function model(): AiSalesPage
    {
        return AiSalesPage::findOrFail($this->pageId);
    }

    private function persistBrief(): AiSalesPage
    {
        $model = $this->model();
        $model->forceFill([
            'title' => $this->title ?: $model->title,
            'prompt' => $this->prompt,
            'target_audience' => $this->targetAudience ?: null,
            'tone' => $this->tone ?: null,
            'design_notes' => $this->designNotes ?: null,
            'style_preset' => $this->stylePreset ?: 'auto',
        ])->save();
        $model->media()->sync($this->assetIds);

        return $model;
    }

    public function generate(): void
    {
        $this->validate(['prompt' => 'required|string|max:5000']);
        $this->dispatchGeneration(null);
    }

    public function sendChat(): void
    {
        $this->validate(['chatInput' => 'required|string|max:2000']);

        if (blank($this->html)) {
            $this->dispatch('notify', message: 'Generate the page first, then chat to refine it.');

            return;
        }

        $message = trim($this->chatInput);
        $this->model()->messages()->create(['role' => 'user', 'content' => $message, 'status' => 'ok']);
        $this->chatInput = '';

        $this->dispatchGeneration($message);
    }

    public function sendSuggestion(string $text): void
    {
        $this->chatInput = $text;
        $this->sendChat();
    }

    private function dispatchGeneration(?string $refine): void
    {
        $model = $this->persistBrief();
        $model->forceFill(['generation_status' => 'processing', 'generation_error' => null])->save();
        $this->generationStatus = 'processing';
        $this->generationError = null;

        GenerateSalesPageJob::dispatch($model, $refine);

        // Reflect immediately when the queue runs synchronously (tests / sync driver).
        $this->pollStatus();
    }

    public function pollStatus(): void
    {
        $model = $this->model();
        $this->generationStatus = $model->generation_status;
        $this->generationError = $model->generation_error;

        if ($model->generation_status !== 'processing') {
            $this->html = $model->html;
            $this->refreshPreview();
        }
    }

    public function saveDraft(): void
    {
        $this->validate([
            'title' => 'required|string|max:160',
            'slug' => 'required|string|max:200|alpha_dash|unique:ai_sales_pages,slug,'.$this->pageId,
            'metaTitle' => 'nullable|string|max:160',
            'metaDescription' => 'nullable|string|max:500',
        ]);

        $this->model()->forceFill([
            'title' => $this->title,
            'slug' => $this->slug,
            'prompt' => $this->prompt,
            'target_audience' => $this->targetAudience ?: null,
            'tone' => $this->tone ?: null,
            'design_notes' => $this->designNotes ?: null,
            'style_preset' => $this->stylePreset ?: 'auto',
            'html' => $this->html,
            'custom_css' => $this->customCss,
            'custom_js' => $this->customJs,
            'meta_title' => $this->metaTitle ?: null,
            'meta_description' => $this->metaDescription ?: null,
            'og_image_media_id' => $this->ogImageMediaId,
        ])->save();

        $this->model()->media()->sync($this->assetIds);

        $this->refreshPreview();
        $this->dispatch('saved');
        session()->flash('flash', 'Draft saved.');
    }

    public function publish(): void
    {
        if (blank($this->html)) {
            $this->dispatch('notify', message: 'Generate or write the page before publishing.');

            return;
        }

        $this->saveDraft();

        $model = $this->model();
        $version = $model->snapshotVersion(generatedBy: 'human', label: 'Published');
        $model->forceFill([
            'status' => 'published',
            'published_version_id' => $version->id,
            'published_at' => now(),
        ])->save();

        $this->status = 'published';
        session()->flash('flash', 'Sales page published.');
    }

    public function unpublish(): void
    {
        $this->model()->forceFill([
            'status' => 'draft',
            'published_at' => null,
        ])->save();
        $this->status = 'draft';
        session()->flash('flash', 'Sales page unpublished.');
    }

    #[On('media-picker:selected')]
    public function onMediaPicked(string $name, array $media): void
    {
        if ($name === 'ai-assets') {
            foreach ($media as $item) {
                if (! in_array($item['id'], $this->assetIds, true)) {
                    $this->assetIds[] = $item['id'];
                    $this->assets[] = ['id' => $item['id'], 'url' => $item['url'], 'title' => $item['title'] ?? ''];
                }
            }
            $this->model()->media()->syncWithoutDetaching($this->assetIds);
        }

        if ($name === 'ai-og') {
            $first = $media[0] ?? null;
            if ($first) {
                $this->ogImageMediaId = $first['id'];
                $this->ogImageUrl = $first['url'];
            }
        }
    }

    public function removeAsset(int $mediaId): void
    {
        $this->assetIds = array_values(array_filter($this->assetIds, fn ($id) => $id !== $mediaId));
        $this->assets = array_values(array_filter($this->assets, fn ($a) => $a['id'] !== $mediaId));
        $this->model()->media()->detach($mediaId);
    }

    public function clearOgImage(): void
    {
        $this->ogImageMediaId = null;
        $this->ogImageUrl = null;
    }

    public function refreshPreview(): void
    {
        $renderer = app(SalesPageRenderer::class);
        $this->previewDoc = $renderer->document(
            (string) $this->html,
            $this->customCss,
            $this->customJs,
            [
                'title' => $this->metaTitle ?: $this->title,
                'description' => $this->metaDescription,
                'og_image' => $this->ogImageUrl,
            ],
        );
        $this->previewKey++;
    }

    public function restoreVersion(int $versionId): void
    {
        $version = $this->model()->versions()->findOrFail($versionId);
        $this->html = $version->html;
        $this->customCss = $version->custom_css;
        $this->customJs = $version->custom_js;
        $this->model()->forceFill([
            'html' => $version->html,
            'custom_css' => $version->custom_css,
            'custom_js' => $version->custom_js,
        ])->save();
        $this->refreshPreview();
        $this->showVersions = false;
        session()->flash('flash', 'Restored version '.$version->version.'.');
    }

    /**
     * Bridge the generated page into the Funnel engine so it inherits checkout,
     * custom domains, analytics and pixel tracking. Stores the full HTML using
     * the proven single-TextBlock "full page" format the funnel renderer supports.
     */
    public function sendToFunnel(): void
    {
        $model = $this->model();

        if (blank($model->html)) {
            $this->dispatch('notify', message: 'Generate the page before sending it to a funnel.');

            return;
        }

        $this->saveDraft();
        $model->refresh();

        $content = [
            'content' => [[
                'type' => 'TextBlock',
                'props' => ['id' => 'ai-fullpage', 'content' => (string) $model->html],
            ]],
            'root' => ['props' => []],
        ];

        if ($model->funnel_id && ($step = FunnelStep::find($model->funnel_step_id))) {
            // Re-sync into the already-linked funnel: publish a fresh content version.
            $step->content()->update(['is_published' => false]);
            FunnelStepContent::create([
                'funnel_step_id' => $step->id,
                'content' => $content,
                'custom_css' => $model->custom_css,
                'custom_js' => $model->custom_js,
                'meta_title' => $model->meta_title,
                'meta_description' => $model->meta_description,
                'version' => (int) $step->content()->max('version') + 1,
                'is_published' => true,
                'published_at' => now(),
            ]);
            $funnel = $step->funnel;
        } else {
            $funnel = Funnel::create([
                'user_id' => $model->user_id ?? auth()->id(),
                'name' => $model->title,
                'type' => 'sales',
                'status' => 'draft',
            ]);

            $step = FunnelStep::create([
                'funnel_id' => $funnel->id,
                'name' => $model->title,
                'slug' => 'main',
                'type' => 'sales',
                'sort_order' => 0,
                'is_active' => true,
            ]);

            FunnelStepContent::create([
                'funnel_step_id' => $step->id,
                'content' => $content,
                'custom_css' => $model->custom_css,
                'custom_js' => $model->custom_js,
                'meta_title' => $model->meta_title,
                'meta_description' => $model->meta_description,
                'version' => 1,
                'is_published' => true,
                'published_at' => now(),
            ]);

            $model->forceFill(['funnel_id' => $funnel->id, 'funnel_step_id' => $step->id])->save();
        }

        $this->linkedFunnelId = $funnel->id;
        $this->funnelBuilderUrl = $funnel->getBuilderUrl();
        $this->showFunnelModal = true;
    }

    public function with(): array
    {
        return [
            'versions' => $this->showVersions
                ? $this->model()->versions()->with('creator')->get()
                : collect(),
            'publicUrl' => $this->model()->getPublicUrl(),
            'stylePresets' => \App\Services\Ai\DesignSystemLibrary::options(),
            'chatMessages' => $this->model()->messages()->get(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-[1600px]" x-data="{ device: 'desktop' }">
    {{-- Header --}}
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.ai-sales-pages.index')" wire:navigate />
            <div>
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ $title ?: __('Untitled page') }}</flux:heading>
                    @if ($status === 'published')
                        <flux:badge color="green" size="sm">{{ __('Published') }}</flux:badge>
                    @elseif ($status === 'archived')
                        <flux:badge color="zinc" size="sm">{{ __('Archived') }}</flux:badge>
                    @else
                        <flux:badge color="amber" size="sm">{{ __('Draft') }}</flux:badge>
                    @endif
                </div>
                <div class="font-mono text-xs text-zinc-400">/{{ config('ai_sales_pages.public_prefix', 'p') }}/{{ $slug }}</div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="clock" wire:click="$set('showVersions', true)">{{ __('Versions') }}</flux:button>
            <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="$publicUrl" target="_blank">{{ __('Preview') }}</flux:button>
            <flux:button size="sm" variant="outline" icon="bookmark" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                <span wire:loading.remove wire:target="saveDraft">{{ __('Save') }}</span>
                <span wire:loading wire:target="saveDraft">{{ __('Saving...') }}</span>
            </flux:button>
            @if ($status === 'published')
                <flux:button size="sm" variant="ghost" icon="eye-slash" wire:click="unpublish">{{ __('Unpublish') }}</flux:button>
            @else
                <flux:button size="sm" variant="primary" icon="rocket-launch" wire:click="publish">{{ __('Publish') }}</flux:button>
            @endif

            <flux:dropdown position="bottom" align="end">
                <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                <flux:menu>
                    <flux:menu.item icon="funnel" wire:click="sendToFunnel"
                        wire:confirm="{{ __('Send this page to the funnel engine? This enables checkout, custom domains and tracking.') }}">
                        {{ $linkedFunnelId ? __('Re-sync to Funnel') : __('Send to Funnel (add checkout)') }}
                    </flux:menu.item>
                    @if ($funnelBuilderUrl)
                        <flux:menu.item icon="arrow-top-right-on-square" href="{{ $funnelBuilderUrl }}" target="_blank">
                            {{ __('Open linked funnel') }}
                        </flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    @if (session('flash'))
        <flux:callout variant="success" class="mb-4" icon="check-circle" :heading="session('flash')" />
    @endif

    {{-- Workspace: controls (left) + live preview (right) --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- LEFT: controls --}}
        <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            {{-- Tabs --}}
            <div class="flex gap-1 border-b border-zinc-200 p-2 dark:border-zinc-700">
                @foreach ([
                    'generate' => ['Generate', 'sparkles'],
                    'chat' => ['AI Chat', 'chat-bubble-left-right'],
                    'html' => ['HTML', 'code-bracket'],
                    'code' => ['CSS / JS', 'paint-brush'],
                    'seo' => ['SEO', 'globe-alt'],
                ] as $tab => [$label, $icon])
                    <button type="button" wire:click="$set('activeTab', '{{ $tab }}')"
                        class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $activeTab === $tab ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700/50' }}">
                        <flux:icon :name="$icon" class="h-4 w-4" />
                        {{ __($label) }}
                    </button>
                @endforeach
            </div>

            <div class="p-4">
                {{-- GENERATE TAB --}}
                <div @class(['space-y-4' => true, 'hidden' => $activeTab !== 'generate'])>
                    <flux:input wire:model="title" label="{{ __('Page title') }}" />

                    <flux:textarea wire:model="prompt" label="{{ __('Brief / offer') }}" rows="5"
                        placeholder="{{ __('What are you selling? Audience, benefits, price, guarantee, urgency, CTA...') }}" />

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <flux:input wire:model="targetAudience" label="{{ __('Target audience') }}" />
                        <flux:select wire:model="tone" label="{{ __('Tone') }}">
                            @foreach (['Professional', 'Friendly', 'Urgent', 'Playful', 'Luxurious', 'Bold'] as $t)
                                <flux:select.option value="{{ $t }}">{{ __($t) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:select wire:model="stylePreset" label="{{ __('Design style (fonts & vibe)') }}">
                        @foreach ($stylePresets as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea wire:model="designNotes" label="{{ __('Design direction (optional)') }}" rows="3"
                        placeholder="{{ __('Colours, mood & effects. e.g. Burgundy #77181e + soft pink #fdf2f8 + rose gold #b76e79; premium, marquee USP bar, pulse CTA. (Leave fonts to the Design style above.)') }}" />

                    {{-- Brand assets --}}
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <flux:text class="text-sm font-medium">{{ __('Brand images') }}</flux:text>
                            <livewire:admin.media.picker name="ai-assets" type="image" :multiple="true"
                                trigger-label="{{ __('Add images') }}" trigger-icon="photo" trigger-variant="ghost" />
                        </div>
                        @if (count($assets) > 0)
                            <div class="flex flex-wrap gap-2">
                                @foreach ($assets as $asset)
                                    <div wire:key="asset-{{ $asset['id'] }}" class="group relative h-16 w-16 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-600">
                                        <img src="{{ $asset['url'] }}" alt="{{ $asset['title'] }}" class="h-full w-full object-cover">
                                        <button type="button" wire:click="removeAsset({{ $asset['id'] }})"
                                            class="absolute right-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition group-hover:opacity-100">
                                            <flux:icon name="x-mark" class="h-3 w-3" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <flux:text class="text-xs text-zinc-400">{{ __('AI will use these exact images in the page. Optional.') }}</flux:text>
                        @endif
                    </div>

                    @if ($generationStatus === 'failed')
                        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ __('Generation failed') }}">
                            {{ $generationError ?: __('Something went wrong. Check the OpenAI API key and try again.') }}
                        </flux:callout>
                    @endif

                    @if ($generationStatus === 'processing')
                        <div wire:poll.2s="pollStatus" class="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-500/30 dark:bg-blue-500/10">
                            <flux:icon name="arrow-path" class="h-5 w-5 animate-spin text-blue-500" />
                            <div>
                                <flux:text class="text-sm font-medium text-blue-700 dark:text-blue-300">{{ __('AI is writing your page...') }}</flux:text>
                                <flux:text class="text-xs text-blue-600/70 dark:text-blue-400/70">{{ __('This usually takes 15–60 seconds.') }}</flux:text>
                            </div>
                        </div>
                    @else
                        <flux:button variant="primary" icon="sparkles" class="w-full" wire:click="generate" wire:loading.attr="disabled" wire:target="generate">
                            <span wire:loading.remove wire:target="generate">{{ filled($html) ? __('Regenerate from scratch') : __('Generate page with AI') }}</span>
                            <span wire:loading wire:target="generate">{{ __('Starting...') }}</span>
                        </flux:button>

                        @if (filled($html))
                            <button type="button" wire:click="$set('activeTab', 'chat')"
                                class="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-zinc-300 px-3 py-2.5 text-sm font-medium text-zinc-600 transition hover:border-blue-400 hover:text-blue-600 dark:border-zinc-600 dark:text-zinc-300 dark:hover:border-blue-500 dark:hover:text-blue-400">
                                <flux:icon name="chat-bubble-left-right" class="h-4 w-4" />
                                {{ __('Chat with AI to refine this page') }}
                            </button>
                        @endif
                    @endif
                </div>

                {{-- CHAT TAB --}}
                <div @class(['flex flex-col' => true, 'hidden' => $activeTab !== 'chat'])>
                    <div class="mb-3 flex items-center gap-2">
                        <flux:icon name="sparkles" class="h-4 w-4 text-blue-500" />
                        <flux:text class="text-sm font-medium">{{ __('Chat with the AI designer') }}</flux:text>
                    </div>

                    <div wire:key="chat-scroll-{{ $chatMessages->count() }}-{{ $generationStatus }}" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                        class="mb-3 max-h-[46vh] min-h-[220px] space-y-3 overflow-y-auto rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                        @forelse ($chatMessages as $msg)
                            @if ($msg->role === 'user')
                                <div wire:key="msg-{{ $msg->id }}" class="flex justify-end">
                                    <div class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-br-sm bg-blue-600 px-3.5 py-2 text-sm text-white">{{ $msg->content }}</div>
                                </div>
                            @else
                                <div wire:key="msg-{{ $msg->id }}" class="flex justify-start gap-2">
                                    <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/20">
                                        <flux:icon name="sparkles" class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-bl-sm px-3.5 py-2 text-sm {{ $msg->status === 'error' ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'bg-white text-zinc-700 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:ring-zinc-700' }}">{{ $msg->content }}</div>
                                </div>
                            @endif
                        @empty
                            <div class="flex h-full flex-col items-center justify-center py-8 text-center">
                                <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-500/10">
                                    <flux:icon name="chat-bubble-left-right" class="h-5 w-5 text-blue-500" />
                                </div>
                                <flux:text class="text-sm font-medium">{{ __('Tell the AI what to improve') }}</flux:text>
                                <flux:text class="mt-1 text-xs text-zinc-400">{{ __('It edits the live page, keeps your copy, and saves every change as a version.') }}</flux:text>
                            </div>
                        @endforelse

                        @if ($generationStatus === 'processing')
                            <div class="flex items-center gap-2">
                                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/20">
                                    <flux:icon name="arrow-path" class="h-3.5 w-3.5 animate-spin text-blue-600 dark:text-blue-400" />
                                </div>
                                <div class="rounded-2xl rounded-bl-sm bg-white px-3.5 py-2 text-sm text-zinc-500 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700">{{ __('Working on it…') }}</div>
                            </div>
                        @endif
                    </div>

                    @if ($generationStatus !== 'processing' && filled($html))
                        <div class="mb-3 flex flex-wrap gap-2">
                            @foreach ([
                                'Redesign with a fresh, completely different layout (keep the offer and brand colours)',
                                'Make the hero headline punchier and bigger',
                                'Add a pricing section with the offer and a buy button',
                                'Add 3 short testimonials with names',
                                'Add an FAQ section',
                                'Add a sticky WhatsApp order button',
                                'Make the colours a little brighter and more premium',
                            ] as $suggestion)
                                <button type="button" wire:click="sendSuggestion(@js($suggestion))"
                                    class="rounded-full border border-zinc-200 px-3 py-1 text-xs text-zinc-600 transition hover:border-blue-400 hover:text-blue-600 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-blue-500 dark:hover:text-blue-400">
                                    {{ $suggestion }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <form wire:submit="sendChat" class="flex items-end gap-2">
                        <flux:textarea wire:model="chatInput" rows="2" class="flex-1"
                            placeholder="{{ blank($html) ? __('Generate the page first, then chat to refine it…') : __('e.g. tukar warna jadi lebih lembut, tambah seksyen harga, besarkan headline…') }}" />
                        <flux:button type="submit" variant="primary" icon="paper-airplane"
                            wire:loading.attr="disabled" wire:target="sendChat,sendSuggestion"
                            :disabled="$generationStatus === 'processing' || blank($html)">
                            {{ __('Send') }}
                        </flux:button>
                    </form>
                </div>

                {{-- HTML TAB --}}
                <div @class(['space-y-3' => true, 'hidden' => $activeTab !== 'html'])>
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm font-medium">{{ __('Page HTML') }}</flux:text>
                        <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="refreshPreview">{{ __('Refresh preview') }}</flux:button>
                    </div>
                    <textarea wire:model="html" spellcheck="false"
                        class="h-[60vh] w-full resize-none rounded-lg border border-zinc-300 bg-zinc-50 p-3 font-mono text-xs leading-relaxed text-zinc-800 focus:border-blue-500 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200"
                        placeholder="{{ __('Your generated HTML appears here. Edit freely, then Refresh preview.') }}"></textarea>
                    <flux:button variant="outline" size="sm" icon="bookmark" wire:click="saveDraft" class="w-full">{{ __('Save changes') }}</flux:button>
                </div>

                {{-- CSS/JS TAB --}}
                <div @class(['space-y-4' => true, 'hidden' => $activeTab !== 'code'])>
                    <div>
                        <flux:text class="mb-1 text-sm font-medium">{{ __('Custom CSS') }}</flux:text>
                        <textarea wire:model="customCss" spellcheck="false"
                            class="h-40 w-full resize-none rounded-lg border border-zinc-300 bg-zinc-50 p-3 font-mono text-xs text-zinc-800 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200"
                            placeholder=".my-class { color: #2563EB; }"></textarea>
                    </div>
                    <div>
                        <flux:text class="mb-1 text-sm font-medium">{{ __('Custom JS') }}</flux:text>
                        <textarea wire:model="customJs" spellcheck="false"
                            class="h-40 w-full resize-none rounded-lg border border-zinc-300 bg-zinc-50 p-3 font-mono text-xs text-zinc-800 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200"
                            placeholder="document.addEventListener('DOMContentLoaded', () => {});"></textarea>
                    </div>
                    <flux:button variant="outline" size="sm" icon="bookmark" wire:click="saveDraft" class="w-full">{{ __('Save changes') }}</flux:button>
                </div>

                {{-- SEO TAB --}}
                <div @class(['space-y-4' => true, 'hidden' => $activeTab !== 'seo'])>
                    <flux:input wire:model="slug" label="{{ __('URL slug') }}" description="{{ __('Public address of the page.') }}" />
                    <flux:input wire:model="metaTitle" label="{{ __('Meta title') }}" />
                    <flux:textarea wire:model="metaDescription" label="{{ __('Meta description') }}" rows="3" />
                    <div>
                        <flux:text class="mb-2 text-sm font-medium">{{ __('Social share image (OG)') }}</flux:text>
                        @if ($ogImageUrl)
                            <div class="flex items-center gap-3">
                                <img src="{{ $ogImageUrl }}" alt="OG image" class="h-16 w-28 rounded-lg object-cover">
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearOgImage">{{ __('Remove') }}</flux:button>
                            </div>
                        @else
                            <livewire:admin.media.picker name="ai-og" type="image" :multiple="false"
                                trigger-label="{{ __('Choose image') }}" trigger-icon="photo" trigger-variant="outline" />
                        @endif
                    </div>
                    <flux:button variant="outline" size="sm" icon="bookmark" wire:click="saveDraft" class="w-full">{{ __('Save changes') }}</flux:button>
                </div>
            </div>
        </div>

        {{-- RIGHT: live preview --}}
        <div class="rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
                <div class="flex items-center gap-2">
                    <flux:icon name="eye" class="h-4 w-4 text-zinc-400" />
                    <flux:text class="text-sm font-medium">{{ __('Live preview') }}</flux:text>
                </div>
                <div class="flex items-center gap-1">
                    <flux:button size="xs" variant="ghost" icon="computer-desktop" x-on:click="device='desktop'" x-bind:class="device==='desktop' ? 'text-blue-500' : ''" />
                    <flux:button size="xs" variant="ghost" icon="device-phone-mobile" x-on:click="device='mobile'" x-bind:class="device==='mobile' ? 'text-blue-500' : ''" />
                    <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="refreshPreview" />
                </div>
            </div>
            <div class="flex justify-center overflow-hidden p-3">
                <div class="overflow-hidden rounded-lg bg-white shadow-sm transition-all duration-300"
                    x-bind:style="device==='mobile' ? 'width:390px' : 'width:100%'">
                    <iframe wire:key="preview-{{ $previewKey }}" srcdoc="{{ $previewDoc }}" sandbox="allow-scripts allow-popups"
                        class="h-[72vh] w-full border-0" title="Sales page preview"></iframe>
                </div>
            </div>
        </div>
    </div>

    {{-- Versions modal --}}
    <flux:modal wire:model.self="showVersions" class="w-full md:w-[560px]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Version history') }}</flux:heading>
            @if ($versions->count() > 0)
                <div class="max-h-[60vh] space-y-2 overflow-y-auto">
                    @foreach ($versions as $version)
                        <div wire:key="ver-{{ $version->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div>
                                <div class="flex items-center gap-2">
                                    <flux:text class="font-medium">v{{ $version->version }}</flux:text>
                                    <flux:badge size="sm" :color="$version->generated_by === 'ai' ? 'blue' : 'zinc'">
                                        {{ $version->generated_by === 'ai' ? __('AI') : __('Manual') }}
                                    </flux:badge>
                                    @if ($version->label)<flux:text class="text-xs text-zinc-400">{{ $version->label }}</flux:text>@endif
                                </div>
                                <flux:text class="text-xs text-zinc-400">{{ $version->created_at?->diffForHumans() }} · {{ $version->creator?->name ?? __('System') }}</flux:text>
                            </div>
                            <flux:button size="sm" variant="outline" icon="arrow-uturn-left" wire:click="restoreVersion({{ $version->id }})"
                                wire:confirm="{{ __('Restore this version into the working draft?') }}">{{ __('Restore') }}</flux:button>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text class="py-6 text-center text-sm text-zinc-400">{{ __('No versions yet. Generate or publish to create one.') }}</flux:text>
            @endif
            <div class="flex justify-end border-t border-zinc-200 pt-3 dark:border-zinc-700">
                <flux:button variant="ghost" wire:click="$set('showVersions', false)">{{ __('Close') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Send to Funnel result modal --}}
    <flux:modal wire:model.self="showFunnelModal" class="w-full md:w-[480px]">
        <div class="space-y-4 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-green-50 dark:bg-green-500/10">
                <flux:icon name="funnel" class="h-6 w-6 text-green-600 dark:text-green-400" />
            </div>
            <flux:heading size="lg">{{ __('Sent to the funnel engine') }}</flux:heading>
            <flux:text>{{ __('Your page is now a draft funnel. Open the funnel builder to add a checkout, products, custom domain and tracking, then publish it there.') }}</flux:text>
            <div class="flex justify-center gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showFunnelModal', false)">{{ __('Later') }}</flux:button>
                @if ($funnelBuilderUrl)
                    <flux:button variant="primary" icon="arrow-top-right-on-square" href="{{ $funnelBuilderUrl }}" target="_blank">{{ __('Open funnel builder') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
