<?php

use App\Models\AiSalesPage;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Title('AI Sales Pages')]
class extends Component {
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    // Create modal
    public bool $showCreateModal = false;

    public string $newTitle = '';

    public string $newPrompt = '';

    public string $newAudience = '';

    public string $newTone = 'Professional';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'pages' => AiSalesPage::query()
                ->with('creator')
                ->withCount('versions')
                ->search($this->search)
                ->status($this->statusFilter)
                ->latest()
                ->paginate(12),
            'stats' => [
                'total' => AiSalesPage::count(),
                'published' => AiSalesPage::where('status', 'published')->count(),
                'drafts' => AiSalesPage::where('status', 'draft')->count(),
            ],
        ];
    }

    public function createPage(): void
    {
        $this->validate([
            'newTitle' => 'required|string|max:160',
            'newPrompt' => 'required|string|max:5000',
            'newAudience' => 'nullable|string|max:160',
            'newTone' => 'nullable|string|max:60',
        ]);

        $page = AiSalesPage::create([
            'title' => $this->newTitle,
            'prompt' => $this->newPrompt,
            'target_audience' => $this->newAudience ?: null,
            'tone' => $this->newTone ?: null,
            'status' => 'draft',
        ]);

        $this->redirectRoute('admin.ai-sales-pages.edit', ['page' => $page->uuid], navigate: true);
    }

    public function duplicate(int $id): void
    {
        $page = AiSalesPage::findOrFail($id);

        $copy = $page->replicate(['uuid', 'slug', 'published_version_id', 'published_at', 'status', 'funnel_id', 'funnel_step_id']);
        $copy->uuid = (string) \Illuminate\Support\Str::uuid();
        $copy->title = $page->title.' (Copy)';
        $copy->slug = AiSalesPage::uniqueSlug($copy->title);
        $copy->status = 'draft';
        $copy->published_version_id = null;
        $copy->published_at = null;
        $copy->save();

        session()->flash('flash', 'Sales page duplicated.');
        $this->redirectRoute('admin.ai-sales-pages.edit', ['page' => $copy->uuid], navigate: true);
    }

    public function delete(int $id): void
    {
        AiSalesPage::findOrFail($id)->delete();
        session()->flash('flash', 'Sales page deleted.');
    }
}; ?>

