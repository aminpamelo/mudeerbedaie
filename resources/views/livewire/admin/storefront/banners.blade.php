<?php

use App\Models\StoreBanner;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Manages the campaign slides that stack after the storefront hero's built-in
 * brand slide. Create/edit happens in a modal so the admin never loses sight of
 * the running order while reshuffling it.
 */
new class extends Component
{
    use WithFileUploads;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $eyebrow = '';

    public string $title = '';

    public string $subtitle = '';

    public string $cta_text = '';

    public string $cta_url = '';

    public bool $is_active = true;

    public int $sort_order = 0;

    public string $starts_at = '';

    public string $ends_at = '';

    public $image;

    public ?string $currentImage = null;

    public function rules(): array
    {
        return [
            'eyebrow' => 'nullable|string|max:255',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'cta_text' => 'nullable|string|max:60',
            'cta_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0|max:999',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'image' => 'nullable|image|max:4096',
        ];
    }

    public function with(): array
    {
        return [
            'banners' => StoreBanner::query()->ordered()->get(),
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->sort_order = (int) (StoreBanner::max('sort_order') ?? -1) + 1;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $banner = StoreBanner::findOrFail($id);

        $this->editingId = $banner->id;
        $this->eyebrow = (string) $banner->eyebrow;
        $this->title = $banner->title;
        $this->subtitle = (string) $banner->subtitle;
        $this->cta_text = (string) $banner->cta_text;
        $this->cta_url = (string) $banner->cta_url;
        $this->is_active = $banner->is_active;
        $this->sort_order = $banner->sort_order;
        $this->starts_at = $banner->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->ends_at = $banner->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->currentImage = $banner->image_url;
        $this->image = null;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $attributes = [
            'eyebrow' => $this->eyebrow ?: null,
            'title' => $this->title,
            'subtitle' => $this->subtitle ?: null,
            'cta_text' => $this->cta_text ?: null,
            'cta_url' => $this->cta_url ?: null,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'starts_at' => $this->starts_at ?: null,
            'ends_at' => $this->ends_at ?: null,
        ];

        if ($this->image) {
            $attributes['image_path'] = $this->image->store('store/banners', 'public');
        }

        $banner = $this->editingId
            ? tap(StoreBanner::findOrFail($this->editingId))->update($attributes)
            : StoreBanner::create($attributes);

        session()->flash('success', $this->editingId ? 'Banner updated.' : 'Banner created.');

        $this->showModal = false;
        $this->resetForm();

        unset($banner);
    }

    public function toggle(int $id): void
    {
        $banner = StoreBanner::findOrFail($id);
        $banner->update(['is_active' => ! $banner->is_active]);
    }

    public function delete(int $id): void
    {
        StoreBanner::findOrFail($id)->delete();

        session()->flash('success', 'Banner deleted.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url', 'starts_at', 'ends_at', 'image', 'currentImage']);
        $this->is_active = true;
        $this->sort_order = 0;
        $this->resetValidation();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Hero Banners</flux:heading>
            <flux:text class="mt-2">Campaign slides shown after the storefront's brand slide</flux:text>
        </div>
        <flux:button variant="primary" wire:click="create" icon="plus">
            New Banner
        </flux:button>
    </div>

    @if(session('success'))
        <flux:callout variant="success" class="mb-6" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <flux:callout class="mb-6" icon="information-circle">
        The homepage always opens with the built-in <strong>1 Rumah 1 Daie</strong> brand slide. Banners here are added after it — with none active, the hero stays a single static panel.
    </flux:callout>

    @if($banners->isEmpty())
        <div class="grid place-items-center rounded-xl border border-dashed border-zinc-300 py-16 text-center dark:border-zinc-700">
            <flux:icon name="photo" class="h-10 w-10 text-zinc-300" />
            <flux:heading size="lg" class="mt-3">No campaign banners yet</flux:heading>
            <flux:text class="mt-1">Add one to promote a launch, a Raya offer or a bundle.</flux:text>
        </div>
    @else
        <div class="space-y-3">
            @foreach($banners as $banner)
                <div wire:key="banner-{{ $banner->id }}" class="flex flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-4 sm:flex-row sm:items-center dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="h-24 w-full shrink-0 overflow-hidden rounded-lg bg-zinc-100 sm:w-40 dark:bg-zinc-800">
                        @if($banner->image_url)
                            <img src="{{ $banner->image_url }}" alt="" class="h-full w-full object-cover" />
                        @else
                            <div class="grid h-full w-full place-items-center bg-gradient-to-br from-violet-500 via-fuchsia-500 to-rose-400">
                                <flux:icon name="sparkles" class="h-6 w-6 text-white/80" />
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="lg" class="truncate">{{ $banner->title }}</flux:heading>
                            @if(! $banner->is_active)
                                <flux:badge color="zinc" size="sm">Off</flux:badge>
                            @elseif(! $banner->isScheduledNow())
                                <flux:badge color="amber" size="sm">Outside schedule</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">Live</flux:badge>
                            @endif
                            <flux:badge color="zinc" size="sm">#{{ $banner->sort_order }}</flux:badge>
                        </div>
                        @if($banner->subtitle)
                            <flux:text class="mt-1 line-clamp-1">{{ $banner->subtitle }}</flux:text>
                        @endif
                        @if($banner->starts_at || $banner->ends_at)
                            <flux:text size="sm" class="mt-1 text-zinc-400">
                                {{ $banner->starts_at?->format('d M Y H:i') ?? '—' }} → {{ $banner->ends_at?->format('d M Y H:i') ?? '—' }}
                            </flux:text>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <flux:button size="sm" variant="outline" wire:click="toggle({{ $banner->id }})">
                            <div class="flex items-center justify-center">
                                <flux:icon name="{{ $banner->is_active ? 'eye-slash' : 'eye' }}" class="mr-1 h-4 w-4" />
                                {{ $banner->is_active ? 'Disable' : 'Enable' }}
                            </div>
                        </flux:button>
                        <flux:button size="sm" variant="outline" wire:click="edit({{ $banner->id }})">Edit</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="delete({{ $banner->id }})" wire:confirm="Delete this banner?">Delete</flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal wire:model.self="showModal" class="md:w-[38rem]">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit banner' : 'New banner' }}</flux:heading>
                <flux:text class="mt-1">Shown as a full-width slide in the storefront hero.</flux:text>
            </div>

            <flux:field>
                <flux:label>Eyebrow</flux:label>
                <flux:input wire:model="eyebrow" placeholder="Promosi Raya" />
                <flux:description>Small label above the headline (optional)</flux:description>
                <flux:error name="eyebrow" />
            </flux:field>

            <flux:field>
                <flux:label>Headline</flux:label>
                <flux:input wire:model="title" placeholder="Pakej Raya — jimat sehingga 30%" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>Subtitle</flux:label>
                <flux:textarea wire:model="subtitle" rows="2" placeholder="Tawaran terhad sehingga 30 Ramadan." />
                <flux:error name="subtitle" />
            </flux:field>

            <flux:field>
                <flux:label>Background image</flux:label>
                <flux:input type="file" wire:model="image" accept="image/*" />
                <flux:description>Landscape works best (about 1600×800). Without one the slide uses the brand gradient.</flux:description>
                <flux:error name="image" />
                @if($image)
                    <img src="{{ $image->temporaryUrl() }}" alt="Preview" class="mt-2 h-28 w-full rounded-lg object-cover" />
                @elseif($currentImage)
                    <img src="{{ $currentImage }}" alt="Current" class="mt-2 h-28 w-full rounded-lg object-cover" />
                @endif
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Button text</flux:label>
                    <flux:input wire:model="cta_text" placeholder="Lihat pakej" />
                    <flux:error name="cta_text" />
                </flux:field>

                <flux:field>
                    <flux:label>Button link</flux:label>
                    <flux:input wire:model="cta_url" placeholder="/shop" />
                    <flux:error name="cta_url" />
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Starts at</flux:label>
                    <flux:input type="datetime-local" wire:model="starts_at" />
                    <flux:description>Leave empty to start immediately</flux:description>
                    <flux:error name="starts_at" />
                </flux:field>

                <flux:field>
                    <flux:label>Ends at</flux:label>
                    <flux:input type="datetime-local" wire:model="ends_at" />
                    <flux:description>Leave empty to run until switched off</flux:description>
                    <flux:error name="ends_at" />
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Order</flux:label>
                    <flux:input type="number" wire:model="sort_order" min="0" />
                    <flux:error name="sort_order" />
                </flux:field>

                <flux:field variant="inline">
                    <flux:checkbox wire:model="is_active" />
                    <flux:label>Active</flux:label>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Create banner' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
