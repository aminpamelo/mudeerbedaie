<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Platform;
use App\Models\ImportJob;

new class extends Component {
    use WithPagination;

    public $platform_filter = '';
    public $status_filter = '';
    public $search = '';

    public function updatedPlatformFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function getImportsProperty()
    {
        return ImportJob::query()
            ->with(['platform'])
            ->when($this->platform_filter, fn($query) =>
                $query->where('platform_id', $this->platform_filter)
            )
            ->when($this->status_filter, fn($query) =>
                $query->where('status', $this->status_filter)
            )
            ->when($this->search, function ($query) {
                $query->where('file_name', 'like', '%' . $this->search . '%')
                      ->orWhere('notes', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function getPlatformsProperty()
    {
        return Platform::all();
    }

    public function with(): array
    {
        return [
            'imports' => $this->imports,
            'platforms' => $this->platforms,
        ];
    }
}; ?>
<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Import History</flux:heading>
            <flux:text class="mt-2">Track platform order imports and their status</flux:text>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search imports..."
        />

        <flux:select wire:model.live="platform_filter" placeholder="All Platforms">
            <option value="">All Platforms</option>
            @foreach($platforms as $platform)
                <option value="{{ $platform->id }}">{{ $platform->display_name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="status_filter" placeholder="All Status">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
            <option value="cancelled">Cancelled</option>
        </flux:select>

        <flux:button variant="outline" wire:click="$refresh">
            Refresh
        </flux:button>
    </div>

    <!-- Import History Table -->
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            File
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            Platform
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            Progress
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            Created
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white">
                    @forelse($imports as $import)
                        <tr class="hover:bg-zinc-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-zinc-900">{{ $import->file_name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-zinc-900">{{ $import->platform?->name ?? 'Unknown' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge variant="{{
                                    $import->status === 'completed' ? 'green' :
                                    ($import->status === 'failed' ? 'red' :
                                    ($import->status === 'processing' ? 'blue' : 'gray'))
                                }}">
                                    {{ ucfirst($import->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500">
                                {{ $import->successful_rows ?? 0 }} / {{ $import->total_rows ?? 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500">
                                {{ $import->created_at?->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="text-zinc-500">No import history found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($imports->hasPages())
        <div class="mt-6">
            {{ $imports->links() }}
        </div>
    @endif
</div>