<div class="mx-auto w-full max-w-7xl">
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" class="flex items-center gap-2">
                <flux:icon name="sparkles" class="h-6 w-6 text-blue-500" />
                {{ __('AI Sales Pages') }}
            </flux:heading>
            <flux:text class="mt-2">{{ __('Generate high-converting sales pages with AI, then publish or send them to a funnel.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
            {{ __('New Sales Page') }}
        </flux:button>
    </div>

    @if (session('flash'))
        <flux:callout variant="success" class="mb-4" icon="check-circle" :heading="session('flash')" />
    @endif

    {{-- Stats --}}
    <div class="mb-6 grid grid-cols-3 gap-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="text-sm text-zinc-500">{{ __('Total Pages') }}</flux:text>
            <div class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $stats['total'] }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="text-sm text-zinc-500">{{ __('Published') }}</flux:text>
            <div class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['published'] }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="text-sm text-zinc-500">{{ __('Drafts') }}</flux:text>
            <div class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $stats['drafts'] }}</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search pages...') }}" icon="magnifying-glass" clearable />
        </div>
        <flux:select wire:model.live="statusFilter" placeholder="{{ __('All Status') }}" class="sm:w-48">
            <flux:select.option value="">{{ __('All Status') }}</flux:select.option>
            <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
            <flux:select.option value="published">{{ __('Published') }}</flux:select.option>
            <flux:select.option value="archived">{{ __('Archived') }}</flux:select.option>
        </flux:select>
    </div>

    {{-- List --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
        @if ($pages->count() > 0)
            <table class="w-full text-left">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Page') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="hidden px-4 py-3 font-medium md:table-cell">{{ __('Versions') }}</th>
                        <th class="hidden px-4 py-3 font-medium lg:table-cell">{{ __('Updated') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                    @foreach ($pages as $page)
                        <tr wire:key="page-{{ $page->id }}" class="group transition hover:bg-zinc-50 dark:hover:bg-zinc-900/40">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.ai-sales-pages.edit', $page->uuid) }}" wire:navigate class="block">
                                    <div class="font-semibold text-zinc-900 group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">{{ $page->title }}</div>
                                    <div class="font-mono text-xs text-zinc-400">/{{ config('ai_sales_pages.public_prefix', 'p') }}/{{ $page->slug }}</div>
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                @if ($page->generation_status === 'processing')
                                    <flux:badge color="blue" size="sm" icon="arrow-path">{{ __('Generating') }}</flux:badge>
                                @elseif ($page->generation_status === 'failed')
                                    <flux:badge color="red" size="sm" icon="exclamation-triangle">{{ __('Failed') }}</flux:badge>
                                @elseif ($page->status === 'published')
                                    <flux:badge color="green" size="sm">{{ __('Published') }}</flux:badge>
                                @elseif ($page->status === 'archived')
                                    <flux:badge color="zinc" size="sm">{{ __('Archived') }}</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm">{{ __('Draft') }}</flux:badge>
                                @endif
                            </td>
                            <td class="hidden px-4 py-3 text-sm text-zinc-500 md:table-cell">{{ $page->versions_count }}</td>
                            <td class="hidden px-4 py-3 text-sm text-zinc-500 lg:table-cell">{{ $page->updated_at?->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('admin.ai-sales-pages.edit', $page->uuid)" wire:navigate>
                                        {{ __('Edit') }}
                                    </flux:button>
                                    @if ($page->status === 'published')
                                        <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="$page->getPublicUrl()" target="_blank" />
                                    @endif
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            <flux:menu.item icon="document-duplicate" wire:click="duplicate({{ $page->id }})">{{ __('Duplicate') }}</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $page->id }})" wire:confirm="{{ __('Delete this sales page? This cannot be undone.') }}">{{ __('Delete') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-500/10">
                    <flux:icon name="sparkles" class="h-7 w-7 text-blue-500" />
                </div>
                <flux:heading size="lg">{{ __('No sales pages yet') }}</flux:heading>
                <flux:text class="mt-1 max-w-md">{{ __('Describe your offer and let AI write a complete, high-converting sales page in seconds.') }}</flux:text>
                <flux:button variant="primary" icon="plus" class="mt-4" wire:click="$set('showCreateModal', true)">{{ __('Create your first page') }}</flux:button>
            </div>
        @endif
    </div>

    @if ($pages->hasPages())
        <div class="mt-4">{{ $pages->links() }}</div>
    @endif

    {{-- Create modal --}}
    <flux:modal wire:model.self="showCreateModal" class="w-full md:w-[640px]">
        <form wire:submit="createPage" class="space-y-5">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2">
                    <flux:icon name="sparkles" class="h-5 w-5 text-blue-500" />
                    {{ __('New AI Sales Page') }}
                </flux:heading>
                <flux:text class="mt-1">{{ __('Give it a name and describe the offer. You can generate and refine the page next.') }}</flux:text>
            </div>

            <flux:input wire:model="newTitle" label="{{ __('Page title') }}" placeholder="{{ __('e.g. Ramadan Quran Class — Limited Seats') }}" required />

            <flux:textarea wire:model="newPrompt" label="{{ __('Describe the offer / brief') }}" rows="5"
                placeholder="{{ __('What are you selling? Who is it for? Key benefits, price, guarantee, urgency, call to action...') }}" required />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="newAudience" label="{{ __('Target audience') }}" placeholder="{{ __('e.g. Busy Muslim parents') }}" />
                <flux:select wire:model="newTone" label="{{ __('Tone of voice') }}">
                    <flux:select.option value="Professional">{{ __('Professional') }}</flux:select.option>
                    <flux:select.option value="Friendly">{{ __('Friendly') }}</flux:select.option>
                    <flux:select.option value="Urgent">{{ __('Urgent') }}</flux:select.option>
                    <flux:select.option value="Playful">{{ __('Playful') }}</flux:select.option>
                    <flux:select.option value="Luxurious">{{ __('Luxurious') }}</flux:select.option>
                    <flux:select.option value="Bold">{{ __('Bold') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)" type="button">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="arrow-right" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createPage">{{ __('Create & open builder') }}</span>
                    <span wire:loading wire:target="createPage">{{ __('Creating...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
