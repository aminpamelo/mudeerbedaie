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
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:button variant="ghost" size="sm" href="{{ route('crm.broadcasts.index') }}">
                    <div class="flex items-center">
                        <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                        Back
                    </div>
                </flux:button>
                <flux:heading size="xl">{{ $broadcast->name }}</flux:heading>
            </div>
            <div class="flex items-center gap-2 mt-2">
                <flux:badge size="sm" :class="match($broadcast->status) {
                    'draft' => 'badge-gray',
                    'scheduled' => 'badge-blue',
                    'sending' => 'badge-yellow',
                    'sent' => 'badge-green',
                    'failed' => 'badge-red',
                    default => 'badge-gray'
                }">
                    {{ ucfirst($broadcast->status) }}
                </flux:badge>
                <flux:text class="text-sm text-gray-600">{{ ucfirst($broadcast->type) }} Broadcast</flux:text>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:card>
            <div class="p-4">
                <flux:text class="text-sm text-gray-600">Total Recipients</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format($broadcast->total_recipients) }}</flux:heading>
            </div>
        </flux:card>
        <flux:card>
            <div class="p-4">
                <flux:text class="text-sm text-gray-600">Sent</flux:text>
                <flux:heading size="lg" class="mt-1 text-green-600">{{ number_format($broadcast->total_sent) }}</flux:heading>
                @if($broadcast->total_recipients > 0)
                    <flux:text class="text-xs text-gray-500">{{ number_format(($broadcast->total_sent / $broadcast->total_recipients) * 100, 1) }}%</flux:text>
                @endif
            </div>
        </flux:card>
        <flux:card>
            <div class="p-4">
                <flux:text class="text-sm text-gray-600">Failed</flux:text>
                <flux:heading size="lg" class="mt-1 text-red-600">{{ number_format($broadcast->total_failed) }}</flux:heading>
                @if($broadcast->total_recipients > 0 && $broadcast->total_failed > 0)
                    <flux:text class="text-xs text-gray-500">{{ number_format(($broadcast->total_failed / $broadcast->total_recipients) * 100, 1) }}%</flux:text>
                @endif
            </div>
        </flux:card>
        <flux:card>
            <div class="p-4">
                <flux:text class="text-sm text-gray-600">Sent At</flux:text>
                @if($broadcast->sent_at)
                    <flux:heading size="base" class="mt-1">{{ $broadcast->sent_at->format('M d, Y') }}</flux:heading>
                    <flux:text class="text-xs text-gray-500">{{ $broadcast->sent_at->format('g:i a') }}</flux:text>
                @else
                    <flux:text class="text-sm text-gray-400 mt-1">Not sent yet</flux:text>
                @endif
            </div>
        </flux:card>
    </div>

    <!-- Tabs -->
    <div class="mb-6">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <button
                    wire:click="setTab('overview')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Overview
                </button>
                <button
                    wire:click="setTab('content')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'content' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Content
                </button>
                <button
                    wire:click="setTab('logs')"
                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'logs' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Delivery Logs
                    @if($failedCount > 0)
                        <flux:badge size="sm" class="ml-2 badge-red">{{ $failedCount }}</flux:badge>
                    @endif
                </button>
            </nav>
        </div>
    </div>

    <!-- Tab Content -->
    @if($activeTab === 'overview')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Broadcast Information -->
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Broadcast Information</flux:heading>
                    <div class="space-y-3">
                        <div>
                            <flux:text class="text-xs text-gray-600">Name</flux:text>
                            <flux:text class="font-medium">{{ $broadcast->name }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-xs text-gray-600">Type</flux:text>
                            <flux:text class="font-medium">{{ ucfirst($broadcast->type) }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-xs text-gray-600">Status</flux:text>
                            <flux:text class="font-medium">{{ ucfirst($broadcast->status) }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-xs text-gray-600">Created At</flux:text>
                            <flux:text class="font-medium">{{ $broadcast->created_at->format('M d, Y g:i a') }}</flux:text>
                        </div>
                        @if($broadcast->scheduled_at)
                            <div>
                                <flux:text class="text-xs text-gray-600">Scheduled At</flux:text>
                                <flux:text class="font-medium">{{ $broadcast->scheduled_at->format('M d, Y g:i a') }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            </flux:card>

            <!-- Email Settings -->
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Email Settings</flux:heading>
                    <div class="space-y-3">
                        <div>
                            <flux:text class="text-xs text-gray-600">From Name</flux:text>
                            <flux:text class="font-medium">{{ $broadcast->from_name }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-xs text-gray-600">From Email</flux:text>
                            <flux:text class="font-medium">{{ $broadcast->from_email }}</flux:text>
                        </div>
                        @if($broadcast->reply_to_email)
                            <div>
                                <flux:text class="text-xs text-gray-600">Reply-To Email</flux:text>
                                <flux:text class="font-medium">{{ $broadcast->reply_to_email }}</flux:text>
                            </div>
                        @endif
                        <div>
                            <flux:text class="text-xs text-gray-600">Subject</flux:text>
                            <flux:text class="font-medium">{{ $broadcast->subject }}</flux:text>
                        </div>
                        @if($broadcast->preview_text)
                            <div>
                                <flux:text class="text-xs text-gray-600">Preview Text</flux:text>
                                <flux:text class="font-medium">{{ $broadcast->preview_text }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            </flux:card>

            <!-- Audiences -->
            <flux:card class="lg:col-span-2">
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Target Audiences ({{ $broadcast->audiences->count() }})</flux:heading>
                    @if($broadcast->audiences->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($broadcast->audiences as $audience)
                                <div class="p-3 border border-gray-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $audience->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $audience->students_count ?? 0 }} contacts</div>
                                        </div>
                                        <flux:badge size="sm" :class="$audience->status === 'active' ? 'badge-green' : 'badge-gray'">
                                            {{ ucfirst($audience->status) }}
                                        </flux:badge>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <flux:text class="text-gray-500">No audiences selected</flux:text>
                    @endif
                </div>
            </flux:card>
        </div>
    @endif

    @if($activeTab === 'content')
        <flux:card>
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">Email Content</flux:heading>

                <div class="mb-6">
                    <flux:text class="text-xs text-gray-600 mb-2">Subject Line</flux:text>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <flux:text class="font-medium">{{ $broadcast->subject }}</flux:text>
                    </div>
                </div>

                @if($broadcast->preview_text)
                    <div class="mb-6">
                        <flux:text class="text-xs text-gray-600 mb-2">Preview Text</flux:text>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <flux:text class="text-sm text-gray-700">{{ $broadcast->preview_text }}</flux:text>
                        </div>
                    </div>
                @endif

                <div>
                    <flux:text class="text-xs text-gray-600 mb-2">Email Body</flux:text>
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="prose max-w-none text-sm">
                            {!! nl2br(e($broadcast->content)) !!}
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <flux:text class="text-xs text-gray-500">
                        Note: Merge tags like @{{name}}, @{{email}}, and @{{student_id}} are replaced with actual student data when sent.
                    </flux:text>
                </div>
            </div>
        </flux:card>
    @endif

    @if($activeTab === 'logs')
        <flux:card>
            <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                    <flux:heading size="lg">Delivery Logs</flux:heading>
                    <div class="w-full sm:w-48">
                        <flux:select wire:model.live="logStatusFilter" placeholder="All Statuses">
                            <flux:select.option value="">All Statuses</flux:select.option>
                            <flux:select.option value="sent">Sent ({{ $sentCount }})</flux:select.option>
                            <flux:select.option value="failed">Failed ({{ $failedCount }})</flux:select.option>
                            <flux:select.option value="pending">Pending ({{ $pendingCount }})</flux:select.option>
                        </flux:select>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $log->student->user->name }}</div>
                                    <div class="text-xs text-gray-500">ID: {{ $log->student->student_id }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $log->email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge size="sm" :class="match($log->status) {
                                        'sent' => 'badge-green',
                                        'failed' => 'badge-red',
                                        'pending' => 'badge-yellow',
                                        default => 'badge-gray'
                                    }">
                                        {{ ucfirst($log->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($log->sent_at)
                                        <div class="text-sm text-gray-900">{{ $log->sent_at->format('M d, Y') }}</div>
                                        <div class="text-xs text-gray-500">{{ $log->sent_at->format('g:i a') }}</div>
                                    @else
                                        <div class="text-sm text-gray-400">-</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($log->error_message)
                                        <div class="text-xs text-red-600 max-w-xs truncate" title="{{ $log->error_message }}">
                                            {{ $log->error_message }}
                                        </div>
                                    @else
                                        <div class="text-sm text-gray-400">-</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <flux:icon.inbox class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No logs found</h3>
                                    <p class="mt-1 text-sm text-gray-500">
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
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $logs->links() }}
                </div>
            @endif
        </flux:card>
    @endif
</div>
