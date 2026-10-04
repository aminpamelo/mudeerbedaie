<?php

use App\Services\Fighter\FighterPerformanceReport;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    #[Url]
    public string $period = 'this_month';

    #[Url]
    public string $sortBy = 'total_sales';

    public string $customStart = '';

    public string $customEnd = '';

    public string $search = '';

    public function mount(): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        $this->customStart = now()->startOfMonth()->toDateString();
        $this->customEnd = now()->toDateString();
    }

    public function sort(string $column): void
    {
        $this->sortBy = $column;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function range(): array
    {
        return match ($this->period) {
            'today' => [now(), now()],
            '7d' => [now()->subDays(6), now()],
            '30d' => [now()->subDays(29), now()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [
                Carbon::parse($this->customStart ?: now()->toDateString()),
                Carbon::parse($this->customEnd ?: now()->toDateString()),
            ],
            default => [now()->startOfMonth(), now()],
        };
    }

    public function with(FighterPerformanceReport $report): array
    {
        [$from, $to] = $this->range();
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $data = $report->build($from, $to);

        $sortable = ['total_sales', 'funnel_sales', 'manual_sales', 'spend', 'roas', 'net', 'visitors', 'total_orders'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'total_sales';

        $rows = $data['rows']
            ->when($this->search !== '', fn ($rows) => $rows->filter(
                fn (array $r) => str_contains(mb_strtolower($r['name'].' '.$r['email']), mb_strtolower($this->search))
            ))
            ->sortByDesc(fn (array $r) => $r[$sortBy] ?? -INF)
            ->values();

        return [
            'rows' => $rows,
            'totals' => $data['totals'],
            'rangeLabel' => $from->isSameDay($to) ? $from->format('d M Y') : $from->format('d M Y').' – '.$to->format('d M Y'),
            'activeSort' => $sortBy,
        ];
    }
}; ?>

@php
    $money = fn ($v) => ($v < 0 ? '−' : '').'RM '.number_format(abs((float) $v), 2);
    $sortHeader = function (string $key, string $label) use ($activeSort) {
        return ['key' => $key, 'label' => $label, 'active' => $activeSort === $key];
    };
@endphp

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Fighter Performance Report</flux:heading>
            <flux:text class="mt-2">Sales, ad spend, ROAS and net for every fighter · {{ $rangeLabel }}</flux:text>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="period" label="Period">
                <option value="today">Today</option>
                <option value="7d">Last 7 days</option>
                <option value="30d">Last 30 days</option>
                <option value="this_month">This month</option>
                <option value="last_month">Last month</option>
                <option value="custom">Custom range</option>
            </flux:select>
        </div>
        @if($period === 'custom')
            <div class="w-full sm:w-44">
                <flux:input type="date" wire:model.live="customStart" label="From" />
            </div>
            <div class="w-full sm:w-44">
                <flux:input type="date" wire:model.live="customEnd" label="To" />
            </div>
        @endif
        <div class="w-full sm:min-w-[220px] sm:flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search fighter..." icon="magnifying-glass" />
        </div>
    </div>

    {{-- Summary --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-6">
        <flux:card class="p-4">
            <flux:text size="sm">Active fighters</flux:text>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $totals['active_fighters'] }}<span class="text-base font-normal text-gray-400"> / {{ $totals['fighters'] }}</span></div>
            <flux:text size="sm" class="mt-1">{{ $totals['with_bm'] }} with Business Manager</flux:text>
        </flux:card>
        <flux:card class="p-4">
            <flux:text size="sm">Total sales</flux:text>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $money($totals['total_sales']) }}</div>
            <flux:text size="sm" class="mt-1">{{ number_format($totals['total_orders']) }} orders</flux:text>
        </flux:card>
        <flux:card class="p-4">
            <flux:text size="sm">Funnel / Manual</flux:text>
            <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $money($totals['funnel_sales']) }}</div>
            <flux:text size="sm" class="mt-1">Manual {{ $money($totals['manual_sales']) }}</flux:text>
        </flux:card>
        <flux:card class="p-4">
            <flux:text size="sm">Ad spend</flux:text>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $money($totals['spend']) }}</div>
            <flux:text size="sm" class="mt-1">+SST {{ $money($totals['spend_with_sst']) }}</flux:text>
        </flux:card>
        <flux:card class="p-4">
            <flux:text size="sm">ROAS (funnel)</flux:text>
            <div class="mt-1 text-2xl font-semibold {{ $totals['roas'] === null ? 'text-gray-400' : ($totals['roas'] >= 1 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400') }}">
                {{ $totals['roas'] === null ? '—' : number_format($totals['roas'], 2).'×' }}
            </div>
        </flux:card>
        <flux:card class="p-4">
            <flux:text size="sm">Net (funnel − spend+SST)</flux:text>
            <div class="mt-1 text-2xl font-semibold {{ $totals['net'] > 0 ? 'text-green-600 dark:text-green-400' : ($totals['net'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white') }}">
                {{ $money($totals['net']) }}
            </div>
        </flux:card>
    </div>

    {{-- Leaderboard --}}
    <flux:card class="p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                <thead>
                    <tr class="bg-gray-50 dark:bg-zinc-800">
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Fighter</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">Funnels</th>
                        @foreach([
                            $sortHeader('visitors', 'Visitors'),
                            $sortHeader('funnel_sales', 'Funnel sales'),
                            $sortHeader('manual_sales', 'Manual sales'),
                            $sortHeader('total_sales', 'Total sales'),
                            $sortHeader('total_orders', 'Orders'),
                            $sortHeader('spend', 'Ad spend'),
                            $sortHeader('roas', 'ROAS'),
                            $sortHeader('net', 'Net'),
                        ] as $col)
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider {{ $col['active'] ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-zinc-400' }}">
                                <button type="button" wire:click="sort('{{ $col['key'] }}')" class="inline-flex items-center gap-1 uppercase hover:text-gray-900 dark:hover:text-white">
                                    {{ $col['label'] }}
                                    @if($col['active'])
                                        <flux:icon.chevron-down variant="micro" />
                                    @endif
                                </button>
                            </th>
                        @endforeach
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-zinc-400">BM</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-zinc-700 dark:bg-transparent">
                    @forelse($rows as $i => $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800" wire:key="fighter-{{ $row['id'] }}">
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-zinc-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-zinc-100">{{ $row['name'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-zinc-400">{{ $row['email'] }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">
                                {{ $row['published_funnels'] }}<span class="text-gray-400"> / {{ $row['funnels'] }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">
                                {{ number_format($row['visitors']) }}
                                @if($row['conversion_rate'] !== null)
                                    <div class="text-xs text-gray-400">{{ $row['conversion_rate'] }}% CR</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">{{ $money($row['funnel_sales']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">{{ $money($row['manual_sales']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-zinc-100">{{ $money($row['total_sales']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">
                                {{ number_format($row['total_orders']) }}
                                <div class="text-xs text-gray-400">{{ $row['funnel_orders'] }} funnel · {{ $row['manual_orders'] }} manual</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 dark:text-zinc-300">
                                {{ $money($row['spend']) }}
                                @if($row['spend'] > 0)
                                    <div class="text-xs text-gray-400">+SST {{ $money($row['spend_with_sst']) }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium {{ $row['roas'] === null ? 'text-gray-400' : ($row['roas'] >= 1 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400') }}">
                                {{ $row['roas'] === null ? '—' : number_format($row['roas'], 2).'×' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium {{ $row['net'] > 0 ? 'text-green-600 dark:text-green-400' : ($row['net'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400') }}">
                                {{ $money($row['net']) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if($row['bm_error'] > 0)
                                    <flux:badge color="red" size="sm">Error</flux:badge>
                                @elseif($row['bm_connected'] > 0)
                                    <flux:badge color="green" size="sm">{{ $row['bm_connected'] }} linked</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">None</flux:badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-zinc-400">
                                No fighters found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <flux:text size="sm" class="mt-3">
        ROAS and Net use funnel sales against ad spend from each fighter's own Business Managers (8% SST). Manual sales are orders keyed in by the fighter outside a funnel.
    </flux:text>
</div>
