<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Payslip;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;
    
    public string $statusFilter = 'all';
    public string $monthFilter = '';
    
    protected $queryString = [
        'statusFilter' => ['except' => 'all'],
        'monthFilter' => ['except' => ''],
    ];
    
    public function with()
    {
        // Get payslips for current teacher
        $query = Payslip::with(['generatedBy', 'sessions.class.course'])
                        ->where('teacher_id', auth()->id());
        
        // Apply filters
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        if ($this->monthFilter) {
            $query->where('month', $this->monthFilter);
        }
        
        $payslips = $query->orderBy('year', 'desc')
                         ->orderBy('month', 'desc')
                         ->orderBy('created_at', 'desc')
                         ->paginate(10);
        
        // Get available months for filter
        $availableMonths = Payslip::where('teacher_id', auth()->id())
                                  ->selectRaw('month, MAX(year) as year')
                                  ->groupBy('month')
                                  ->orderBy('year', 'desc')
                                  ->orderBy('month', 'desc')
                                  ->get()
                                  ->map(function ($payslip) {
                                      $carbon = Carbon::createFromFormat('Y-m', $payslip->month);
                                      return [
                                          'value' => $payslip->month,
                                          'label' => $carbon->format('F Y'),
                                      ];
                                  });
        
        // Calculate statistics
        $totalEarnings = Payslip::where('teacher_id', auth()->id())->sum('total_amount');
        $paidEarnings = Payslip::where('teacher_id', auth()->id())->where('status', 'paid')->sum('total_amount');
        $pendingEarnings = Payslip::where('teacher_id', auth()->id())->whereIn('status', ['draft', 'finalized'])->sum('total_amount');
        $totalSessions = Payslip::where('teacher_id', auth()->id())->sum('total_sessions');
        
        $statistics = [
            'total_earnings' => $totalEarnings,
            'paid_earnings' => $paidEarnings,
            'pending_earnings' => $pendingEarnings,
            'total_sessions' => $totalSessions,
        ];
        
        return [
            'payslips' => $payslips,
            'availableMonths' => $availableMonths,
            'statistics' => $statistics
        ];
    }
    
    public function updatedStatusFilter()
    {
        $this->resetPage();
    }
    
    public function updatedMonthFilter()
    {
        $this->resetPage();
    }
}; ?>

@php
    $currentYear = now()->year;
    $yearTotal = \App\Models\Payslip::query()
        ->where('teacher_id', auth()->id())
        ->where('status', 'paid')
        ->where('year', $currentYear)
        ->sum('total_amount');
    $paidPayslipCount = \App\Models\Payslip::query()
        ->where('teacher_id', auth()->id())
        ->where('status', 'paid')
        ->where('year', $currentYear)
        ->count();
@endphp

