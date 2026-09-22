<?php

use App\Jobs\SendBroadcastEmail;
use App\Models\Broadcast;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Broadcast $broadcast;
    public string $activeTab = 'recipients';
    public string $recipientSearch = '';
    public string $recipientFilter = '';
    public ?string $flash = null;

    public function mount(Broadcast $broadcast): void
    {
        $this->broadcast = $broadcast;
    }

    public function updatingRecipientSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRecipientFilter(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function pause(): void
    {
        if ($this->broadcast->canPause()) {
            $this->broadcast->update(['status' => 'paused']);
            $this->broadcast->refresh();
            $this->flash = 'Campaign paused. In-flight emails finish, the rest hold until you resume.';
        }
    }

    public function resume(): void
    {
        if ($this->broadcast->canResume()) {
            $this->broadcast->update(['status' => 'sending']);
            $this->broadcast->refresh();
            SendBroadcastEmail::dispatch($this->broadcast);
            $this->flash = 'Campaign resumed — remaining recipients are being sent.';
        }
    }

    public function cancel(): void
    {
        if ($this->broadcast->canCancel()) {
            $this->broadcast->update(['status' => 'cancelled']);
            $this->broadcast->refresh();
            $this->flash = 'Campaign cancelled. No further emails will be sent.';
        }
    }

    public function sendNow(): void
    {
        if (in_array($this->broadcast->status, ['draft', 'scheduled'], true)) {
            $this->broadcast->update(['status' => 'sending', 'scheduled_at' => null]);
            $this->broadcast->refresh();
            SendBroadcastEmail::dispatch($this->broadcast);
            $this->flash = 'Campaign is sending now.';
        }
    }

    protected function recipientsQuery()
    {
        $ids = $this->broadcast->recipientStudentIds();

        // One log per student (the latest), so legacy duplicate logs never
        // multiply rows or inflate counts.
        $latestLogs = DB::table('broadcast_logs')
            ->selectRaw('student_id, MAX(id) as max_id')
            ->where('broadcast_id', $this->broadcast->id)
            ->groupBy('student_id');

        $query = Student::query()
            ->whereIn('students.id', $ids)
            ->leftJoin('users', 'users.id', '=', 'students.user_id')
            ->leftJoinSub($latestLogs, 'latest', 'latest.student_id', '=', 'students.id')
            ->leftJoin('broadcast_logs', 'broadcast_logs.id', '=', 'latest.max_id')
            ->select([
                'students.id',
                'students.student_id',
                'users.name as user_name',
                'users.email as user_email',
                'broadcast_logs.status as delivery_status',
                'broadcast_logs.sent_at as delivery_sent_at',
                'broadcast_logs.opened_at as delivery_opened_at',
                'broadcast_logs.clicked_at as delivery_clicked_at',
                'broadcast_logs.error_message as delivery_error',
            ]);

        if ($this->recipientSearch !== '') {
            $term = '%'.$this->recipientSearch.'%';
            $query->where(function ($w) use ($term) {
                $w->where('users.name', 'like', $term)
                    ->orWhere('users.email', 'like', $term)
                    ->orWhere('students.student_id', 'like', $term);
            });
        }

        match ($this->recipientFilter) {
            'sent' => $query->where('broadcast_logs.status', 'sent'),
            'failed' => $query->where('broadcast_logs.status', 'failed'),
            'skipped' => $query->where('broadcast_logs.status', 'skipped'),
            'opened' => $query->whereNotNull('broadcast_logs.opened_at'),
            'clicked' => $query->whereNotNull('broadcast_logs.clicked_at'),
            'queued' => $query->where(fn ($w) => $w->whereNull('broadcast_logs.status')->orWhere('broadcast_logs.status', 'pending')),
            default => null,
        };

        return $query
            ->orderByRaw('CASE WHEN broadcast_logs.status IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('broadcast_logs.sent_at')
            ->orderBy('users.name');
    }

    public function with(): array
    {
        $sent = $this->broadcast->logs()->where('status', 'sent')->distinct()->count('student_id');
        $failed = $this->broadcast->logs()->where('status', 'failed')->distinct()->count('student_id');
        $skipped = $this->broadcast->logs()->where('status', 'skipped')->distinct()->count('student_id');
        $opened = $this->broadcast->logs()->whereNotNull('opened_at')->distinct()->count('student_id');
        $clicked = $this->broadcast->logs()->whereNotNull('clicked_at')->distinct()->count('student_id');
        $total = (int) ($this->broadcast->total_recipients ?: count($this->broadcast->recipientStudentIds()));
        $queued = max(0, $total - $sent - $failed - $skipped);

        return [
            'recipients' => $this->recipientsQuery()->paginate(20),
            'audiences' => $this->broadcast->audiences()->withCount('students')->get(),
            'metrics' => [
                'total' => $total,
                'sent' => $sent,
                'failed' => $failed,
                'opened' => $opened,
                'clicked' => $clicked,
                'queued' => $queued,
                'skipped' => $skipped,
                'open_rate' => $sent > 0 ? round($opened / $sent * 100, 1) : 0.0,
                'click_rate' => $sent > 0 ? round($clicked / $sent * 100, 1) : 0.0,
                'sent_rate' => $total > 0 ? round($sent / $total * 100, 1) : 0.0,
                'processed_pct' => $total > 0 ? min(100, round(($sent + $failed) / $total * 100)) : 0,
            ],
        ];
    }
}; ?>

@php
    $statusStyles = [
        'draft' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300',
        'scheduled' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-300',
        'sending' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-300',
        'paused' => 'bg-orange-50 text-orange-700 ring-1 ring-orange-600/20 dark:bg-orange-900/30 dark:text-orange-300',
        'sent' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-300',
        'failed' => 'bg-red-50 text-red-700 ring-1 ring-red-600/20 dark:bg-red-900/30 dark:text-red-300',
        'cancelled' => 'bg-zinc-100 text-zinc-500 line-through dark:bg-zinc-800 dark:text-zinc-400',
    ];
@endphp

<div class="mx-auto max-w-7xl">
    <!-- Flash -->
    @if($flash)
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition
            class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-300"
            role="status"
        >
            <flux:icon name="check-circle" class="h-4 w-4 shrink-0" />
            <span>{{ $flash }}</span>
            <button type="button" @click="show = false" class="ml-auto text-emerald-600 hover:text-emerald-800 dark:text-emerald-400" aria-label="Dismiss">
                <flux:icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>
    @endif

    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <flux:button variant="ghost" size="sm" href="{{ route('crm.broadcasts.index') }}" wire:navigate>
                    <div class="flex items-center">
                        <flux:icon name="chevron-left" class="mr-1 h-4 w-4" />
                        Back
                    </div>
                </flux:button>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $broadcast->name }}</h1>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider {{ $statusStyles[$broadcast->status] ?? $statusStyles['draft'] }}">
                    {{ ucfirst($broadcast->status) }}
                </span>
                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ ucfirst($broadcast->type) }} Broadcast</span>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap items-center gap-2">
            @if(in_array($broadcast->status, ['draft', 'scheduled'], true))
                <flux:button variant="ghost" size="sm" href="{{ route('crm.broadcasts.edit', $broadcast) }}" wire:navigate>
                    <div class="flex items-center justify-center">
                        <flux:icon name="pencil-square" class="mr-1.5 h-4 w-4" />
                        Edit
                    </div>
                </flux:button>
                <flux:button variant="primary" size="sm" wire:click="sendNow" wire:confirm="Send this campaign to all recipients now?" wire:loading.attr="disabled" wire:target="sendNow">
                    <div class="flex items-center justify-center">
                        <flux:icon name="paper-airplane" class="mr-1.5 h-4 w-4" />
                        <span wire:loading.remove wire:target="sendNow">Send now</span>
                        <span wire:loading wire:target="sendNow">Sending…</span>
                    </div>
                </flux:button>
            @endif

            @if($broadcast->canPause())
                <flux:button variant="outline" size="sm" wire:click="pause" wire:loading.attr="disabled" wire:target="pause">
                    <div class="flex items-center justify-center">
                        <flux:icon name="pause" class="mr-1.5 h-4 w-4" />
                        Pause
                    </div>
                </flux:button>
            @endif

            @if($broadcast->canResume())
                <flux:button variant="primary" size="sm" wire:click="resume" wire:loading.attr="disabled" wire:target="resume">
                    <div class="flex items-center justify-center">
                        <flux:icon name="play" class="mr-1.5 h-4 w-4" />
                        Resume
                    </div>
                </flux:button>
            @endif

            @if($broadcast->canCancel())
                <flux:button variant="ghost" size="sm" wire:click="cancel" wire:confirm="Cancel this campaign? Recipients not yet emailed will not receive it. This cannot be undone." wire:loading.attr="disabled" wire:target="cancel">
                    <div class="flex items-center justify-center text-red-600 dark:text-red-400">
                        <flux:icon name="x-circle" class="mr-1.5 h-4 w-4" />
                        Cancel
                    </div>
                </flux:button>
            @endif
        </div>
    </div>

    <!-- Progress (while sending / paused) -->
    @if($broadcast->isInProgress())
        <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-medium text-zinc-700 dark:text-zinc-300">
                    {{ $broadcast->status === 'paused' ? 'Paused' : 'Sending…' }}
                </span>
                <span class="tabular-nums text-zinc-500 dark:text-zinc-400">
                    {{ number_format($metrics['sent'] + $metrics['failed']) }} of {{ number_format($metrics['total']) }} processed ({{ $metrics['processed_pct'] }}%)
                </span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                <div class="h-full rounded-full transition-all duration-500 {{ $broadcast->status === 'paused' ? 'bg-orange-400' : 'bg-emerald-500' }}" style="width: {{ $metrics['processed_pct'] }}%"></div>
            </div>
        </div>
    @endif

    <!-- KPI Cards -->
    @php
        $cards = [
            ['label' => 'Recipients', 'value' => number_format($metrics['total']), 'sub' => $metrics['skipped'] > 0 ? number_format($metrics['skipped']).' skipped (invalid)' : null, 'icon' => 'users', 'chip' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'],
            ['label' => 'Sent', 'value' => number_format($metrics['sent']), 'sub' => $metrics['sent_rate'].'% of recipients', 'icon' => 'paper-airplane', 'chip' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'],
            ['label' => 'Opened', 'value' => number_format($metrics['opened']), 'sub' => $metrics['open_rate'].'% open rate', 'icon' => 'envelope-open', 'chip' => 'bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400'],
            ['label' => 'Clicked', 'value' => number_format($metrics['clicked']), 'sub' => $metrics['click_rate'].'% click rate', 'icon' => 'cursor-arrow-rays', 'chip' => 'bg-violet-50 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400'],
            ['label' => 'Failed', 'value' => number_format($metrics['failed']), 'sub' => $metrics['failed'] > 0 ? 'needs attention' : 'none', 'icon' => 'exclamation-triangle', 'chip' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400'],
        ];
    @endphp
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach($cards as $card)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $card['chip'] }}">
                        <flux:icon :name="$card['icon']" class="h-4 w-4" />
                    </span>
                    <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ $card['label'] }}</p>
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $card['value'] }}</p>
                @if($card['sub'])
                    <p class="text-[11px] tabular-nums text-zinc-400 dark:text-zinc-500">{{ $card['sub'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <!-- Tabs -->
    <div class="mb-5 border-b border-zinc-200 dark:border-zinc-700">
        <nav class="-mb-px flex gap-6">
            @foreach(['recipients' => 'Recipients', 'overview' => 'Overview', 'content' => 'Content'] as $tab => $label)
                <button
                    wire:click="setTab('{{ $tab }}')"
                    class="cursor-pointer border-b-2 px-1 py-3 text-sm font-medium transition-colors {{ $activeTab === $tab ? 'border-emerald-500 text-zinc-900 dark:border-emerald-400 dark:text-zinc-100' : 'border-transparent text-zinc-400 hover:border-zinc-300 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300' }}"
                >
                    {{ $label }}
                    @if($tab === 'recipients')
                        <span class="ml-1 tabular-nums text-zinc-400">{{ number_format($metrics['total']) }}</span>
                    @endif
                    @if($tab === 'recipients' && $metrics['failed'] > 0)
                        <span class="ml-1 inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold tabular-nums bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400">{{ $metrics['failed'] }}</span>
                    @endif
                </button>
            @endforeach
        </nav>
    </div>

    <!-- Recipients -->
    @if($activeTab === 'recipients')
        <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative sm:w-72">
                    <flux:input wire:model.live.debounce.300ms="recipientSearch" placeholder="Search name, email or ID…" icon="magnifying-glass" size="sm" clearable />
                </div>
                <div class="sm:w-52">
                    <flux:select wire:model.live="recipientFilter" size="sm">
                        <flux:select.option value="">All recipients ({{ number_format($metrics['total']) }})</flux:select.option>
                        <flux:select.option value="sent">Sent ({{ number_format($metrics['sent']) }})</flux:select.option>
                        <flux:select.option value="opened">Opened ({{ number_format($metrics['opened']) }})</flux:select.option>
                        <flux:select.option value="clicked">Clicked ({{ number_format($metrics['clicked']) }})</flux:select.option>
                        <flux:select.option value="failed">Failed ({{ number_format($metrics['failed']) }})</flux:select.option>
                        <flux:select.option value="skipped">Skipped ({{ number_format($metrics['skipped']) }})</flux:select.option>
                        <flux:select.option value="queued">Queued ({{ number_format($metrics['queued']) }})</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <div class="overflow-x-auto" wire:loading.class.delay="opacity-60">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Client</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Email</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Delivery</th>
                            <th class="px-4 py-2.5 text-center text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Opened</th>
                            <th class="px-4 py-2.5 text-center text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Clicked</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sent At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($recipients as $r)
                            @php
                                $delivery = $r->delivery_status ?? 'queued';
                                $deliveryStyle = match($delivery) {
                                    'sent' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                    'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'skipped' => 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                                    'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                    default => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
                                };
                                $deliveryLabel = $delivery === 'pending' ? 'Queued' : ucfirst($delivery);
                            @endphp
                            <tr wire:key="recipient-{{ $r->id }}" class="transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30">
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $r->user_name ?? 'Unknown' }}</p>
                                    @if($r->student_id)
                                        <p class="text-xs text-zinc-400 dark:text-zinc-500">ID: {{ $r->student_id }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-sm text-zinc-600 dark:text-zinc-300">{{ $r->user_email ?? '—' }}</td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ $deliveryStyle }}">{{ $deliveryLabel }}</span>
                                    @if(in_array($delivery, ['failed', 'skipped'], true) && $r->delivery_error)
                                        <flux:tooltip content="{{ $r->delivery_error }}">
                                            <flux:icon name="information-circle" class="ml-1 inline h-3.5 w-3.5 {{ $delivery === 'failed' ? 'text-red-400' : 'text-orange-400' }}" />
                                        </flux:tooltip>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    @if($r->delivery_opened_at)
                                        <flux:tooltip content="{{ \Illuminate\Support\Carbon::parse($r->delivery_opened_at)->format('M d, Y g:i a') }}">
                                            <flux:icon name="check-circle" class="inline h-4 w-4 text-sky-500" />
                                        </flux:tooltip>
                                    @else
                                        <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    @if($r->delivery_clicked_at)
                                        <flux:tooltip content="{{ \Illuminate\Support\Carbon::parse($r->delivery_clicked_at)->format('M d, Y g:i a') }}">
                                            <flux:icon name="check-circle" class="inline h-4 w-4 text-violet-500" />
                                        </flux:tooltip>
                                    @else
                                        <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    @if($r->delivery_sent_at)
                                        <p class="text-sm tabular-nums text-zinc-700 dark:text-zinc-300">{{ \Illuminate\Support\Carbon::parse($r->delivery_sent_at)->format('M d, Y') }}</p>
                                        <p class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ \Illuminate\Support\Carbon::parse($r->delivery_sent_at)->format('g:i a') }}</p>
                                    @else
                                        <span class="text-sm text-zinc-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-16 text-center">
                                    <flux:icon name="users" class="mx-auto h-10 w-10 text-zinc-300 dark:text-zinc-600" />
                                    <p class="mt-3 text-sm font-medium text-zinc-600 dark:text-zinc-300">No recipients found</p>
                                    <p class="mt-1 text-[13px] text-zinc-400 dark:text-zinc-500">
                                        @if($recipientSearch || $recipientFilter)
                                            Try adjusting your search or filter.
                                        @else
                                            This broadcast has no target recipients yet.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($recipients->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $recipients->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- Overview -->
    @if($activeTab === 'overview')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Broadcast Information</h3>
                </div>
                <dl class="space-y-3 px-5 py-4">
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Name</dt>
                        <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Type</dt>
                        <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ ucfirst($broadcast->type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Created At</dt>
                        <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->created_at->format('M d, Y g:i a') }}</dd>
                    </div>
                    @if($broadcast->scheduled_at)
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Scheduled At</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->scheduled_at->format('M d, Y g:i a') }}</dd>
                        </div>
                    @endif
                    @if($broadcast->sent_at)
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sent At</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->sent_at->format('M d, Y g:i a') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Email Settings</h3>
                </div>
                <dl class="space-y-3 px-5 py-4">
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">From</dt>
                        <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->from_name }} &lt;{{ $broadcast->from_email }}&gt;</dd>
                    </div>
                    @if($broadcast->reply_to_email)
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Reply-To</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->reply_to_email }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Subject</dt>
                        <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->subject }}</dd>
                    </div>
                    @if($broadcast->preview_text)
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Preview Text</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ $broadcast->preview_text }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white lg:col-span-2 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Target Audiences ({{ $audiences->count() }})</h3>
                </div>
                <div class="px-5 py-4">
                    @if($audiences->count() > 0)
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                            @foreach($audiences as $audience)
                                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $audience->name }}</p>
                                        <p class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">{{ number_format($audience->students_count ?? 0) }} contacts</p>
                                    </div>
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ $audience->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">
                                        {{ ucfirst($audience->status) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-zinc-400 dark:text-zinc-500">
                            This campaign is sending to a snapshot of <span class="font-medium tabular-nums text-zinc-500 dark:text-zinc-400">{{ number_format($metrics['total']) }}</span> recipients taken when it was built. See the Recipients tab for the full list.
                        </p>
                    @else
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No audiences selected</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Content -->
    @if($activeTab === 'content')
        <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Email Content</h3>
            </div>
            <div class="space-y-6 px-5 py-4">
                <div>
                    <p class="mb-2 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Subject Line</p>
                    <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $broadcast->subject }}</p>
                    </div>
                </div>
                <div>
                    <p class="mb-2 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Email Body</p>
                    <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="prose max-w-none text-sm dark:prose-invert">
                            {!! $broadcast->isVisualEditor() ? $broadcast->html_content : nl2br(e($broadcast->content)) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
