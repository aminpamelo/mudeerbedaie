<?php

use App\Models\StoreTestimonial;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Manages the customer testimonials on the storefront homepage.
 *
 * Nothing here is pre-filled: the homepage section stays hidden until real
 * quotes are entered, so the store never publishes invented reviews.
 */
new class extends Component
{
    use WithFileUploads;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $author_name = '';

    public string $author_title = '';

    public string $quote = '';

    public ?int $rating = 5;

    public bool $is_active = true;

    public int $sort_order = 0;

    public $photo;

    public ?string $currentPhoto = null;

    public function rules(): array
    {
        return [
            'author_name' => 'required|string|max:255',
            'author_title' => 'nullable|string|max:255',
            'quote' => 'required|string|max:600',
            'rating' => 'nullable|integer|min:1|max:5',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0|max:999',
            'photo' => 'nullable|image|max:2048',
        ];
    }

    public function with(): array
    {
        return [
            'testimonials' => StoreTestimonial::query()->ordered()->get(),
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->sort_order = (int) (StoreTestimonial::max('sort_order') ?? -1) + 1;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $testimonial = StoreTestimonial::findOrFail($id);

        $this->editingId = $testimonial->id;
        $this->author_name = $testimonial->author_name;
        $this->author_title = (string) $testimonial->author_title;
        $this->quote = $testimonial->quote;
        $this->rating = $testimonial->rating;
        $this->is_active = $testimonial->is_active;
        $this->sort_order = $testimonial->sort_order;
        $this->currentPhoto = $testimonial->photo_url;
        $this->photo = null;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $attributes = [
            'author_name' => $this->author_name,
            'author_title' => $this->author_title ?: null,
            'quote' => $this->quote,
            'rating' => $this->rating,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];

        if ($this->photo) {
            $attributes['author_photo_path'] = $this->photo->store('store/testimonials', 'public');
        }

        if ($this->editingId) {
            StoreTestimonial::findOrFail($this->editingId)->update($attributes);
        } else {
            StoreTestimonial::create($attributes);
        }

        session()->flash('success', $this->editingId ? 'Testimonial updated.' : 'Testimonial added.');

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggle(int $id): void
    {
        $testimonial = StoreTestimonial::findOrFail($id);
        $testimonial->update(['is_active' => ! $testimonial->is_active]);
    }

    public function delete(int $id): void
    {
        StoreTestimonial::findOrFail($id)->delete();

        session()->flash('success', 'Testimonial deleted.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'author_name', 'author_title', 'quote', 'photo', 'currentPhoto']);
        $this->rating = 5;
        $this->is_active = true;
        $this->sort_order = 0;
        $this->resetValidation();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Testimonials</flux:heading>
            <flux:text class="mt-2">Customer quotes shown on the storefront homepage</flux:text>
        </div>
        <flux:button variant="primary" wire:click="create" icon="plus">
            New Testimonial
        </flux:button>
    </div>

    @if(session('success'))
        <flux:callout variant="success" class="mb-6" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <flux:callout variant="warning" class="mb-6" icon="exclamation-triangle">
        Only enter real feedback you have actually received. The homepage section stays hidden while this list is empty — an empty section is far better than an invented review.
    </flux:callout>

    @if($testimonials->isEmpty())
        <div class="grid place-items-center rounded-xl border border-dashed border-zinc-300 py-16 text-center dark:border-zinc-700">
            <flux:icon name="chat-bubble-left-right" class="h-10 w-10 text-zinc-300" />
            <flux:heading size="lg" class="mt-3">No testimonials yet</flux:heading>
            <flux:text class="mt-1">Add real customer feedback to show it on the homepage.</flux:text>
        </div>
    @else
        <div class="grid gap-3 md:grid-cols-2">
            @foreach($testimonials as $testimonial)
                <div wire:key="testi-{{ $testimonial->id }}" class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start gap-3">
                        @if($testimonial->photo_url)
                            <img src="{{ $testimonial->photo_url }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover" />
                        @else
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-sm font-bold text-white">{{ $testimonial->initial }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading class="truncate">{{ $testimonial->author_name }}</flux:heading>
                                @if($testimonial->rating)
                                    <flux:badge color="amber" size="sm">{{ $testimonial->rating }}/5</flux:badge>
                                @endif
                                <flux:badge :color="$testimonial->is_active ? 'green' : 'zinc'" size="sm">
                                    {{ $testimonial->is_active ? 'Live' : 'Off' }}
                                </flux:badge>
                            </div>
                            @if($testimonial->author_title)
                                <flux:text size="sm" class="text-zinc-400">{{ $testimonial->author_title }}</flux:text>
                            @endif
                            <flux:text class="mt-2 line-clamp-3">“{{ $testimonial->quote }}”</flux:text>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <flux:button size="sm" variant="outline" wire:click="toggle({{ $testimonial->id }})">
                            {{ $testimonial->is_active ? 'Disable' : 'Enable' }}
                        </flux:button>
                        <flux:button size="sm" variant="outline" wire:click="edit({{ $testimonial->id }})">Edit</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="delete({{ $testimonial->id }})" wire:confirm="Delete this testimonial?">Delete</flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal wire:model.self="showModal" class="md:w-[34rem]">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit testimonial' : 'New testimonial' }}</flux:heading>
                <flux:text class="mt-1">Use the customer's own words, with their permission.</flux:text>
            </div>

            <flux:field>
                <flux:label>Customer name</flux:label>
                <flux:input wire:model="author_name" placeholder="Nur Aisyah" />
                <flux:error name="author_name" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:input wire:model="author_title" placeholder="Ibu kepada 3 anak, Shah Alam" />
                <flux:description>Where they're from or who they are (optional)</flux:description>
                <flux:error name="author_title" />
            </flux:field>

            <flux:field>
                <flux:label>Quote</flux:label>
                <flux:textarea wire:model="quote" rows="4" placeholder="What the customer said…" />
                <flux:error name="quote" />
            </flux:field>

            <flux:field>
                <flux:label>Photo</flux:label>
                <flux:input type="file" wire:model="photo" accept="image/*" />
                <flux:description>Optional — a letter tile is used when there's no photo.</flux:description>
                <flux:error name="photo" />
                @if($photo)
                    <img src="{{ $photo->temporaryUrl() }}" alt="Preview" class="mt-2 h-16 w-16 rounded-full object-cover" />
                @elseif($currentPhoto)
                    <img src="{{ $currentPhoto }}" alt="Current" class="mt-2 h-16 w-16 rounded-full object-cover" />
                @endif
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>Rating</flux:label>
                    <flux:select wire:model="rating" placeholder="None">
                        @foreach([5, 4, 3, 2, 1] as $star)
                            <flux:select.option value="{{ $star }}">{{ $star }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="rating" />
                </flux:field>

                <flux:field>
                    <flux:label>Order</flux:label>
                    <flux:input type="number" wire:model="sort_order" min="0" />
                    <flux:error name="sort_order" />
                </flux:field>

                <flux:field variant="inline" class="sm:mt-7">
                    <flux:checkbox wire:model="is_active" />
                    <flux:label>Active</flux:label>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Add testimonial' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
