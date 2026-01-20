<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Platform;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $typeFilter = '';
    public $statusFilter = '';
    public $sortBy = 'sort_order';
    public $sortDirection = 'asc';

    public function mount()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedTypeFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function toggleStatus($platformId)
    {
        $platform = Platform::findOrFail($platformId);
        $platform->update(['is_active' => !$platform->is_active]);

        $this->dispatch('platform-updated', [
            'message' => "Platform '{$platform->name}' has been " . ($platform->is_active ? 'activated' : 'deactivated')
        ]);
    }

    public function with()
    {
        $query = Platform::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('display_name', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $platforms = $query->orderBy($this->sortBy, $this->sortDirection)
                          ->paginate(12);

        return [
            'platforms' => $platforms,
            'platformTypes' => ['marketplace', 'social_media', 'custom'],
            'totalPlatforms' => Platform::count(),
            'activePlatforms' => Platform::where('is_active', true)->count(),
            'connectedPlatforms' => Platform::whereHas('accounts', function($q) {
                $q->where('is_active', true);
            })->count(),
        ];
    }
}; ?>

<div>
    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Platform Management</flux:heading>
            <flux:text class="mt-2">Manage e-commerce platforms and marketplaces for order integration</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" icon="plus" href="/admin/platforms/create">
                Add Platform
            </flux:button>
            <flux:button variant="primary" icon="arrow-down-tray">
                Export Data
            </flux:button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg border p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-zinc-600">Total Platforms</flux:text>
                    <flux:heading size="lg">{{ $totalPlatforms }}</flux:heading>
                </div>
                <div class="p-2 bg-blue-100 rounded-lg">
                    <flux:icon name="squares-2x2" class="w-6 h-6 text-blue-600" />
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-zinc-600">Active Platforms</flux:text>
                    <flux:heading size="lg">{{ $activePlatforms }}</flux:heading>
                </div>
                <div class="p-2 bg-green-100 rounded-lg">
                    <flux:icon name="check-circle" class="w-6 h-6 text-green-600" />
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-zinc-600">Connected Accounts</flux:text>
                    <flux:heading size="lg">{{ $connectedPlatforms }}</flux:heading>
                </div>
                <div class="p-2 bg-purple-100 rounded-lg">
                    <flux:icon name="link" class="w-6 h-6 text-purple-600" />
                </div>
            </div>
        </div>
    </div>

    {{-- Filters & Search --}}
    <div class="mb-6 bg-white rounded-lg border p-4">
        <div class="flex flex-col lg:flex-row gap-4">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search platforms by name or description..."
                    icon="magnifying-glass"
                />
            </div>

            <div class="flex gap-3">
                <flux:select wire:model.live="typeFilter" placeholder="All Types">
                    <flux:select.option value="">All Types</flux:select.option>
                    @foreach($platformTypes as $type)
                        <flux:select.option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="statusFilter" placeholder="All Status">
                    <flux:select.option value="">All Status</flux:select.option>
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="inactive">Inactive</flux:select.option>
                </flux:select>

                @if($search || $typeFilter || $statusFilter)
                    <flux:button
                        variant="outline"
                        wire:click="$set('search', ''); $set('typeFilter', ''); $set('statusFilter', '')"
                        icon="x-mark"
                        size="sm"
                    >
                        Clear
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    {{-- Platforms Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse($platforms as $platform)
            <div class="bg-white rounded-lg border overflow-hidden hover:shadow-lg transition-shadow">
                {{-- Platform Header --}}
                <div class="p-4 border-b" style="background: linear-gradient(135deg, {{ $platform->color_primary ?? '#6b7280' }}15 0%, {{ $platform->color_secondary ?? '#9ca3af' }}15 100%);">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center space-x-3">
                            @if($platform->logo_url)
                                <img src="{{ $platform->logo_url }}" alt="{{ $platform->name }}" class="w-10 h-10 rounded-lg">
                            @else
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-lg font-bold"
                                     style="background: {{ $platform->color_primary ?? '#6b7280' }}">
                                    {{ substr($platform->name, 0, 1) }}
                                </div>
                            @endif
                            <div>
                                <flux:heading size="sm">{{ $platform->display_name }}</flux:heading>
                                <flux:text size="xs" class="text-zinc-600">{{ ucfirst(str_replace('_', ' ', $platform->type)) }}</flux:text>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2">
                            @if($platform->is_active)
                                <flux:badge size="sm" color="green">Active</flux:badge>
                            @else
                                <flux:badge size="sm" color="red">Inactive</flux:badge>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Platform Content --}}
                <div class="p-4">
                    <flux:text size="sm" class="text-zinc-600 mb-4 line-clamp-2">
                        {{ $platform->description }}
                    </flux:text>

                    {{-- Features --}}
                    <div class="mb-4">
                        <flux:text size="xs" class="text-zinc-500 mb-2">Features:</flux:text>
                        <div class="flex flex-wrap gap-1">
                            @foreach(($platform->features ?? []) as $feature)
                                <flux:badge size="xs" color="blue">{{ str_replace('_', ' ', $feature) }}</flux:badge>
                            @endforeach
                        </div>
                    </div>

                    {{-- Connection Status --}}
                    <div class="mb-4">
                        @if($platform->isConnected())
                            <div class="flex items-center text-green-600">
                                <flux:icon name="check-circle" class="w-4 h-4 mr-1" />
                                <flux:text size="xs">{{ $platform->accounts()->where('is_active', true)->count() }} account(s) connected</flux:text>
                            </div>
                        @else
                            <div class="flex items-center text-amber-600">
                                <flux:icon name="exclamation-triangle" class="w-4 h-4 mr-1" />
                                <flux:text size="xs">No accounts connected</flux:text>
                            </div>
                        @endif
                    </div>

                    {{-- API Status --}}
                    <div class="mb-4">
                        @if($platform->settings['api_available'] ?? false)
                            <div class="flex items-center text-green-600">
                                <flux:icon name="signal" class="w-4 h-4 mr-1" />
                                <flux:text size="xs">API Integration Available</flux:text>
                            </div>
                        @else
                            <div class="flex items-center text-blue-600">
                                <flux:icon name="clock" class="w-4 h-4 mr-1" />
                                <flux:text size="xs">Manual Mode Only</flux:text>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div class="px-4 py-3 bg-gray-50 border-t">
                    <div class="flex items-center justify-between">
                        <div class="flex space-x-2">
                            <flux:button
                                size="sm"
                                variant="outline"
                                href="/admin/platforms/{{ $platform->slug }}/accounts"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="user-group" class="w-4 h-4 mr-1" />
                                    Accounts
                                </div>
                            </flux:button>

                            <flux:button
                                size="sm"
                                variant="outline"
                                href="/admin/platforms/{{ $platform->slug }}/edit"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="pencil" class="w-4 h-4 mr-1" />
                                    Edit
                                </div>
                            </flux:button>
                        </div>

                        <flux:button
                            size="sm"
                            variant="{{ $platform->is_active ? 'outline' : 'primary' }}"
                            wire:click="toggleStatus({{ $platform->id }})"
                            wire:confirm="Are you sure you want to {{ $platform->is_active ? 'deactivate' : 'activate' }} this platform?"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="{{ $platform->is_active ? 'x-mark' : 'check' }}" class="w-4 h-4 mr-1" />
                                {{ $platform->is_active ? 'Deactivate' : 'Activate' }}
                            </div>
                        </flux:button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <div class="text-center py-12">
                    <flux:icon name="squares-2x2" class="w-12 h-12 text-zinc-400 mx-auto mb-4" />
                    <flux:heading size="lg" class="text-zinc-600 mb-2">No platforms found</flux:heading>
                    <flux:text class="text-zinc-500 mb-4">
                        @if($search || $typeFilter || $statusFilter)
                            Try adjusting your search criteria or filters.
                        @else
                            Get started by adding your first platform.
                        @endif
                    </flux:text>
                    @if(!$search && !$typeFilter && !$statusFilter)
                        <flux:button variant="primary" href="/admin/platforms/create">
                            Add Platform
                        </flux:button>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($platforms->hasPages())
        <div class="mt-6">
            {{ $platforms->links() }}
        </div>
    @endif
</div>
