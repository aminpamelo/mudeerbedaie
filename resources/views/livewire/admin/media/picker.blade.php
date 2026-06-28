<?php

use App\Models\Media;
use App\Models\MediaFolder;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads, WithPagination;

    /** Unique identifier so a page can host several pickers and tell them apart. */
    public string $name = 'default';

    /** Allow selecting more than one item. */
    public bool $multiple = false;

    /** Restrict the picker to a single media type: 'image', 'video' or '' for both. */
    public string $type = '';

    /** Whether to render the built-in trigger button. */
    public bool $showTrigger = true;

    public string $triggerLabel = 'Select Media';

    public string $triggerIcon = 'photo';

    public string $triggerVariant = 'outline';

    public bool $showModal = false;

    public array $selected = [];

    public string $search = '';

    public string $typeFilter = '';

    public string $folderFilter = '';

    // Inline upload
    public array $newFiles = [];

    public function mount(): void
    {
        if ($this->type) {
            $this->typeFilter = $this->type;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFolderFilter(): void
    {
        $this->resetPage();
    }

    public function open(): void
    {
        $this->showModal = true;
    }

    #[On('open-media-picker')]
    public function openFor(?string $name = null): void
    {
        if ($name === null || $name === $this->name) {
            $this->open();
        }
    }

    public function with(): array
    {
        $effectiveType = $this->type ?: $this->typeFilter;

        return [
            'items' => Media::query()
                ->with('folder')
                ->search($this->search)
                ->ofType($effectiveType)
                ->inFolder($this->folderFilter)
                ->latest()
                ->paginate(18),
            'folders' => MediaFolder::query()->ordered()->get(),
        ];
    }

    public function toggleSelect(int $id): void
    {
        if ($this->multiple) {
            if (in_array($id, $this->selected, true)) {
                $this->selected = array_values(array_diff($this->selected, [$id]));
            } else {
                $this->selected[] = $id;
            }

            return;
        }

        $this->selected = [$id];
    }

    public function uploadAndSelect(): void
    {
        $allowed = $this->type === 'video'
            ? 'mimetypes:video/mp4,video/webm,video/quicktime'
            : ($this->type === 'image'
                ? 'mimetypes:image/jpeg,image/png,image/webp,image/gif'
                : 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime');

        $this->validate([
            'newFiles' => 'required|array|min:1',
            'newFiles.*' => 'file|'.$allowed.'|max:20480',
        ]);

        foreach ($this->newFiles as $file) {
            $original = $file->getClientOriginalName();
            $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
            $fileName = time().'_'.Str::random(6).'_'.$sanitized;
            $filePath = $file->storeAs('media', $fileName, 'public');

            $mime = $file->getMimeType() ?? 'application/octet-stream';
            $mediaType = str_starts_with($mime, 'video/') ? 'video' : 'image';

            $width = null;
            $height = null;
            if ($mediaType === 'image') {
                $info = @getimagesize($file->getRealPath());
                if ($info !== false) {
                    $width = $info[0] ?? null;
                    $height = $info[1] ?? null;
                }
            }

            $media = Media::create([
                'title' => Str::headline(pathinfo($original, PATHINFO_FILENAME)),
                'original_filename' => $original,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'disk' => 'public',
                'mime_type' => $mime,
                'type' => $mediaType,
                'file_size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
            ]);

            if ($this->multiple) {
                $this->selected[] = $media->id;
            } else {
                $this->selected = [$media->id];
            }
        }

        $this->reset('newFiles');
        $this->resetPage();
    }

    public function confirm(): void
    {
        $payload = Media::whereIn('id', $this->selected)
            ->get()
            ->map(fn (Media $m) => [
                'id' => $m->id,
                'url' => $m->url,
                'type' => $m->type,
                'title' => $m->title,
                'alt_text' => $m->alt_text,
                'mime_type' => $m->mime_type,
            ])
            ->values()
            ->all();

        $this->dispatch('media-picker:selected', name: $this->name, media: $payload);

        $this->showModal = false;
        $this->selected = [];
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->selected = [];
    }
}; ?>

<div>
    @if($showTrigger)
        <flux:button type="button" wire:click="open" :variant="$triggerVariant" :icon="$triggerIcon">
            {{ $triggerLabel }}
        </flux:button>
    @endif

    <flux:modal wire:model.self="showModal" class="w-full md:w-[760px]">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $multiple ? 'Select Media' : 'Select a File' }}</flux:heading>
                <flux:text class="mt-1">
                    @if($type === 'image')
                        Choose an image from the library or upload a new one.
                    @elseif($type === 'video')
                        Choose a video from the library or upload a new one.
                    @else
                        Choose media from the library or upload new files.
                    @endif
                </flux:text>
            </div>

            {{-- Filters --}}
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Search media..." icon="magnifying-glass" clearable size="sm" />
                </div>
                <flux:select wire:model.live="folderFilter" placeholder="All Folders" size="sm">
                    <flux:select.option value="">All Folders</flux:select.option>
                    <flux:select.option value="none">No folder</flux:select.option>
                    @foreach($folders as $folder)
                        <flux:select.option value="{{ $folder->id }}">{{ $folder->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Inline upload --}}
            <div class="flex items-center gap-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-900">
                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-blue-600 dark:text-blue-400">
                    <flux:icon name="arrow-up-tray" class="h-4 w-4" />
                    <span>Upload new</span>
                    <input type="file" wire:model="newFiles" multiple
                        accept="{{ $type === 'image' ? 'image/*' : ($type === 'video' ? 'video/*' : 'image/*,video/*') }}"
                        class="sr-only">
                </label>
                <div wire:loading wire:target="newFiles" class="flex items-center gap-1.5 text-xs text-gray-500">
                    <flux:icon name="arrow-path" class="h-3.5 w-3.5 animate-spin" /> Uploading…
                </div>
                @if(count($newFiles) > 0)
                    <flux:button wire:click="uploadAndSelect" size="xs" variant="primary" wire:loading.attr="disabled" wire:target="uploadAndSelect">
                        Save {{ count($newFiles) }} file(s)
                    </flux:button>
                @endif
                <span class="ml-auto text-xs text-gray-400">Max 20MB each</span>
            </div>
            @error('newFiles.*') <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text> @enderror

            {{-- Grid --}}
            <div class="max-h-[44vh] overflow-y-auto" wire:loading.class.delay="opacity-60">
                @if($items->count() > 0)
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                        @foreach($items as $m)
                            @php($isSel = in_array($m->id, $selected, true))
                            <button type="button" wire:key="pick-{{ $m->id }}" wire:click="toggleSelect({{ $m->id }})"
                                class="group relative aspect-square overflow-hidden rounded-lg border-2 bg-gray-100 transition dark:bg-zinc-900 {{ $isSel ? 'border-blue-500 ring-2 ring-blue-500/40' : 'border-transparent hover:border-gray-300 dark:hover:border-zinc-600' }}">
                                @if($m->isImage())
                                    <img src="{{ $m->url }}" alt="{{ $m->alt_text ?? $m->title }}" loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center bg-zinc-900">
                                        <flux:icon name="film" class="h-6 w-6 text-zinc-500" />
                                    </div>
                                @endif
                                @if($isSel)
                                    <span class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-blue-500 text-white">
                                        <flux:icon name="check" class="h-3.5 w-3.5" />
                                    </span>
                                @endif
                                <span class="absolute inset-x-0 bottom-0 truncate bg-black/50 px-1 py-0.5 text-left text-[10px] text-white">
                                    {{ $m->title ?? $m->original_filename }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                        <flux:icon name="photo" class="mx-auto mb-2 h-8 w-8 text-gray-300 dark:text-zinc-600" />
                        No media found. Upload a file to get started.
                    </div>
                @endif
            </div>

            @if($items->hasPages())
                <div>{{ $items->links() }}</div>
            @endif

            <div class="flex items-center justify-between gap-2 border-t border-gray-200 pt-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ count($selected) }} selected</flux:text>
                <div class="flex gap-2">
                    <flux:button wire:click="cancel" variant="ghost">Cancel</flux:button>
                    <flux:button wire:click="confirm" variant="primary" icon="check" :disabled="count($selected) === 0">Use Selection</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
