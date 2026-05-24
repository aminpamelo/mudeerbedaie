<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Services\Upsell\UpsellPaidOrdersQuery;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.teacher')] class extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        if (! auth()->user()?->isTeacher()) {
            abort(403, 'Unauthorized access');
        }

        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function getMyUpsellProperty(): array
    {
        $row = app(UpsellPaidOrdersQuery::class)
            ->forDateRange($this->dateFrom ?: null, $this->dateTo ?: null)
            ->byTeacher()
            ->firstWhere('teacher_id', auth()->id());

        return $row ?? [
            'teacher_id' => auth()->id(),
            'teacher_name' => auth()->user()?->name,
            'sessions_count' => 0,
            'paid_orders' => 0,
            'paid_revenue' => 0.0,
            'commission_earned' => 0.0,
            'commission_paid' => 0.0,
            'commission_pending' => 0.0,
            'top_products' => collect(),
        ];
    }

    public function getMyUpsellSessionsProperty()
    {
        return ClassSession::query()
            ->whereJsonContains('upsell_teacher_ids', auth()->id())
            ->when($this->dateFrom, fn ($q) => $q->whereDate('session_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('session_date', '<=', $this->dateTo))
            ->whereHas('funnelOrders', fn ($q) => $q->whereHas('productOrder', fn ($qq) => $qq
                ->where('payment_status', 'paid')
                ->whereNotIn('status', UpsellPaidOrdersQuery::EXCLUDED_ORDER_STATUSES)))
            ->withCount(['funnelOrders as paid_orders_count' => fn ($q) => $q->whereHas('productOrder', fn ($qq) => $qq
                ->where('payment_status', 'paid')
                ->whereNotIn('status', UpsellPaidOrdersQuery::EXCLUDED_ORDER_STATUSES))])
            ->withSum(['funnelOrders as paid_revenue_sum' => fn ($q) => $q->whereHas('productOrder', fn ($qq) => $qq
                ->where('payment_status', 'paid')
                ->whereNotIn('status', UpsellPaidOrdersQuery::EXCLUDED_ORDER_STATUSES))], 'funnel_revenue')
            ->with('class.course')
            ->orderByDesc('session_date')
            ->limit(50)
            ->get()
            ->map(function ($session) {
                $teacherCount = max(count($session->upsell_teacher_ids ?? []), 1);
                $session->teacher_share_revenue = ($session->paid_revenue_sum ?? 0) / $teacherCount;
                $session->teacher_share_commission = $session->teacher_share_revenue * ($session->upsell_teacher_commission_rate / 100);

                return $session;
            });
    }
}; ?>

@php
    $stats = $this->myUpsell;
    $sessions = $this->myUpsellSessions;
    $hasData = $stats['sessions_count'] > 0 || $stats['paid_orders'] > 0;
@endphp

<div class="teacher-app w-full space-y-6">
    {{-- ──────────────────────────────────────────────────────────
         HEADER  -  Title + date range filter
         ────────────────────────────────────────────────────────── --}}
    <div class="teacher-card p-5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="teacher-display text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">
                    Upsell Performance
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Paid orders only. Multi-teacher session revenue is split equally.
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:items-end">
                <flux:input type="date" wire:model.live="dateFrom" size="sm" label="From" />
                <flux:input type="date" wire:model.live="dateTo" size="sm" label="To" />
            </div>
        </div>
    </div>

    @if(! $hasData)
        {{-- Empty state --}}
        <div class="teacher-card p-10 text-center">
            <div class="inline-flex w-14 h-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-violet-400 text-white shadow-lg shadow-violet-500/30 mb-4">
                <flux:icon name="megaphone" class="w-7 h-7" />
            </div>
            <h3 class="teacher-display text-lg font-bold text-slate-900 dark:text-white">
                No upsell activity in this period
            </h3>
            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1 max-w-md mx-auto">
                When you're assigned as an upsell teacher and your students place paid orders, your performance will show up here.
            </p>
        </div>
    @else
        {{-- ──────────────────────────────────────────────────────────
             STAT CARDS  -  4 cards (2 cols mobile, 4 cols desktop)
             ────────────────────────────────────────────────────────── --}}
        <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
            {{-- Sessions with Upsell --}}
            <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-indigo teacher-stat-hover p-5">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/90">Sessions</span>
                    <div class="rounded-lg bg-violet-500/10 dark:bg-violet-400/15 p-1.5">
                        <flux:icon name="calendar-days" class="w-4 h-4 text-violet-600 dark:text-violet-300" />
                    </div>
                </div>
                <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">
                    {{ $stats['sessions_count'] }}
                </div>
                <div class="mt-1 text-xs text-violet-700/80 dark:text-violet-300/80 font-medium">
                    with upsell activity
                </div>
            </div>

            {{-- Paid Orders --}}
            <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-violet teacher-stat-hover p-5">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/90">Paid Orders</span>
                    <div class="rounded-lg bg-violet-500/10 dark:bg-violet-400/15 p-1.5">
                        <flux:icon name="shopping-bag" class="w-4 h-4 text-violet-600 dark:text-violet-300" />
                    </div>
                </div>
                <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">
                    {{ $stats['paid_orders'] }}
                </div>
                <div class="mt-1 text-xs text-violet-700/80 dark:text-violet-300/80 font-medium">
                    in selected period
                </div>
            </div>

            {{-- Paid Revenue --}}
            <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-emerald teacher-stat-hover p-5">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/90">Revenue</span>
                    <div class="rounded-lg bg-emerald-500/10 dark:bg-emerald-400/15 p-1.5">
                        <flux:icon name="banknotes" class="w-4 h-4 text-emerald-600 dark:text-emerald-300" />
                    </div>
                </div>
                <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">
                    <span class="text-base font-semibold text-emerald-700 dark:text-emerald-300 align-top">RM</span>
                    {{ number_format((float) $stats['paid_revenue'], 2) }}
                </div>
                <div class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-300/80 font-medium">
                    your share
                </div>
            </div>

            {{-- Commission Earned --}}
            <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-amber teacher-stat-hover p-5">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-700/80 dark:text-amber-300/90">Commission</span>
                    <div class="rounded-lg bg-amber-500/10 dark:bg-amber-400/15 p-1.5">
                        <flux:icon name="currency-dollar" class="w-4 h-4 text-amber-600 dark:text-amber-300" />
                    </div>
                </div>
                <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">
                    <span class="text-base font-semibold text-amber-700 dark:text-amber-300 align-top">RM</span>
                    {{ number_format((float) $stats['commission_earned'], 2) }}
                </div>
                <div class="mt-1 text-xs text-amber-700/80 dark:text-amber-300/80 font-medium">
                    earned
                </div>
            </div>
        </div>

        {{-- ──────────────────────────────────────────────────────────
             PAID / PENDING  -  Outstanding balance at a glance
             ────────────────────────────────────────────────────────── --}}
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2">
            {{-- Commission Paid --}}
            <div class="teacher-card p-5 relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-36 h-36 rounded-full bg-gradient-to-br from-emerald-400/30 to-teal-500/20 blur-2xl pointer-events-none"></div>
                <div class="relative flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/90">
                            Commission Paid
                        </div>
                        <div class="teacher-display teacher-num text-2xl sm:text-3xl font-bold bg-gradient-to-r from-emerald-600 to-teal-600 dark:from-emerald-400 dark:to-teal-300 bg-clip-text text-transparent mt-1">
                            RM {{ number_format((float) $stats['commission_paid'], 2) }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                            Already paid out
                        </div>
                    </div>
                    <div class="rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white p-3 shadow-md shadow-emerald-500/30">
                        <flux:icon name="check-circle" class="w-6 h-6" />
                    </div>
                </div>
            </div>

            {{-- Commission Pending --}}
            <div class="teacher-card p-5 relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-36 h-36 rounded-full bg-gradient-to-br from-amber-400/30 to-orange-500/20 blur-2xl pointer-events-none"></div>
                <div class="relative flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-amber-700/80 dark:text-amber-300/90">
                            Commission Pending
                        </div>
                        <div class="teacher-display teacher-num text-2xl sm:text-3xl font-bold bg-gradient-to-r from-amber-600 to-orange-600 dark:from-amber-400 dark:to-orange-300 bg-clip-text text-transparent mt-1">
                            RM {{ number_format((float) $stats['commission_pending'], 2) }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                            Outstanding balance
                        </div>
                    </div>
                    <div class="rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-white p-3 shadow-md shadow-amber-500/30">
                        <flux:icon name="clock" class="w-6 h-6" />
                    </div>
                </div>
            </div>
        </div>

        {{-- ──────────────────────────────────────────────────────────
             TOP PRODUCTS
             ────────────────────────────────────────────────────────── --}}
        @if($stats['top_products']->isNotEmpty())
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">Top Products</h3>
                    <flux:icon name="trophy" class="w-4 h-4 text-amber-500" />
                </div>
                <div class="space-y-2">
                    @foreach($stats['top_products'] as $i => $product)
                        <div wire:key="product-{{ $product['product_id'] ?? $i }}"
                             class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="shrink-0 inline-flex w-7 h-7 items-center justify-center rounded-lg bg-gradient-to-br from-violet-600 to-violet-400 text-white text-xs font-bold shadow-sm">
                                    {{ $i + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                                        {{ $product['product_name'] }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-zinc-400">
                                        {{ $product['units'] }} {{ Str::plural('unit', $product['units']) }} sold
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="teacher-num text-sm font-bold text-emerald-700 dark:text-emerald-300">
                                    RM {{ number_format((float) $product['revenue'], 2) }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ──────────────────────────────────────────────────────────
             SESSIONS TABLE
             ────────────────────────────────────────────────────────── --}}
        <div class="teacher-card p-5 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">
                    Upsell Sessions ({{ $sessions->count() }})
                </h3>
            </div>

            @if($sessions->isEmpty())
                <p class="text-sm text-slate-500 dark:text-zinc-400">No upsell sessions in the selected period.</p>
            @else
                {{-- Mobile: stacked cards --}}
                <div class="space-y-3 sm:hidden">
                    @foreach($sessions as $session)
                        <div wire:key="session-mobile-{{ $session->id }}"
                             class="rounded-xl border border-slate-200 dark:border-zinc-800 p-3.5">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-xs font-semibold text-slate-500 dark:text-zinc-400">
                                    {{ $session->session_date?->format('M j, Y') }}
                                </div>
                                <div class="text-xs font-medium text-violet-700 dark:text-violet-300">
                                    {{ number_format((float) $session->upsell_teacher_commission_rate, 2) }}% rate
                                </div>
                            </div>
                            <div class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                                {{ $session->class?->title ?? '—' }}
                            </div>
                            @if($session->class?->course)
                                <div class="text-xs text-slate-500 dark:text-zinc-400 truncate">
                                    {{ $session->class->course->title ?? $session->class->course->name }}
                                </div>
                            @endif
                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                <div>
                                    <div class="text-[10px] uppercase font-semibold text-slate-500 dark:text-zinc-500">Orders</div>
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $session->paid_orders_count }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase font-semibold text-slate-500 dark:text-zinc-500">Your Rev</div>
                                    <div class="font-bold text-emerald-700 dark:text-emerald-300">RM {{ number_format((float) $session->teacher_share_revenue, 2) }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase font-semibold text-slate-500 dark:text-zinc-500">Your Comm.</div>
                                    <div class="font-bold text-amber-700 dark:text-amber-300">RM {{ number_format((float) $session->teacher_share_commission, 2) }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Desktop: table --}}
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-zinc-700 text-xs uppercase text-slate-500 dark:text-zinc-400">
                                <th class="text-left py-2 px-3 font-semibold">Date</th>
                                <th class="text-left py-2 px-3 font-semibold">Class</th>
                                <th class="text-right py-2 px-3 font-semibold">Paid Orders</th>
                                <th class="text-right py-2 px-3 font-semibold">Paid Revenue</th>
                                <th class="text-right py-2 px-3 font-semibold">Rate</th>
                                <th class="text-right py-2 px-3 font-semibold">Your Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sessions as $session)
                                <tr wire:key="session-desk-{{ $session->id }}"
                                    class="border-b border-slate-100 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/40">
                                    <td class="py-2.5 px-3 text-slate-900 dark:text-white">
                                        {{ $session->session_date?->format('Y-m-d') }}
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="font-medium text-slate-900 dark:text-white truncate max-w-xs">
                                            {{ $session->class?->title ?? '—' }}
                                        </div>
                                        @if($session->class?->course)
                                            <div class="text-xs text-slate-500 dark:text-zinc-400 truncate max-w-xs">
                                                {{ $session->class->course->title ?? $session->class->course->name }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-right py-2.5 px-3 text-slate-900 dark:text-white">{{ $session->paid_orders_count }}</td>
                                    <td class="text-right py-2.5 px-3 text-slate-900 dark:text-white">RM {{ number_format((float) ($session->paid_revenue_sum ?? 0), 2) }}</td>
                                    <td class="text-right py-2.5 px-3 text-violet-700 dark:text-violet-300 font-medium">
                                        {{ number_format((float) $session->upsell_teacher_commission_rate, 2) }}%
                                    </td>
                                    <td class="text-right py-2.5 px-3">
                                        <div class="font-bold text-emerald-700 dark:text-emerald-300">
                                            RM {{ number_format((float) $session->teacher_share_revenue, 2) }}
                                        </div>
                                        <div class="text-xs text-amber-700 dark:text-amber-300">
                                            + RM {{ number_format((float) $session->teacher_share_commission, 2) }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
