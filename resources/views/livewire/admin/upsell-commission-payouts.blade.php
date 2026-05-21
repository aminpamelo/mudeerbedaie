<?php

use App\Models\UpsellCommissionPayout;
use App\Services\Upsell\CommissionPayoutService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $from = '';

    public string $to = '';

    /** @var array<int, array<string, mixed>> */
    public array $preview = [];

    public ?int $payoutBeingMarkedPaid = null;

    public string $paymentReference = '';

    public function mount(): void
    {
        $this->authorize('manageUpsellCommissions');

        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function loadPreview(): void
    {
        $this->authorize('manageUpsellCommissions');

        $this->preview = app(CommissionPayoutService::class)
            ->preview($this->from, $this->to)
            ->map(fn (array $row): array => array_merge($row, [
                'session_ids' => $row['session_ids']->toArray(),
            ]))
            ->toArray();
    }

    public function createPayout(int $teacherId): void
    {
        $this->authorize('manageUpsellCommissions');

        $row = collect($this->preview)->firstWhere('teacher_id', $teacherId);
        if (! $row) {
            return;
        }

        app(CommissionPayoutService::class)
            ->createPayout($teacherId, $this->from, $this->to, $row['session_ids']);

        $this->loadPreview();
        session()->flash('success', 'Payout draft created.');
    }

    public function lock(int $payoutId): void
    {
        $this->authorize('manageUpsellCommissions');

        UpsellCommissionPayout::findOrFail($payoutId)->lock();

        session()->flash('success', 'Payout locked.');
    }

    public function startMarkPaid(int $payoutId): void
    {
        $this->authorize('manageUpsellCommissions');

        $this->payoutBeingMarkedPaid = $payoutId;
        $this->paymentReference = '';
    }

    public function cancelMarkPaid(): void
    {
        $this->payoutBeingMarkedPaid = null;
        $this->paymentReference = '';
    }

    public function confirmMarkPaid(): void
    {
        $this->authorize('manageUpsellCommissions');

        $this->validate([
            'paymentReference' => 'required|string|min:3|max:100',
        ]);

        if ($this->payoutBeingMarkedPaid === null) {
            return;
        }

        UpsellCommissionPayout::findOrFail($this->payoutBeingMarkedPaid)
            ->markPaid(auth()->id(), $this->paymentReference);

        $this->payoutBeingMarkedPaid = null;
        $this->paymentReference = '';

        session()->flash('success', 'Payout marked as paid.');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'drafts' => UpsellCommissionPayout::draft()
                ->with('teacher')
                ->latest()
                ->get(),
            'locked' => UpsellCommissionPayout::locked()
                ->with('teacher')
                ->latest('locked_at')
                ->get(),
            'paidHistory' => UpsellCommissionPayout::paid()
                ->with(['teacher', 'paidBy'])
                ->latest('paid_at')
                ->limit(50)
                ->get(),
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Upsell Commission Payouts</flux:heading>
            <flux:text class="mt-2">Preview, create, lock, and mark commission payouts as paid</flux:text>
        </div>
    </div>

    @if(session('success'))
        <flux:callout variant="success" class="mb-6">
            <flux:callout.text>{{ session('success') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Section 1: Preview --}}
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 mb-6">
        <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Preview Unpaid Commissions</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Pick a date range to see eligible commissions per teacher</p>
        </div>
        <div class="p-4">
            <div class="flex items-end gap-3 flex-wrap mb-4">
                <div class="w-40 shrink-0">
                    <flux:input type="date" wire:model="from" label="From" size="sm" />
                </div>
                <div class="w-40 shrink-0">
                    <flux:input type="date" wire:model="to" label="To" size="sm" />
                </div>
                <flux:button variant="primary" size="sm" wire:click="loadPreview">Load Preview</flux:button>
            </div>

            @if(empty($preview))
                <div class="py-8 text-center">
                    <p class="text-xs text-zinc-400">No preview loaded yet. Pick a date range and click Load Preview.</p>
                </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                            <th class="text-left py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Teacher</th>
                            <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sessions</th>
                            <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Commission Total</th>
                            <th class="text-right py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($preview as $row)
                            <tr wire:key="preview-{{ $row['teacher_id'] }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                                <td class="py-2.5 px-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $row['teacher_name'] ?? 'Unknown' }}
                                </td>
                                <td class="py-2.5 px-3 text-right text-sm text-zinc-600 dark:text-zinc-400 tabular-nums">{{ $row['session_count'] }}</td>
                                <td class="py-2.5 px-3 text-right text-sm font-semibold text-amber-600 dark:text-amber-400 tabular-nums whitespace-nowrap">RM {{ number_format($row['commission_total'], 2) }}</td>
                                <td class="py-2.5 px-4 text-right">
                                    <flux:button size="sm" variant="primary" wire:click="createPayout({{ $row['teacher_id'] }})" wire:loading.attr="disabled" wire:target="createPayout({{ $row['teacher_id'] }})">
                                        Create Payout
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Section 2: Drafts --}}
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 mb-6">
        <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Draft Payouts</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Draft payouts can still be discarded. Lock to commit.</p>
        </div>
        @if($drafts->isEmpty())
            <div class="py-8 text-center">
                <p class="text-xs text-zinc-400">No draft payouts</p>
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                        <th class="text-left py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Teacher</th>
                        <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Period</th>
                        <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sessions</th>
                        <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total</th>
                        <th class="text-right py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($drafts as $payout)
                        <tr wire:key="draft-{{ $payout->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                            <td class="py-2.5 px-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $payout->teacher?->name ?? 'Unknown' }}
                            </td>
                            <td class="py-2.5 px-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                {{ $payout->period_start?->toDateString() }} → {{ $payout->period_end?->toDateString() }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-sm text-zinc-600 dark:text-zinc-400 tabular-nums">{{ $payout->session_count }}</td>
                            <td class="py-2.5 px-3 text-right text-sm font-semibold text-amber-600 dark:text-amber-400 tabular-nums whitespace-nowrap">RM {{ number_format((float) $payout->total_commission, 2) }}</td>
                            <td class="py-2.5 px-4 text-right">
                                <flux:button size="sm" variant="primary" wire:click="lock({{ $payout->id }})" wire:loading.attr="disabled">
                                    Lock
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Section 3: Locked + Paid history --}}
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
        <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Locked & Paid History</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Locked payouts await payment. Paid rows are read-only.</p>
        </div>
        @if($locked->isEmpty() && $paidHistory->isEmpty())
            <div class="py-8 text-center">
                <p class="text-xs text-zinc-400">No locked or paid payouts yet</p>
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                        <th class="text-left py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Teacher</th>
                        <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Period</th>
                        <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total</th>
                        <th class="text-center py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Status</th>
                        <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Reference</th>
                        <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Paid At</th>
                        <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Paid By</th>
                        <th class="text-right py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($locked as $payout)
                        <tr wire:key="locked-{{ $payout->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                            <td class="py-2.5 px-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $payout->teacher?->name ?? 'Unknown' }}
                            </td>
                            <td class="py-2.5 px-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                {{ $payout->period_start?->toDateString() }} → {{ $payout->period_end?->toDateString() }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-sm font-semibold text-amber-600 dark:text-amber-400 tabular-nums whitespace-nowrap">RM {{ number_format((float) $payout->total_commission, 2) }}</td>
                            <td class="py-2.5 px-3 text-center">
                                <flux:badge color="blue" size="sm">Locked</flux:badge>
                            </td>
                            <td class="py-2.5 px-3 text-sm text-zinc-400">—</td>
                            <td class="py-2.5 px-3 text-sm text-zinc-400">—</td>
                            <td class="py-2.5 px-3 text-sm text-zinc-400">—</td>
                            <td class="py-2.5 px-4 text-right">
                                @if($payoutBeingMarkedPaid === $payout->id)
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-48">
                                            <flux:input
                                                wire:model="paymentReference"
                                                placeholder="TXN-12345"
                                                size="sm"
                                            />
                                        </div>
                                        <flux:button size="sm" variant="primary" wire:click="confirmMarkPaid" wire:loading.attr="disabled">
                                            Confirm
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="cancelMarkPaid">
                                            Cancel
                                        </flux:button>
                                    </div>
                                    @error('paymentReference')
                                        <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                                    @enderror
                                @else
                                    <flux:button size="sm" variant="primary" wire:click="startMarkPaid({{ $payout->id }})">
                                        Mark Paid
                                    </flux:button>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    @foreach($paidHistory as $payout)
                        <tr wire:key="paid-{{ $payout->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                            <td class="py-2.5 px-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $payout->teacher?->name ?? 'Unknown' }}
                            </td>
                            <td class="py-2.5 px-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                {{ $payout->period_start?->toDateString() }} → {{ $payout->period_end?->toDateString() }}
                            </td>
                            <td class="py-2.5 px-3 text-right text-sm font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums whitespace-nowrap">RM {{ number_format((float) $payout->total_commission, 2) }}</td>
                            <td class="py-2.5 px-3 text-center">
                                <flux:badge color="green" size="sm">Paid</flux:badge>
                            </td>
                            <td class="py-2.5 px-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">{{ $payout->payment_reference }}</td>
                            <td class="py-2.5 px-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">{{ $payout->paid_at?->toDateTimeString() }}</td>
                            <td class="py-2.5 px-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $payout->paidBy?->name ?? '—' }}</td>
                            <td class="py-2.5 px-4 text-right text-sm text-zinc-400">Read-only</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
