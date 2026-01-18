<?php

use App\Models\ImpersonationLog;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';

    public $dateFrom = '';

    public $dateTo = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingDateFrom()
    {
        $this->resetPage();
    }

    public function updatingDateTo()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function getLogsProperty()
    {
        return ImpersonationLog::query()
            ->with(['impersonator', 'impersonated'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('impersonator', function ($sub) {
                        $sub->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    })->orWhereHas('impersonated', function ($sub) {
                        $sub->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    });
                });
            })
            ->when($this->dateFrom, fn ($q) => $q->whereDate('started_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('started_at', '<=', $this->dateTo))
            ->orderBy('started_at', 'desc')
            ->paginate(20);
    }
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Impersonation Logs</flux:heading>
            <flux:text class="mt-2">Audit trail of all user impersonation sessions</flux:text>
        </div>
        <flux:button variant="ghost" href="{{ route('users.index') }}" wire:navigate>
            Back to Users
        </flux:button>
    </div>

    <!-- Filters -->
    <flux:card class="mb-6">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by user name or email..."
                        icon="magnifying-glass"
                    />
                </div>
                <div>
                    <flux:input
                        type="date"
                        wire:model.live="dateFrom"
                        label="From Date"
                    />
                </div>
                <div>
                    <flux:input
                        type="date"
                        wire:model.live="dateTo"
                        label="To Date"
                    />
                </div>
                <div class="flex items-end">
                    <flux:button variant="ghost" wire:click="clearFilters">
                        Clear Filters
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:card>

    <!-- Logs Table -->
    <flux:card>
        <div class="overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Impersonator
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Impersonated
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Started
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ended
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Duration
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            IP Address
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($this->logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 flex-shrink-0">
                                        <div class="h-8 w-8 rounded-full bg-purple-100 flex items-center justify-center">
                                            <span class="text-xs font-medium text-purple-700">
                                                {{ $log->impersonator?->initials() }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">{{ $log->impersonator?->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $log->impersonator?->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 flex-shrink-0">
                                        <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                            <span class="text-xs font-medium text-blue-700">
                                                {{ $log->impersonated?->initials() }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">{{ $log->impersonated?->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $log->impersonated?->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div>{{ $log->started_at->format('M d, Y') }}</div>
                                <div class="text-xs">{{ $log->started_at->format('H:i:s') }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                @if($log->ended_at)
                                    <div>{{ $log->ended_at->format('M d, Y') }}</div>
                                    <div class="text-xs">{{ $log->ended_at->format('H:i:s') }}</div>
                                @else
                                    <flux:badge color="amber">Active</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $log->duration }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $log->ip_address ?? 'N/A' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="text-gray-500">
                                    <flux:icon name="eye-slash" class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                                    <p class="text-lg font-medium">No impersonation logs found</p>
                                    <p class="text-sm">Impersonation activity will appear here</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->logs->hasPages())
            <div class="px-6 py-3 border-t border-gray-200">
                {{ $this->logs->links() }}
            </div>
        @endif
    </flux:card>
</div>
