<?php

use App\Models\Media;
use App\Models\MediaFolder;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new
#[Title('Media Library')]
class extends Component {
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $folderFilter = '';

    // Upload modal
    public bool $showUploadModal = false;

    public array $newFiles = [];

    public ?string $uploadFolderId = null;

    // Edit modal
    public bool $showEditModal = false;

    public ?int $editingId = null;

    public string $editTitle = '';

    public string $editAlt = '';

    public string $editTags = '';

    public ?string $editFolderId = null;

    // Preview modal
    public bool $showPreviewModal = false;

    public ?int $previewId = null;

    // Folder modal
    public bool $showFolderModal = false;

    public string $newFolderName = '';

    public string $flash = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingFolderFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'media' => Media::query()
                ->with('folder')
                ->search($this->search)
                ->ofType($this->typeFilter)
                ->inFolder($this->folderFilter)
                ->latest()
                ->paginate(24),
            'folders' => MediaFolder::query()->withCount('media')->ordered()->get(),
            'stats' => [
                'total' => Media::count(),
                'images' => Media::images()->count(),
                'videos' => Media::videos()->count(),
                'storage' => $this->formatBytes((int) Media::sum('file_size')),
            ],
            'previewMedia' => $this->previewId ? Media::with(['folder', 'uploader'])->find($this->previewId) : null,
        ];
    }

    public function uploadRules(): array
    {
        return [
            'newFiles' => 'required|array|min:1',
            'newFiles.*' => 'file|mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime|max:20480',
            'uploadFolderId' => 'nullable|exists:media_folders,id',
        ];
    }

    public function removeNewFile(int $index): void
    {
        unset($this->newFiles[$index]);
        $this->newFiles = array_values($this->newFiles);
    }

    public function save(): void
    {
        $this->validate($this->uploadRules(), [
            'newFiles.required' => 'Please choose at least one image or video.',
            'newFiles.*.mimetypes' => 'Only images (JPG, PNG, WEBP, GIF) and videos (MP4, WEBM, MOV) are allowed.',
            'newFiles.*.max' => 'Each file must be 20MB or smaller.',
        ]);

        $count = 0;

        foreach ($this->newFiles as $file) {
            $original = $file->getClientOriginalName();
            $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
            $fileName = time().'_'.Str::random(6).'_'.$sanitized;
            $filePath = $file->storeAs('media', $fileName, 'public');

            $mime = $file->getMimeType() ?? 'application/octet-stream';
            $type = str_starts_with($mime, 'video/') ? 'video' : 'image';

            $width = null;
            $height = null;

            if ($type === 'image') {
                $info = @getimagesize($file->getRealPath());
                if ($info !== false) {
                    $width = $info[0] ?? null;
                    $height = $info[1] ?? null;
                }
            }

            Media::create([
                'folder_id' => $this->uploadFolderId ?: null,
                'title' => Str::headline(pathinfo($original, PATHINFO_FILENAME)),
                'original_filename' => $original,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'disk' => 'public',
                'mime_type' => $mime,
                'type' => $type,
                'file_size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
            ]);

            $count++;
        }

        $this->reset(['newFiles', 'uploadFolderId']);
        $this->showUploadModal = false;
        $this->resetPage();
        $this->flash = $count.' '.Str::plural('file', $count).' uploaded successfully.';
    }

    public function openEdit(Media $media): void
    {
        $this->editingId = $media->id;
        $this->editTitle = (string) $media->title;
        $this->editAlt = (string) $media->alt_text;
        $this->editTags = implode(', ', $media->tags ?? []);
        $this->editFolderId = $media->folder_id ? (string) $media->folder_id : null;
        $this->showPreviewModal = false;
        $this->showEditModal = true;
    }

    public function update(): void
    {
        $this->validate([
            'editTitle' => 'nullable|string|max:255',
            'editAlt' => 'nullable|string|max:255',
            'editTags' => 'nullable|string|max:500',
            'editFolderId' => 'nullable|exists:media_folders,id',
        ]);

        $media = Media::findOrFail($this->editingId);

        $tags = collect(explode(',', $this->editTags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $media->update([
            'title' => $this->editTitle ?: null,
            'alt_text' => $this->editAlt ?: null,
            'tags' => $tags ?: null,
            'folder_id' => $this->editFolderId ?: null,
        ]);

        $this->showEditModal = false;
        $this->flash = 'Media details updated.';
    }

    public function openPreview(Media $media): void
    {
        $this->previewId = $media->id;
        $this->showPreviewModal = true;
    }

    public function delete(Media $media): void
    {
        $media->delete();

        if ($this->previewId === $media->id) {
            $this->showPreviewModal = false;
            $this->previewId = null;
        }

        $this->resetPage();
        $this->flash = 'Media deleted.';
    }

    public function createFolder(): void
    {
        $this->validate([
            'newFolderName' => 'required|string|max:255',
        ]);

        MediaFolder::create(['name' => trim($this->newFolderName)]);

        $this->newFolderName = '';
        $this->flash = 'Folder created.';
    }

    public function deleteFolder(MediaFolder $folder): void
    {
        $folder->delete();

        if ($this->folderFilter === (string) $folder->id) {
            $this->folderFilter = '';
        }

        $this->resetPage();
        $this->flash = 'Folder deleted. Its media were moved to "No folder".';
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'folderFilter']);
        $this->resetPage();
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}; ?>

