<?php

use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterHost = '';
    public string $filterPlatform = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    // View modal
    public bool $showViewModal = false;
    public ?int $viewingSessionId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getSessionsProperty()
    {
        return LiveSession::query()
            ->whereNotNull('uploaded_at')
            ->with(['liveHost', 'platformAccount.platform', 'uploadedBy'])
            ->when($this->search, fn($q) => $q->where(function ($query) {
                $query->where('title', 'like', "%{$this->search}%")
                    ->orWhereHas('liveHost', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->filterHost, fn($q) => $q->where('live_host_id', $this->filterHost))
            ->when($this->filterPlatform, fn($q) => $q->where('platform_account_id', $this->filterPlatform))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('scheduled_start_at', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('scheduled_start_at', '<=', $this->filterDateTo))
            ->orderByDesc('uploaded_at')
            ->paginate(20);
    }

    public function getLiveHostsProperty()
    {
        return User::where('role', 'live_host')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function getPlatformAccountsProperty()
    {
        return PlatformAccount::active()
            ->with('platform')
            ->orderBy('name')
            ->get();
    }

    public function getStatsProperty(): array
    {
        return [
            'totalUploaded' => LiveSession::whereNotNull('uploaded_at')->count(),
            'thisWeek' => LiveSession::whereNotNull('uploaded_at')
                ->whereBetween('uploaded_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'totalHours' => round(LiveSession::whereNotNull('uploaded_at')->sum('duration_minutes') / 60, 1),
            'uniqueHosts' => LiveSession::whereNotNull('uploaded_at')
                ->distinct('live_host_id')
                ->count('live_host_id'),
        ];
    }

    public function openViewModal(int $sessionId): void
    {
        $this->viewingSessionId = $sessionId;
        $this->showViewModal = true;
    }

    public function closeViewModal(): void
    {
        $this->showViewModal = false;
        $this->viewingSessionId = null;
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterHost', 'filterPlatform', 'filterDateFrom', 'filterDateTo']);
    }

    public function exportToCsv()
    {
        $sessions = LiveSession::query()
            ->whereNotNull('uploaded_at')
            ->with(['liveHost', 'platformAccount.platform'])
            ->when($this->filterHost, fn($q) => $q->where('live_host_id', $this->filterHost))
            ->when($this->filterPlatform, fn($q) => $q->where('platform_account_id', $this->filterPlatform))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('scheduled_start_at', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('scheduled_start_at', '<=', $this->filterDateTo))
            ->orderByDesc('uploaded_at')
            ->get();

        $rows = [
            ['Date', 'Host Name', 'Platform', 'Title', 'Start Time', 'End Time', 'Duration (min)', 'Remarks', 'Uploaded At'],
        ];

        foreach ($sessions as $session) {
            $rows[] = [
                $session->scheduled_start_at->format('Y-m-d'),
                $session->liveHost?->name ?? 'N/A',
                $session->platformAccount?->name ?? 'N/A',
                $session->title,
                $session->actual_start_at?->format('H:i') ?? 'N/A',
                $session->actual_end_at?->format('H:i') ?? 'N/A',
                $session->duration_minutes ?? 0,
                $session->remarks ?? '',
                $session->uploaded_at->format('Y-m-d H:i'),
            ];
        }

        $filename = 'uploaded-sessions-' . now()->format('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
?>

<div>
    <x-slot:title>Uploaded Sessions</x-slot:title>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Session Slots (Uploaded)</flux:heading>
            <flux:text class="mt-2">View all uploaded live streaming sessions from hosts</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" wire:click="exportToCsv">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-2" />
                    Export CSV
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-green-100 dark:bg-green-900/30 rounded-xl">
                    <flux:icon name="check-circle" class="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['totalUploaded'] }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Uploaded</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-xl">
                    <flux:icon name="calendar" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['thisWeek'] }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">This Week</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-purple-100 dark:bg-purple-900/30 rounded-xl">
                    <flux:icon name="clock" class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['totalHours'] }}h</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Hours</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-orange-100 dark:bg-orange-900/30 rounded-xl">
                    <flux:icon name="users" class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['uniqueHosts'] }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Active Hosts</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-4 shadow-sm">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <flux:field>
                    <flux:label>Search</flux:label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by title or host name..." />
                </flux:field>
            </div>
            <div class="w-48">
                <flux:field>
                    <flux:label>Host</flux:label>
                    <flux:select wire:model.live="filterHost">
                        <option value="">All Hosts</option>
                        @foreach($this->liveHosts as $host)
                            <option value="{{ $host->id }}">{{ $host->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
            <div class="w-48">
                <flux:field>
                    <flux:label>Platform</flux:label>
                    <flux:select wire:model.live="filterPlatform">
                        <option value="">All Platforms</option>
                        @foreach($this->platformAccounts as $platform)
                            <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
            <div class="w-40">
                <flux:field>
                    <flux:label>Date From</flux:label>
                    <flux:input type="date" wire:model.live="filterDateFrom" />
                </flux:field>
            </div>
            <div class="w-40">
                <flux:field>
                    <flux:label>Date To</flux:label>
                    <flux:input type="date" wire:model.live="filterDateTo" />
                </flux:field>
            </div>
            <flux:button variant="ghost" wire:click="clearFilters">
                Clear
            </flux:button>
        </div>
    </div>

    <!-- Sessions Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden shadow-sm">
        <table class="w-full divide-y divide-gray-200 dark:divide-zinc-700">
            <thead class="bg-gray-50 dark:bg-zinc-900/50">
                <tr>
                    <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Session
                    </th>
                    <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Host
                    </th>
                    <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Platform
                    </th>
                    <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Time
                    </th>
                    <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Duration
                    </th>
                    <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Uploaded
                    </th>
                    <th class="px-4 py-3.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                @forelse($this->sessions as $session)
                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors">
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">
                                @if($session->image_path)
                                    <img
                                        src="{{ Storage::url($session->image_path) }}"
                                        alt="Screenshot"
                                        class="w-14 h-14 object-cover rounded-lg border border-gray-200 dark:border-zinc-600"
                                    />
                                @else
                                    <div class="w-14 h-14 bg-gray-100 dark:bg-zinc-700 rounded-lg flex items-center justify-center border border-gray-200 dark:border-zinc-600">
                                        <flux:icon name="video-camera" class="w-6 h-6 text-gray-400 dark:text-zinc-500" />
                                    </div>
                                @endif
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $session->title }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $session->scheduled_start_at->format('D, M d, Y') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-2">
                                @if($session->liveHost?->host_color)
                                    <span
                                        class="w-3 h-3 rounded-full flex-shrink-0"
                                        style="background-color: {{ $session->liveHost->host_color }};"
                                    ></span>
                                @endif
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $session->liveHost?->name ?? 'N/A' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <flux:badge variant="outline" size="sm">
                                {{ $session->platformAccount?->name ?? 'N/A' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-4">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $session->actual_start_at?->format('g:i A') }} - {{ $session->actual_end_at?->format('g:i A') }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $session->duration_minutes ?? 0 }} min
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $session->uploaded_at->diffForHumans() }}
                            </span>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <flux:button variant="ghost" size="sm" wire:click="openViewModal({{ $session->id }})">
                                View
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="p-4 bg-gray-100 dark:bg-zinc-700 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                                <flux:icon name="document" class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Uploaded Sessions</h3>
                            <p class="text-gray-500 dark:text-gray-400">
                                Uploaded sessions from live hosts will appear here.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($this->sessions->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $this->sessions->links() }}
            </div>
        @endif
    </div>

    <!-- View Modal -->
    <flux:modal wire:model="showViewModal" class="max-w-2xl">
        <div class="p-6">
            @if($viewingSessionId)
                @php
                    $session = \App\Models\LiveSession::with(['liveHost', 'platformAccount.platform', 'uploadedBy'])->find($viewingSessionId);
                @endphp

                @if($session)
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-blue-100 dark:bg-blue-800/50 rounded-lg">
                            <flux:icon name="video-camera" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <flux:heading size="lg">Session Details</flux:heading>
                            <p class="text-sm text-gray-500 dark:text-gray-400">View uploaded session information</p>
                        </div>
                    </div>

                    <!-- Screenshot -->
                    @if($session->image_path)
                        <div class="mb-6">
                            <img
                                src="{{ Storage::url($session->image_path) }}"
                                alt="Session screenshot"
                                class="w-full max-h-72 object-contain rounded-xl bg-gray-100 dark:bg-zinc-700 border border-gray-200 dark:border-zinc-600"
                            />
                        </div>
                    @endif

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-gray-50 dark:bg-zinc-700/50 rounded-lg">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-medium">Title</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white mt-1">{{ $session->title }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 dark:bg-zinc-700/50 rounded-lg">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-medium">Date</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ $session->scheduled_start_at->format('l, M d, Y') }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-gray-50 dark:bg-zinc-700/50 rounded-lg">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-medium">Live Host</p>
                                <div class="flex items-center gap-2 mt-1">
                                    @if($session->liveHost?->host_color)
                                        <span
                                            class="w-3 h-3 rounded-full"
                                            style="background-color: {{ $session->liveHost->host_color }};"
                                        ></span>
                                    @endif
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $session->liveHost?->name ?? 'N/A' }}</span>
                                </div>
                            </div>
                            <div class="p-3 bg-gray-50 dark:bg-zinc-700/50 rounded-lg">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-medium">Platform</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ $session->platformAccount?->name ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4 pt-4 border-t border-gray-200 dark:border-zinc-700">
                            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
                                <p class="text-xs text-blue-600 dark:text-blue-400 uppercase font-medium">Start Time</p>
                                <p class="text-lg font-bold text-blue-900 dark:text-blue-100 mt-1">{{ $session->actual_start_at?->format('g:i A') ?? 'N/A' }}</p>
                            </div>
                            <div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-center">
                                <p class="text-xs text-purple-600 dark:text-purple-400 uppercase font-medium">End Time</p>
                                <p class="text-lg font-bold text-purple-900 dark:text-purple-100 mt-1">{{ $session->actual_end_at?->format('g:i A') ?? 'N/A' }}</p>
                            </div>
                            <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg text-center">
                                <p class="text-xs text-green-600 dark:text-green-400 uppercase font-medium">Duration</p>
                                <p class="text-lg font-bold text-green-900 dark:text-green-100 mt-1">{{ $session->duration_minutes ?? 0 }} min</p>
                            </div>
                        </div>

                        @if($session->remarks)
                            <div class="pt-4 border-t border-gray-200 dark:border-zinc-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-medium mb-2">Remarks</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 p-3 bg-gray-50 dark:bg-zinc-700/50 rounded-lg italic">{{ $session->remarks }}</p>
                            </div>
                        @endif

                        <div class="pt-4 border-t border-gray-200 dark:border-zinc-700 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Uploaded by <span class="font-medium">{{ $session->uploadedBy?->name ?? 'Unknown' }}</span> on {{ $session->uploaded_at->format('M d, Y g:i A') }}
                            </p>
                        </div>
                    </div>
                @endif
            @endif

            <div class="mt-6">
                <flux:button variant="ghost" wire:click="closeViewModal" class="w-full">
                    Close
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
