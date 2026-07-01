<?php

use App\Models\WhatsAppCampaign;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $campaignId;

    public string $statusFilter = 'all';

    public function mount(WhatsAppCampaign $campaign): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Access denied');
        }

        $this->campaignId = $campaign->id;
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $campaign = WhatsAppCampaign::with('creator', 'template')->findOrFail($this->campaignId);

        $recipients = $campaign->recipients()
            ->with('order:id,order_number')
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(25);

        $statusCounts = $campaign->recipients()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'campaign' => $campaign,
            'recipients' => $recipients,
            'statusCounts' => $statusCounts,
            'usdToMyr' => (float) config('whatsapp-pricing.usd_to_myr', 4.50),
        ];
    }

    public function recipientBadge(string $status): string
    {
        return match ($status) {
            'read' => 'bg-emerald-100 text-emerald-700',
            'delivered' => 'bg-sky-100 text-sky-700',
            'sent' => 'bg-blue-100 text-blue-700',
            'failed' => 'bg-rose-100 text-rose-700',
            'skipped' => 'bg-amber-100 text-amber-700',
            default => 'bg-slate-100 text-slate-500',
        };
    }
} ?>

<div @if (! $campaign->isFinished()) wire:poll.5s @endif>
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <div class="mb-1 flex items-center gap-2 text-sm text-slate-500">
                <a href="{{ route('admin.whatsapp.campaigns') }}" wire:navigate class="hover:text-indigo-600">Campaigns</a>
                <span>/</span>
                <span>Detail</span>
            </div>
            <flux:heading size="xl">{{ $campaign->name }}</flux:heading>
            <flux:text class="mt-1">
                Template <strong>{{ $campaign->template_name }}</strong> · {{ strtoupper($campaign->template_language) }}
                @if ($campaign->creator) · by {{ $campaign->creator->name }} @endif
                · {{ $campaign->created_at?->format('d M Y, H:i') }}
            </flux:text>
        </div>
        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium
            {{ $campaign->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : ($campaign->status === 'sending' ? 'bg-blue-100 text-blue-700' : ($campaign->status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700')) }}">
            @if (! $campaign->isFinished())
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current"></span>
            @endif
            {{ ucfirst($campaign->status) }}
        </span>
    </div>

    {{-- Progress bar while sending --}}
    @if (! $campaign->isFinished())
        <div class="mb-6">
            <div class="mb-1 flex items-center justify-between text-xs text-slate-500">
                <span>Sending…</span>
                <span>{{ $campaign->progress_percent }}%</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-indigo-500 transition-all" style="width: {{ $campaign->progress_percent }}%"></div>
            </div>
        </div>
    @endif

    {{-- Stat cards --}}
    @php
        $total = max((int) $campaign->total_recipients, 0);
        $accents = [
            'slate' => ['chip' => 'bg-slate-100 text-slate-600', 'num' => 'text-slate-900'],
            'blue' => ['chip' => 'bg-blue-50 text-blue-600', 'num' => 'text-blue-600'],
            'sky' => ['chip' => 'bg-sky-50 text-sky-600', 'num' => 'text-sky-600'],
            'emerald' => ['chip' => 'bg-emerald-50 text-emerald-600', 'num' => 'text-emerald-600'],
            'rose' => ['chip' => 'bg-rose-50 text-rose-600', 'num' => 'text-rose-600'],
            'amber' => ['chip' => 'bg-amber-50 text-amber-600', 'num' => 'text-amber-600'],
        ];
        $stats = [
            ['label' => 'Recipients', 'value' => $campaign->total_recipients, 'icon' => 'users', 'accent' => 'slate', 'base' => true],
            ['label' => 'Sent', 'value' => $campaign->sent_count, 'icon' => 'paper-airplane', 'accent' => 'blue'],
            ['label' => 'Delivered', 'value' => $campaign->delivered_count, 'icon' => 'check', 'accent' => 'sky'],
            ['label' => 'Read', 'value' => $campaign->read_count, 'icon' => 'check-badge', 'accent' => 'emerald'],
            ['label' => 'Failed', 'value' => $campaign->failed_count, 'icon' => 'x-circle', 'accent' => 'rose'],
            ['label' => 'Skipped', 'value' => $campaign->skipped_count, 'icon' => 'minus-circle', 'accent' => 'amber'],
        ];
    @endphp
    <div class="mb-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($stats as $stat)
            @php $a = $accents[$stat['accent']]; @endphp
            <div class="rounded-xl border border-slate-200 bg-white p-4 transition-shadow duration-200 hover:shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $a['chip'] }}">
                        <flux:icon :name="$stat['icon']" class="h-4 w-4" />
                    </span>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $stat['label'] }}</p>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <p class="text-2xl font-semibold tabular-nums {{ $a['num'] }}">{{ number_format($stat['value']) }}</p>
                    @if (empty($stat['base']) && $total > 0)
                        <span class="text-xs font-medium tabular-nums text-slate-400">{{ round($stat['value'] / $total * 100) }}%</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
        <span class="mr-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
            <flux:icon name="banknotes" class="h-4 w-4" />
        </span>
        <span class="text-slate-500">Estimated cost</span>
        <span class="font-semibold text-slate-900">RM {{ number_format($campaign->estimated_cost_usd * $usdToMyr, 2) }}</span>
        <span class="text-slate-400">· ${{ number_format($campaign->estimated_cost_usd, 4) }} USD · {{ $campaign->total_recipients }} × {{ ucfirst(optional($campaign->template)->category ?? 'marketing') }} template</span>
    </div>

    {{-- Recipients --}}
    <div class="mb-3 flex flex-wrap items-center gap-2">
        @foreach (['all' => 'All', 'sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed', 'pending' => 'Pending'] as $key => $label)
            @php $count = $key === 'all' ? $statusCounts->sum() : ($statusCounts[$key] ?? 0); @endphp
            <button type="button" wire:click="$set('statusFilter', '{{ $key }}')"
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-colors {{ $statusFilter === $key ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                {{ $label }}
                <span class="rounded-full px-1.5 py-0.5 text-[10px] leading-none tabular-nums {{ $statusFilter === $key ? 'bg-white/20 text-white' : 'bg-white text-slate-500' }}">{{ number_format($count) }}</span>
            </button>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Customer</th>
                        <th class="px-4 py-3 font-medium">Phone</th>
                        <th class="px-4 py-3 font-medium">Order</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Sent</th>
                        <th class="px-4 py-3 font-medium">Delivered</th>
                        <th class="px-4 py-3 font-medium">Read</th>
                        <th class="px-4 py-3 font-medium">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recipients as $recipient)
                        <tr wire:key="recipient-{{ $recipient->id }}" class="transition-colors hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $recipient->customer_name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600">{{ $recipient->phone }}</td>
                            <td class="px-4 py-3 text-slate-500">
                                @if ($recipient->order)
                                    <a href="{{ route('admin.orders.show', $recipient->order) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800">{{ $recipient->order->order_number }}</a>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $this->recipientBadge($recipient->status) }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                                    {{ ucfirst($recipient->status) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ $recipient->sent_at?->format('d M H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ $recipient->delivered_at?->format('d M H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ $recipient->read_at?->format('d M H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs">
                                @if ($recipient->error_message)
                                    <span class="block max-w-xs truncate text-rose-500" title="{{ $recipient->error_message }}">{{ $recipient->error_message }}</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center">
                                <flux:icon name="inbox" class="mx-auto mb-2 h-8 w-8 text-slate-300" />
                                <p class="text-sm text-slate-400">No recipients for this filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $recipients->links() }}
    </div>
</div>