<div class="teacher-app w-full">
    {{-- Page header --}}
    <x-teacher.page-header
        title="My Payslips"
        subtitle="Monthly earnings summary"
    />

    {{-- Hero year-to-date earnings card --}}
    <div class="teacher-card mb-6 p-6 sm:p-7 relative overflow-hidden">
        <div class="absolute -top-12 -right-12 w-44 h-44 rounded-full bg-gradient-to-br from-emerald-400/30 to-teal-500/20 blur-2xl pointer-events-none"></div>
        <div class="absolute -bottom-16 -left-12 w-40 h-40 rounded-full bg-gradient-to-br from-violet-400/20 to-violet-500/10 blur-2xl pointer-events-none"></div>
        <div class="relative">
            <div class="flex items-center gap-2 mb-1">
                <div class="text-xs font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/90">
                    Year-to-date earnings
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-500/10 dark:bg-emerald-400/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                    {{ $currentYear }}
                </span>
            </div>
            <div class="teacher-display teacher-num text-4xl sm:text-5xl font-bold bg-gradient-to-r from-violet-600 via-emerald-600 to-teal-600 dark:from-violet-400 dark:via-emerald-400 dark:to-teal-300 bg-clip-text text-transparent">
                RM {{ number_format($yearTotal, 2) }}
            </div>
            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1.5">
                Across {{ $paidPayslipCount }} paid {{ Str::plural('payslip', $paidPayslipCount) }}
            </p>
        </div>
    </div>

    {{-- Stat strip --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-teacher.stat-card
            eyebrow="Total Earnings"
            value="RM {{ number_format($statistics['total_earnings'], 2) }}"
            tone="emerald"
            icon="banknotes"
        >
            <span class="font-medium text-emerald-700/80 dark:text-emerald-300/80">All-time gross</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Paid"
            value="RM {{ number_format($statistics['paid_earnings'], 2) }}"
            tone="indigo"
            icon="check-circle"
        >
            <span class="font-medium text-violet-700/80 dark:text-violet-300/80">Paid out to date</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Pending"
            value="RM {{ number_format($statistics['pending_earnings'], 2) }}"
            tone="amber"
            icon="clock"
        >
            <span class="font-medium text-amber-700/80 dark:text-amber-300/80">Awaiting payout</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Total Sessions"
            value="{{ $statistics['total_sessions'] }}"
            tone="violet"
            icon="academic-cap"
        >
            <span class="font-medium text-violet-700/80 dark:text-violet-300/80">Across all payslips</span>
        </x-teacher.stat-card>
    </div>

    {{-- Filter bar --}}
    <x-teacher.filter-bar>
        <div class="flex-1 min-w-[180px]">
            <flux:select wire:model.live="statusFilter" placeholder="All Status">
                <option value="all">All Status</option>
                <option value="draft">Draft</option>
                <option value="finalized">Finalized</option>
                <option value="paid">Paid</option>
            </flux:select>
        </div>

        <div class="flex-1 min-w-[180px]">
            <flux:select wire:model.live="monthFilter" placeholder="All Months">
                <option value="">All Months</option>
                @foreach($availableMonths as $month)
                    <option value="{{ $month['value'] }}">{{ $month['label'] }}</option>
                @endforeach
            </flux:select>
        </div>

        @if($statusFilter !== 'all' || $monthFilter)
            <x-slot name="actions">
                <button
                    type="button"
                    wire:click="$set('statusFilter', 'all'); $set('monthFilter', '')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 dark:text-zinc-300 ring-1 ring-slate-200 dark:ring-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 transition"
                >
                    <flux:icon name="x-mark" class="w-3.5 h-3.5" />
                    Clear filters
                </button>
            </x-slot>
        @endif
    </x-teacher.filter-bar>

    @if($payslips->count() > 0)
        {{-- Payslip list --}}
        <div class="space-y-3">
            @foreach($payslips as $payslip)
                @php
                    $statusKey = $payslip->status === 'draft' ? 'pending' : ($payslip->status === 'finalized' ? 'pending' : ($payslip->status === 'paid' ? 'paid' : $payslip->status));
                    $statusLabel = $payslip->status === 'draft' ? 'Draft' : ($payslip->status === 'finalized' ? 'Finalized' : 'Paid');
                    try {
                        $period = \Carbon\Carbon::createFromFormat('Y-m', $payslip->month);
                        $monthLabel = $period->format('F');
                        $yearLabel = $period->format('Y');
                        $periodRange = $period->copy()->startOfMonth()->format('j M') . ' – ' . $period->copy()->endOfMonth()->format('j M Y');
                    } catch (\Exception $e) {
                        $monthLabel = $payslip->formatted_month;
                        $yearLabel = '';
                        $periodRange = '';
                    }
                @endphp
                <a
                    wire:key="payslip-{{ $payslip->id }}"
                    href="{{ route('teacher.payslips.show', $payslip) }}"
                    wire:navigate
                    class="teacher-card teacher-card-hover group flex flex-col sm:flex-row sm:items-center gap-4 p-5"
                >
                    {{-- Left: month/year --}}
                    <div class="flex items-center gap-4 sm:w-56 shrink-0">
                        <div class="shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500/15 to-violet-400/10 dark:from-violet-400/20 dark:to-violet-500/10 ring-1 ring-violet-200/60 dark:ring-violet-800/40 flex items-center justify-center">
                            <flux:icon name="banknotes" class="w-5 h-5 text-violet-600 dark:text-violet-300" />
                        </div>
                        <div class="min-w-0">
                            <div class="teacher-display text-lg font-bold text-slate-900 dark:text-white leading-tight truncate">
                                {{ $monthLabel }} <span class="text-slate-400 dark:text-zinc-500 font-semibold">{{ $yearLabel }}</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5 truncate">
                                @if($periodRange)
                                    {{ $periodRange }}
                                @else
                                    Generated {{ $payslip->generated_at->format('M d, Y') }}
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Center: status + sessions --}}
                    <div class="flex flex-1 flex-wrap items-center gap-3 min-w-0">
                        <x-teacher.status-pill :status="$statusKey" :label="$statusLabel" />

                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-zinc-400">
                            <flux:icon name="academic-cap" class="w-3.5 h-3.5" />
                            {{ $payslip->total_sessions }} {{ Str::plural('session', $payslip->total_sessions) }}
                        </span>

                        @if($payslip->status === 'paid' && $payslip->paid_at)
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                <flux:icon name="check-circle" class="w-3.5 h-3.5" />
                                Paid {{ $payslip->paid_at->format('j M Y') }}
                            </span>
                        @endif
                    </div>

                    {{-- Right: amount + chevron --}}
                    <div class="flex items-center gap-4 shrink-0 sm:justify-end">
                        <div class="text-right">
                            <div class="teacher-display teacher-num text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                                <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-300 align-top">RM</span> {{ number_format($payslip->total_amount, 2) }}
                            </div>
                            <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">
                                Generated {{ $payslip->generated_at->format('j M') }}
                            </p>
                        </div>
                        <div class="shrink-0 w-9 h-9 rounded-xl bg-slate-100/70 dark:bg-zinc-800/60 ring-1 ring-slate-200/70 dark:ring-zinc-700 group-hover:bg-violet-500 group-hover:ring-violet-500 dark:group-hover:bg-violet-500 transition flex items-center justify-center">
                            <flux:icon name="arrow-right" class="w-4 h-4 text-slate-500 dark:text-zinc-300 group-hover:text-white transition" />
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $payslips->links() }}
        </div>
    @else
        {{-- Empty state --}}
        @if($statusFilter !== 'all' || $monthFilter)
            <x-teacher.empty-state
                icon="banknotes"
                title="No payslips found"
                message="No payslips match your current filter criteria. Try adjusting your filters."
            >
                <button
                    type="button"
                    wire:click="$set('statusFilter', 'all'); $set('monthFilter', '')"
                    class="teacher-cta"
                >
                    <flux:icon name="sparkles" class="w-4 h-4" />
                    Clear all filters
                </button>
            </x-teacher.empty-state>
        @else
            <x-teacher.empty-state
                icon="banknotes"
                title="No payslips yet"
                message="Payslips will appear here once you have completed and verified sessions for a finalized month."
            />
        @endif
    @endif
</div>