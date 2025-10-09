<?php

use App\Models\Certificate;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        //
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function deleteCertificate(int $id): void
    {
        $certificate = Certificate::findOrFail($id);

        // Check if certificate has been issued
        if ($certificate->issues()->count() > 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot delete certificate that has been issued to students.',
            ]);
            return;
        }

        $certificate->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate template deleted successfully.',
        ]);
    }

    public function duplicateCertificate(int $id): void
    {
        $original = Certificate::findOrFail($id);

        $duplicate = Certificate::create([
            'name' => $original->name . ' (Copy)',
            'description' => $original->description,
            'size' => $original->size,
            'orientation' => $original->orientation,
            'width' => $original->width,
            'height' => $original->height,
            'background_image' => $original->background_image,
            'background_color' => $original->background_color,
            'elements' => $original->elements,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate template duplicated successfully.',
        ]);

        $this->redirect(route('certificates.edit', $duplicate));
    }

    public function archiveCertificate(int $id): void
    {
        $certificate = Certificate::findOrFail($id);
        $certificate->archive();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate template archived.',
        ]);
    }

    public function activateCertificate(int $id): void
    {
        $certificate = Certificate::findOrFail($id);
        $certificate->activate();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate template activated.',
        ]);
    }

    public function with(): array
    {
        $query = Certificate::query()
            ->with(['creator', 'issues'])
            ->withCount('issues');

        // Apply search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        $certificates = $query->paginate(10);

        return [
            'certificates' => $certificates,
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Certificate Templates</flux:heading>
            <flux:text class="mt-2">Create and manage certificate templates for courses and classes</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('certificates.create') }}" icon="plus">
            Create Template
        </flux:button>
    </div>

    {{-- Filters --}}
    <div class="mb-6 flex items-center gap-4">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search templates..." icon="magnifying-glass" />
        </div>

        <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
            <option value="all">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="archived">Archived</option>
        </flux:select>
    </div>

    {{-- Certificate List --}}
    <div class="space-y-4">
        @forelse($certificates as $certificate)
            <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <flux:heading size="lg">
                                <a href="{{ route('certificates.edit', $certificate) }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $certificate->name }}
                                </a>
                            </flux:heading>
                            <flux:badge :color="match($certificate->status) {
                                'draft' => 'zinc',
                                'active' => 'green',
                                'archived' => 'yellow',
                                default => 'zinc'
                            }">
                                {{ ucfirst($certificate->status) }}
                            </flux:badge>
                        </div>

                        @if($certificate->description)
                            <flux:text class="mt-2">{{ $certificate->description }}</flux:text>
                        @endif

                        <div class="mt-4 flex items-center gap-6 text-sm text-gray-600 dark:text-gray-400">
                            <div class="flex items-center gap-2">
                                <flux:icon name="document" class="h-4 w-4" />
                                <span>{{ $certificate->formatted_size }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="users" class="h-4 w-4" />
                                <span>{{ $certificate->issues_count }} {{ Str::plural('Issue', $certificate->issues_count) }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="user" class="h-4 w-4" />
                                <span>By {{ $certificate->creator->name }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="calendar" class="h-4 w-4" />
                                <span>{{ $certificate->created_at->format('M d, Y') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="ml-4">
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />

                            <flux:menu>
                                <flux:menu.item href="{{ route('certificates.edit', $certificate) }}" icon="pencil">
                                    Edit
                                </flux:menu.item>

                                <flux:menu.item href="{{ route('certificates.preview', $certificate) }}" icon="eye">
                                    Preview
                                </flux:menu.item>

                                <flux:menu.item href="{{ route('certificates.assignments', $certificate) }}" icon="link">
                                    Assignments
                                </flux:menu.item>

                                <flux:menu.item wire:click="duplicateCertificate({{ $certificate->id }})" icon="document-duplicate">
                                    Duplicate
                                </flux:menu.item>

                                <flux:menu.separator />

                                @if($certificate->status === 'draft' || $certificate->status === 'archived')
                                    <flux:menu.item wire:click="activateCertificate({{ $certificate->id }})" icon="check-circle">
                                        Activate
                                    </flux:menu.item>
                                @endif

                                @if($certificate->status === 'active')
                                    <flux:menu.item wire:click="archiveCertificate({{ $certificate->id }})" icon="archive-box">
                                        Archive
                                    </flux:menu.item>
                                @endif

                                <flux:menu.separator />

                                @if($certificate->issues_count === 0)
                                    <flux:menu.item wire:click="deleteCertificate({{ $certificate->id }})" wire:confirm="Are you sure you want to delete this certificate template?" icon="trash" variant="danger">
                                        Delete
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-200 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-800">
                <flux:icon name="document-text" class="mx-auto h-12 w-12 text-gray-400" />
                <flux:heading size="lg" class="mt-4">No certificate templates found</flux:heading>
                <flux:text class="mt-2">Get started by creating your first certificate template</flux:text>
                <flux:button variant="primary" href="{{ route('certificates.create') }}" class="mt-4" icon="plus">
                    Create Template
                </flux:button>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($certificates->hasPages())
        <div class="mt-6">
            {{ $certificates->links() }}
        </div>
    @endif
</div>