<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">Media Library</flux:heading>
            <flux:text class="mt-1">Upload, organise and reuse images and videos across the system</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="$set('showFolderModal', true)" variant="outline" icon="folder">
                Manage Folders
            </flux:button>
            <flux:button wire:click="$set('showUploadModal', true)" variant="primary" icon="arrow-up-tray">
                Upload Media
            </flux:button>
        </div>
    </div>

    {{-- Flash --}}
    @if($flash)
        <div wire:transition class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
            <div class="flex items-center gap-2">
                <flux:icon name="check-circle" class="h-5 w-5" />
                {{ $flash }}
            </div>
            <button type="button" wire:click="$set('flash', '')" class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-400">
                <flux:icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>
    @endif

    {{-- Summary stats --}}
    @php
        $cards = [
            ['label' => 'Total Files', 'value' => number_format($stats['total']), 'icon' => 'photo', 'chip' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'],
            ['label' => 'Images', 'value' => number_format($stats['images']), 'icon' => 'photo', 'chip' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'],
            ['label' => 'Videos', 'value' => number_format($stats['videos']), 'icon' => 'video-camera', 'chip' => 'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400'],
            ['label' => 'Storage Used', 'value' => $stats['storage'], 'icon' => 'circle-stack', 'chip' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'],
        ];
    @endphp
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach($cards as $card)
            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $card['chip'] }}">
                    <flux:icon :name="$card['icon']" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $card['value'] }}</div>
                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Folder strip --}}
    <div class="mb-4 flex items-center gap-2 overflow-x-auto pb-1">
        <flux:button wire:click="$set('folderFilter', '')" size="sm" :variant="$folderFilter === '' ? 'primary' : 'ghost'" icon="squares-2x2">
            All
        </flux:button>
        <flux:button wire:click="$set('folderFilter', 'none')" size="sm" :variant="$folderFilter === 'none' ? 'primary' : 'ghost'" icon="inbox">
            No folder
        </flux:button>
        @foreach($folders as $folder)
            <flux:button wire:key="folder-pill-{{ $folder->id }}" wire:click="$set('folderFilter', '{{ $folder->id }}')" size="sm" :variant="$folderFilter === (string) $folder->id ? 'primary' : 'ghost'" icon="folder">
                {{ $folder->name }} ({{ $folder->media_count }})
            </flux:button>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by title, filename or tag..." icon="magnifying-glass" clearable />
            </div>
            <flux:select wire:model.live="typeFilter" placeholder="All Types">
                <flux:select.option value="">All Types</flux:select.option>
                <flux:select.option value="image">Images</flux:select.option>
                <flux:select.option value="video">Videos</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="folderFilter" placeholder="All Folders">
                <flux:select.option value="">All Folders</flux:select.option>
                <flux:select.option value="none">No folder</flux:select.option>
                @foreach($folders as $folder)
                    <flux:select.option value="{{ $folder->id }}">{{ $folder->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Result count + clear --}}
    <div class="mb-3 flex items-center justify-between gap-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @if($media->total() > 0)
                Showing
                <span class="font-medium tabular-nums text-gray-700 dark:text-gray-300">{{ $media->firstItem() }}–{{ $media->lastItem() }}</span>
                of
                <span class="font-medium tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($media->total()) }}</span>
                {{ Str::plural('file', $media->total()) }}
            @else
                No media matches your filters
            @endif
        </p>
        @if($search || $typeFilter || $folderFilter)
            <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark">Clear filters</flux:button>
        @endif
    </div>

    {{-- Grid --}}
    <div wire:loading.class.delay="opacity-60">
        @if($media->count() > 0)
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @foreach($media as $m)
                    <div wire:key="media-{{ $m->id }}" class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800">
                        <button type="button" wire:click="openPreview({{ $m->id }})" class="block aspect-square w-full overflow-hidden bg-gray-100 dark:bg-zinc-900">
                            @if($m->isImage())
                                <img src="{{ $m->url }}" alt="{{ $m->alt_text ?? $m->title }}" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-105">
                            @else
                                <div class="relative flex h-full w-full items-center justify-center bg-zinc-900">
                                    <flux:icon name="film" class="h-10 w-10 text-zinc-600" />
                                    <span class="absolute inset-0 flex items-center justify-center">
                                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white/15 backdrop-blur">
                                            <flux:icon name="play" class="h-6 w-6 text-white" />
                                        </span>
                                    </span>
                                </div>
                            @endif
                        </button>

                        {{-- Type badge --}}
                        <span class="pointer-events-none absolute left-2 top-2 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $m->isVideo() ? 'bg-purple-600/90 text-white' : 'bg-blue-600/90 text-white' }}">
                            <flux:icon :name="$m->isVideo() ? 'video-camera' : 'photo'" class="h-3 w-3" />
                            {{ $m->type }}
                        </span>

                        {{-- Hover actions --}}
                        <div class="absolute right-2 top-2 flex gap-1 opacity-0 transition group-hover:opacity-100">
                            <button type="button"
                                x-data="{ copied: false }"
                                data-url="{{ $m->url }}"
                                @click.stop="navigator.clipboard.writeText($el.dataset.url); copied = true; setTimeout(() => copied = false, 1500)"
                                class="flex h-7 w-7 items-center justify-center rounded-md bg-white/90 text-gray-700 shadow hover:bg-white dark:bg-zinc-700/90 dark:text-gray-200"
                                title="Copy URL">
                                <flux:icon name="clipboard-document" class="h-4 w-4" x-show="!copied" />
                                <flux:icon name="check" class="h-4 w-4 text-emerald-600" x-show="copied" x-cloak />
                            </button>
                            <button type="button" wire:click="openEdit({{ $m->id }})"
                                class="flex h-7 w-7 items-center justify-center rounded-md bg-white/90 text-gray-700 shadow hover:bg-white dark:bg-zinc-700/90 dark:text-gray-200"
                                title="Edit">
                                <flux:icon name="pencil" class="h-4 w-4" />
                            </button>
                            <button type="button" wire:click="delete({{ $m->id }})" wire:confirm="Delete this file permanently? This cannot be undone."
                                class="flex h-7 w-7 items-center justify-center rounded-md bg-white/90 text-red-600 shadow hover:bg-white dark:bg-zinc-700/90 dark:text-red-400"
                                title="Delete">
                                <flux:icon name="trash" class="h-4 w-4" />
                            </button>
                        </div>

                        {{-- Caption --}}
                        <div class="p-2.5">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100" title="{{ $m->title ?? $m->original_filename }}">
                                {{ $m->title ?? $m->original_filename }}
                            </p>
                            <div class="mt-0.5 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                                <span class="tabular-nums">{{ $m->formatted_size }}</span>
                                @if($m->folder)
                                    <span class="inline-flex max-w-[60%] items-center gap-1 truncate">
                                        <flux:icon name="folder" class="h-3 w-3 shrink-0" />
                                        <span class="truncate">{{ $m->folder->name }}</span>
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-800">
                <flux:icon name="photo" class="mx-auto h-12 w-12 text-gray-300 dark:text-zinc-600" />
                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-gray-100">No media found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($search || $typeFilter || $folderFilter)
                        Try adjusting your search or filters.
                    @else
                        Get started by uploading your first image or video.
                    @endif
                </p>
                <div class="mt-6 flex items-center justify-center gap-2">
                    @if($search || $typeFilter || $folderFilter)
                        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">Clear filters</flux:button>
                    @endif
                    <flux:button wire:click="$set('showUploadModal', true)" variant="primary" icon="arrow-up-tray">Upload Media</flux:button>
                </div>
            </div>
        @endif
    </div>

    {{-- Pagination --}}
    @if($media->hasPages())
        <div class="mt-6">
            {{ $media->links() }}
        </div>
    @endif

    {{-- ===================== Upload Modal ===================== --}}
    <flux:modal wire:model.self="showUploadModal" class="w-full md:w-[640px]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Upload Media</flux:heading>
                <flux:text class="mt-1">Images up to 5MB, videos up to 20MB. You can select multiple files.</flux:text>
            </div>

            <flux:field>
                <flux:label>Folder (optional)</flux:label>
                <flux:select wire:model="uploadFolderId" placeholder="No folder">
                    <flux:select.option value="">No folder</flux:select.option>
                    @foreach($folders as $folder)
                        <flux:select.option value="{{ $folder->id }}">{{ $folder->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            {{-- Dropzone --}}
            <div>
                <label for="media-files" class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center transition hover:border-blue-400 hover:bg-blue-50/50 dark:border-zinc-600 dark:bg-zinc-900 dark:hover:border-blue-500 dark:hover:bg-zinc-800">
                    <flux:icon name="cloud-arrow-up" class="h-10 w-10 text-gray-400 dark:text-zinc-500" />
                    <span class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">Click to browse or drag files here</span>
                    <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">JPG, PNG, WEBP, GIF, MP4, WEBM, MOV</span>
                    <input id="media-files" type="file" wire:model="newFiles" multiple accept="image/*,video/*" class="sr-only">
                </label>

                <div wire:loading wire:target="newFiles" class="mt-2 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" />
                    Uploading…
                </div>

                @error('newFiles') <flux:text class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text> @enderror
                @error('newFiles.*') <flux:text class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text> @enderror
            </div>

            {{-- Selected previews --}}
            @if(count($newFiles) > 0)
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                    @foreach($newFiles as $index => $file)
                        <div wire:key="newfile-{{ $index }}" class="group relative aspect-square overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-zinc-700 dark:bg-zinc-900">
                            @if(str_starts_with($file->getMimeType(), 'image/'))
                                <img src="{{ $file->temporaryUrl() }}" class="h-full w-full object-cover" alt="preview">
                            @else
                                <div class="flex h-full w-full flex-col items-center justify-center gap-1 p-2 text-center">
                                    <flux:icon name="film" class="h-7 w-7 text-zinc-500" />
                                    <span class="line-clamp-2 text-[10px] text-gray-500 dark:text-gray-400">{{ $file->getClientOriginalName() }}</span>
                                </div>
                            @endif
                            <button type="button" wire:click="removeNewFile({{ $index }})"
                                class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition group-hover:opacity-100 hover:bg-red-600">
                                <flux:icon name="x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="save" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save,newFiles" :disabled="count($newFiles) === 0">
                    <span wire:loading.remove wire:target="save">Upload {{ count($newFiles) > 0 ? '('.count($newFiles).')' : '' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ===================== Edit Modal ===================== --}}
    <flux:modal wire:model.self="showEditModal" class="w-full md:w-[480px]">
        <div class="space-y-5">
            <flux:heading size="lg">Edit Media Details</flux:heading>

            <flux:field>
                <flux:label>Title</flux:label>
                <flux:input wire:model="editTitle" placeholder="Descriptive title" />
                <flux:error name="editTitle" />
            </flux:field>

            <flux:field>
                <flux:label>Alt text</flux:label>
                <flux:input wire:model="editAlt" placeholder="Accessibility description" />
                <flux:error name="editAlt" />
            </flux:field>

            <flux:field>
                <flux:label>Tags</flux:label>
                <flux:input wire:model="editTags" placeholder="banner, hero, promo" />
                <flux:text class="mt-1 text-xs">Separate tags with commas.</flux:text>
                <flux:error name="editTags" />
            </flux:field>

            <flux:field>
                <flux:label>Folder</flux:label>
                <flux:select wire:model="editFolderId" placeholder="No folder">
                    <flux:select.option value="">No folder</flux:select.option>
                    @foreach($folders as $folder)
                        <flux:select.option value="{{ $folder->id }}">{{ $folder->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="editFolderId" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="update" variant="primary" icon="check">Save changes</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ===================== Preview Modal ===================== --}}
    <flux:modal wire:model.self="showPreviewModal" class="w-full md:w-[760px]">
        @if($previewMedia)
            <div class="space-y-5">
                <div class="overflow-hidden rounded-xl bg-zinc-900">
                    @if($previewMedia->isImage())
                        <img src="{{ $previewMedia->url }}" alt="{{ $previewMedia->alt_text ?? $previewMedia->title }}" class="mx-auto max-h-[60vh] w-auto object-contain">
                    @else
                        <video src="{{ $previewMedia->url }}" controls class="mx-auto max-h-[60vh] w-full" preload="metadata"></video>
                    @endif
                </div>

                <div>
                    <flux:heading size="lg" class="break-words">{{ $previewMedia->title ?? $previewMedia->original_filename }}</flux:heading>
                    @if($previewMedia->tags)
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($previewMedia->tags as $tag)
                                <flux:badge size="sm" variant="outline">{{ $tag }}</flux:badge>
                            @endforeach
                        </div>
                    @endif
                </div>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">File name</dt>
                        <dd class="break-all font-medium text-gray-900 dark:text-gray-100">{{ $previewMedia->original_filename }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Type</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $previewMedia->mime_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Size</dt>
                        <dd class="font-medium tabular-nums text-gray-900 dark:text-gray-100">{{ $previewMedia->formatted_size }}</dd>
                    </div>
                    @if($previewMedia->width && $previewMedia->height)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Dimensions</dt>
                            <dd class="font-medium tabular-nums text-gray-900 dark:text-gray-100">{{ $previewMedia->width }} × {{ $previewMedia->height }} px</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Folder</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $previewMedia->folder?->name ?? 'No folder' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Uploaded</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $previewMedia->created_at->format('M d, Y g:i A') }}</dd>
                    </div>
                </dl>

                <div class="flex items-center gap-2">
                    <flux:input readonly value="{{ $previewMedia->url }}" class="flex-1" />
                    <flux:button
                        x-data="{ copied: false }"
                        data-url="{{ $previewMedia->url }}"
                        x-on:click="navigator.clipboard.writeText($el.dataset.url); copied = true; setTimeout(() => copied = false, 1500)"
                        variant="outline"
                        icon="clipboard-document">
                        <span x-show="!copied">Copy</span>
                        <span x-show="copied" x-cloak>Copied!</span>
                    </flux:button>
                    <flux:button :href="$previewMedia->url" target="_blank" variant="ghost" icon="arrow-top-right-on-square">Open</flux:button>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-zinc-700">
                    <flux:button wire:click="delete({{ $previewMedia->id }})" wire:confirm="Delete this file permanently? This cannot be undone." variant="danger" icon="trash">Delete</flux:button>
                    <flux:button wire:click="openEdit({{ $previewMedia->id }})" variant="primary" icon="pencil">Edit Details</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- ===================== Folder Modal ===================== --}}
    <flux:modal wire:model.self="showFolderModal" class="w-full md:w-[480px]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Manage Folders</flux:heading>
                <flux:text class="mt-1">Group media into folders. Deleting a folder keeps its media (moved to “No folder”).</flux:text>
            </div>

            <form wire:submit="createFolder" class="flex items-end gap-2">
                <flux:field class="flex-1">
                    <flux:label>New folder</flux:label>
                    <flux:input wire:model="newFolderName" placeholder="e.g. Campaign Banners" />
                    <flux:error name="newFolderName" />
                </flux:field>
                <flux:button type="submit" variant="primary" icon="plus">Add</flux:button>
            </form>

            <div class="divide-y divide-gray-200 rounded-xl border border-gray-200 dark:divide-zinc-700 dark:border-zinc-700">
                @forelse($folders as $folder)
                    <div wire:key="folder-row-{{ $folder->id }}" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <flux:icon name="folder" class="h-5 w-5 shrink-0 text-amber-500" />
                            <span class="truncate font-medium text-gray-900 dark:text-gray-100">{{ $folder->name }}</span>
                            <flux:badge size="sm" variant="outline">{{ $folder->media_count }}</flux:badge>
                        </div>
                        <flux:button wire:click="deleteFolder({{ $folder->id }})" wire:confirm="Delete this folder? Its media will be moved to “No folder”." variant="ghost" size="sm" icon="trash" square aria-label="Delete {{ $folder->name }}" />
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No folders yet.</div>
                @endforelse
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">Done</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
