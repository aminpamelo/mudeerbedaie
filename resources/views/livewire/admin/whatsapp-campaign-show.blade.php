<?php

use App\Models\WhatsAppCampaign;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
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

        return [
            'campaign' => $campaign,
            'recipients' => $recipients,
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
    <div class="mb-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
        @php
            $stats = [
                ['Recipients', $campaign->total_recipients, 'text-slate-900'],
                ['Sent', $campaign->sent_count, 'text-blue-600'],
                ['Delivered', $campaign->delivered_count, 'text-sky-600'],
                ['Read', $campaign->read_count, 'text-emerald-600'],
                ['Failed', $campaign->failed_count, 'text-rose-600'],
                ['Skipped', $campaign->skipped_count, 'text-amber-600'],
            ];
        @endphp
        @foreach ($stats as [$label, $value, $color])
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums {{ $color }}">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
        Estimated cost: <strong>RM {{ number_format($campaign->estimated_cost_usd * $usdToMyr, 2) }}</strong>
        <span class="text-slate-400">(${{ number_format($campaign->estimated_cost_usd, 4) }} USD · {{ $campaign->total_recipients }} × {{ ucfirst(optional($campaign->template)->category ?? 'marketing') }} template)</span>
    </div>

    {{-- Recipients --}}
    <div class="mb-3 flex flex-wrap items-center gap-2">
        @foreach (['all' => 'All', 'sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed', 'pending' => 'Pending'] as $key => $label)
            <button type="button" wire:click="$set('statusFilter', '{{ $key }}')"
                class="rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $statusFilter === $key ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Order</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Sent</th>
                    <th class="px-4 py-3">Delivered</th>
                    <th class="px-4 py-3">Read</th>
                    <th class="px-4 py-3">Error</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recipients as $recipient)
                    <tr wire:key="recipient-{{ $recipient->id }}" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $recipient->customer_name ?: '—' }}</td>
                        <td class="px-4 py-3 tabular-nums text-slate-600">{{ $recipient->phone }}</td>
                        <td class="px-4 py-3 text-slate-500">
                            @if ($recipient->order)
                                <a href="{{ route('admin.orders.show', $recipient->order) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800">{{ $recipient->order->order_number }}</a>
                            @else — @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $this->recipientBadge($recipient->status) }}">
                                {{ ucfirst($recipient->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $recipient->sent_at?->format('d M H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $recipient->delivered_at?->format('d M H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $recipient->read_at?->format('d M H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-rose-500">{{ $recipient->error_message }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-sm text-slate-400">No recipients for this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $recipients->links() }}
    </div>
</div>
