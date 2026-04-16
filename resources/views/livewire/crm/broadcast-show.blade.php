<?php

use App\Models\Broadcast;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Broadcast $broadcast;
    public $activeTab = 'overview';
    public $logStatusFilter = '';

    public function mount(Broadcast $broadcast): void
    {
        $this->broadcast = $broadcast->load(['audiences']);
    }

    public function with(): array
    {
        $logsQuery = $this->broadcast->logs()->with('student.user');

        if ($this->logStatusFilter) {
            $logsQuery->where('status', $this->logStatusFilter);
        }

        return [
            'logs' => $logsQuery->latest()->paginate(20),
            'sentCount' => $this->broadcast->logs()->where('status', 'sent')->count(),
            'failedCount' => $this->broadcast->logs()->where('status', 'failed')->count(),
            'pendingCount' => $this->broadcast->logs()->where('status', 'pending')->count(),
        ];
    }

    public function setTab($tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:button variant="ghost" size="sm" href="{{ route('crm.broadcasts.index') }}">
                    <div class="flex items-center">
                        <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                        Back
                    </div>
                </flux:button>
                <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $broadcast->name }}</h1>
            </div>
            <div class="flex items-center gap-2 mt-2 ml-[72px]">
                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ match($broadcast->status) {
                    'draft' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300',
                    'scheduled' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                    'sending' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                    'sent' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                    'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                    default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'
                } }}">
                    {{ ucfirst($broadcast->status) }}
                </span>
                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ ucfirst($broadcast->type) }} Broadcast</span>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total Recipients</p>
            <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($broadcast->total_recipients) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sent</p>
            <p class="mt-1 text-xl font-semibold tabular-nums text-emerald-600">{{ number_format($broadcast->total_sent) }}</p>
            @if($broadcast->total_recipients > 0)
                <p class="text-[11px] tabular-nums text-zinc-400">{{ number_format(($broadcast->total_sent / $broadcast->total_recipients) * 100, 1) }}%</p>
            @endif
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Failed</p>
            <p class="mt-1 text-xl font-semibold tabular-nums text-red-500">{{ number_format($broadcast->total_failed) }}</p>
            @if($broadcast->total_recipients > 0 && $broadcast->total_failed > 0)
                <p class="text-[11px] tabular-nums text-zinc-400">{{ number_format(($broadcast->total_failed / $broadcast->total_recipients) * 100, 1) }}%</p>
            @endif
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sent At</p>
            @if($broadcast->sent_at)
                <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $broadcast->sent_at->format('M d, Y') }}</p>
                <p class="text-[11px] tabular-nums text-zinc-400">{{ $broadcast->sent_at->format('g:i a') }}</p>
            @else
                <p class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">Not sent yet</p>
            @endif
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-6">
        <div class="border-b border-zinc-200 dark:border-zinc-700">
            <nav class="-mb-px flex space-x-8">
                <button
                    wire:click="setTab('overview')"
                    class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'overview' ? 'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'border-transparent text-zinc-400 hover:text-zinc-600 hover:border-zinc-300 dark:text-zinc-500 dark:hover:text-zinc-300' }}">
                    Overview
                </button>
                <button
                    wire:click="setTab('content')"
                    class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'content' ? 'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'border-transparent text-zinc-400 hover:text-zinc-600 hover:border-zinc-300 dark:text-zinc-500 dark:hover:text-zinc-300' }}">
                    Content
                </button>
                <button
                    wire:click="setTab('logs')"
                    class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'logs' ? 'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'border-transparent text-zinc-400 hover:text-zinc-600 hover:border-zinc-300 dark:text-zinc-500 dark:hover:text-zinc-300' }}">
                    Delivery Logs
                    @if($failedCount > 0)
                        <span class="ml-1.5 inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold tabular-nums bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400">{{ $failedCount }}</span>
                    @endif
                </button>
            </nav>
        </div>
    </div>

    <!-- Tab Content -->
    @if($activeTab === 'overview')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Broadcast Information -->
            <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Broadcast Information</h3>
                </div>
                <div class="px-5 py-4">
                    <div class="space-y-3">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Name</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->name }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Type</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ ucfirst($broadcast->type) }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Status</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ ucfirst($broadcast->status) }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Created At</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->created_at->format('M d, Y g:i a') }}</p>
                        </div>
                        @if($broadcast->scheduled_at)
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Scheduled At</p>
                                <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->scheduled_at->format('M d, Y g:i a') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Email Settings</h3>
                </div>
                <div class="px-5 py-4">
                    <div class="space-y-3">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">From Name</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->from_name }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">From Email</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->from_email }}</p>
                        </div>
                        @if($broadcast->reply_to_email)
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Reply-To Email</p>
                                <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->reply_to_email }}</p>
                            </div>
                        @endif
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Subject</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->subject }}</p>
                        </div>
                        @if($broadcast->preview_text)
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Preview Text</p>
                                <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->preview_text }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Audiences -->
            <div class="rounded-lg border border-zinc-200 bg-white lg:col-span-2 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Target Audiences ({{ $broadcast->audiences->count() }})</h3>
                </div>
                <div class="px-5 py-4">
                    @if($broadcast->audiences->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($broadcast->audiences as $audience)
                                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $audience->name }}</p>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $audience->students_count ?? 0 }} contacts</p>
                                        </div>
                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ $audience->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">
                                            {{ ucfirst($audience->status) }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No audiences selected</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($activeTab === 'content')
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Email Content</h3>
            </div>
            <div class="px-5 py-4">
                <div class="mb-6">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">Subject Line</p>
                    <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $broadcast->subject }}</p>
                    </div>
                </div>

                @if($broadcast->preview_text)
                    <div class="mb-6">
                        <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">Preview Text</p>
                        <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $broadcast->preview_text }}</p>
                        </div>
                    </div>
                @endif

                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">Email Body</p>
                    <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="prose max-w-none text-sm dark:prose-invert">
                            {!! nl2br(e($broadcast->content)) !!}
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">
                        Note: Merge tags like @{{name}}, @{{email}}, and @{{student_id}} are replaced with actual student data when sent.
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if($activeTab === 'logs')
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Delivery Logs</h3>
                <div class="w-40">
                    <flux:select wire:model.live="logStatusFilter" placeholder="All Statuses">
                        <flux:select.option value="">All Statuses</flux:select.option>
                        <flux:select.option value="sent">Sent ({{ $sentCount }})</flux:select.option>
                        <flux:select.option value="failed">Failed ({{ $failedCount }})</flux:select.option>
                        <flux:select.option value="pending">Pending ({{ $pendingCount }})</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr>
                            <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Recipient</th>
                            <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Email</th>
                            <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Status</th>
                            <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Sent At</th>
                            <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($logs as $log)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $log->student->user->name }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">ID: {{ $log->student->student_id }}</p>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $log->email }}</p>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ match($log->status) {
                                        'sent' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                        'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                        'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                        default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'
                                    } }}">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    @if($log->sent_at)
                                        <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $log->sent_at->format('M d, Y') }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $log->sent_at->format('g:i a') }}</p>
                                    @else
                                        <p class="text-sm text-zinc-400 dark:text-zinc-500">-</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($log->error_message)
                                        <p class="text-xs text-red-600 dark:text-red-400 max-w-xs truncate" title="{{ $log->error_message }}">
                                            {{ $log->error_message }}
                                        </p>
                                    @else
                                        <p class="text-sm text-zinc-400 dark:text-zinc-500">-</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center">
                                    <flux:icon.inbox class="mx-auto h-8 w-8 text-zinc-300 dark:text-zinc-600" />
                                    <p class="mt-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">No logs found</p>
                                    <p class="mt-1 text-[13px] text-zinc-400 dark:text-zinc-500">
                                        @if($logStatusFilter)
                                            Try adjusting your filter.
                                        @else
                                            Logs will appear here once emails are sent.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
