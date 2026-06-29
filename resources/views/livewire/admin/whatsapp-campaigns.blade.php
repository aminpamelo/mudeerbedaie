<?php

use App\Models\WhatsAppCampaign;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function mount(): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Access denied');
        }
    }

    public function with(): array
    {
        return [
            'campaigns' => WhatsAppCampaign::query()
                ->with('creator')
                ->withCount('recipients')
                ->latest()
                ->paginate(15),
            'usdToMyr' => (float) config('whatsapp-pricing.usd_to_myr', 4.50),
        ];
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'bg-emerald-100 text-emerald-700',
            'sending' => 'bg-blue-100 text-blue-700',
            'queued' => 'bg-amber-100 text-amber-700',
            'cancelled' => 'bg-slate-100 text-slate-600',
            'failed' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
} ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">WhatsApp Campaigns</flux:heading>
            <flux:text class="mt-2">Bulk WhatsApp blasts sent to your customers via the official WhatsApp API.</flux:text>
        </div>
        <flux:button :href="route('admin.orders.index')" variant="primary" icon="paper-airplane" wire:navigate>
            New blast from Orders
        </flux:button>
    </div>

    <flux:callout class="mb-6" icon="information-circle" variant="secondary">
        Start a blast from <strong>Orders &amp; Package Sales</strong> — select orders, then choose <strong>Send WhatsApp</strong>. Blasts use approved message templates and send in the background.
    </flux:callout>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Campaign</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Recipients</th>
                    <th class="px-4 py-3 text-right">Sent</th>
                    <th class="px-4 py-3 text-right">Delivered</th>
                    <th class="px-4 py-3 text-right">Read</th>
                    <th class="px-4 py-3 text-right">Failed</th>
                    <th class="px-4 py-3 text-right">Est. cost</th>
                    <th class="px-4 py-3">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($campaigns as $campaign)
                    <tr wire:key="campaign-{{ $campaign->id }}" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.whatsapp.campaigns.show', $campaign) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800">
                                {{ $campaign->name }}
                            </a>
                            <p class="text-xs text-slate-400">
                                {{ $campaign->template_name }} · {{ strtoupper($campaign->template_language) }}
                                @if ($campaign->creator) · by {{ $campaign->creator->name }} @endif
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $this->statusColor($campaign->status) }}">
                                {{ ucfirst($campaign->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($campaign->total_recipients) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ number_format($campaign->sent_count) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-sky-600">{{ number_format($campaign->delivered_count) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-emerald-600">{{ number_format($campaign->read_count) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $campaign->failed_count > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ number_format($campaign->failed_count) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-600">RM {{ number_format($campaign->estimated_cost_usd * $usdToMyr, 2) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $campaign->created_at?->format('d M Y, H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center">
                            <flux:icon.megaphone class="mx-auto mb-3 h-10 w-10 text-slate-300" />
                            <p class="text-sm font-medium text-slate-500">No campaigns yet</p>
                            <p class="text-xs text-slate-400">Select orders on the Orders page and choose “Send WhatsApp”.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $campaigns->links() }}
    </div>
</div>
