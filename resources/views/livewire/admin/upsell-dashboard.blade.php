<?php

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\Funnel;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $filterClassId = '';
    public string $filterFunnelId = '';
    public string $filterPicId = '';

    public function mount(): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
    }

    public function getOverallStatsProperty(): array
    {
        $sessionsQuery = ClassSession::query()
            ->whereNotNull('upsell_funnel_ids')
            ->when($this->dateFrom, fn ($q) => $q->where('session_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->where('session_date', '<=', $this->dateTo))
            ->when($this->filterClassId, fn ($q) => $q->where('class_id', $this->filterClassId))
            ->when($this->filterFunnelId, fn ($q) => $q->whereJsonContains('upsell_funnel_ids', (int) $this->filterFunnelId))
            ->when($this->filterPicId, fn ($q) => $q->whereJsonContains('upsell_pic_user_ids', (int) $this->filterPicId));

        $sessionIds = $sessionsQuery->clone()->pluck('id');
        $totalSessions = $sessionsQuery->count();

        $orders = FunnelOrder::whereNotNull('class_session_id')
            ->whereIn('class_session_id', $sessionIds)
            ->get();

        $visitors = FunnelSession::whereNotNull('class_session_id')
            ->whereIn('class_session_id', $sessionIds)
            ->count();

        $totalConversions = $orders->count();
        $totalRevenue = $orders->sum('funnel_revenue');
        $conversionRate = $visitors > 0 ? round(($totalConversions / $visitors) * 100, 1) : 0;

        $totalCommission = ClassSession::query()
            ->whereNotNull('upsell_funnel_ids')
            ->whereNotNull('upsell_teacher_commission_rate')
            ->when($this->dateFrom, fn ($q) => $q->where('session_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->where('session_date', '<=', $this->dateTo))
            ->when($this->filterClassId, fn ($q) => $q->where('class_id', $this->filterClassId))
            ->get()
            ->sum(function ($session) use ($orders) {
                $sessionRevenue = $orders->where('class_session_id', $session->id)->sum('funnel_revenue');

                return $sessionRevenue * ($session->upsell_teacher_commission_rate / 100);
            });

        return [
            'total_sessions' => $totalSessions,
            'visitors' => $visitors,
            'total_conversions' => $totalConversions,
            'total_revenue' => $totalRevenue,
            'conversion_rate' => $conversionRate,
            'total_commission' => $totalCommission,
        ];
    }

    public function getClassBreakdownProperty(): \Illuminate\Support\Collection
    {
        return ClassModel::query()
            ->whereHas('sessions', function ($q) {
                $q->whereNotNull('upsell_funnel_ids')
                    ->when($this->dateFrom, fn ($q) => $q->where('session_date', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q) => $q->where('session_date', '<=', $this->dateTo));
            })
            ->with(['course:id,name', 'teacher.user:id,name'])
            ->withCount(['sessions as upsell_sessions_count' => function ($q) {
                $q->whereNotNull('upsell_funnel_ids')
                    ->when($this->dateFrom, fn ($q2) => $q2->where('session_date', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q2) => $q2->where('session_date', '<=', $this->dateTo));
            }])
            ->get()
            ->map(function ($class) {
                $sessionIds = $class->sessions()
                    ->whereNotNull('upsell_funnel_ids')
                    ->when($this->dateFrom, fn ($q) => $q->where('session_date', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q) => $q->where('session_date', '<=', $this->dateTo))
                    ->pluck('id');

                $orders = FunnelOrder::whereIn('class_session_id', $sessionIds)->get();
                $visitors = FunnelSession::whereIn('class_session_id', $sessionIds)->count();

                $class->upsell_orders = $orders->count();
                $class->upsell_revenue = $orders->sum('funnel_revenue');
                $class->upsell_visitors = $visitors;

                return $class;
            })
            ->sortByDesc('upsell_revenue')
            ->values();
    }

    public function getFunnelBreakdownProperty(): \Illuminate\Support\Collection
    {
        $sessionIds = ClassSession::query()
            ->whereNotNull('upsell_funnel_ids')
            ->when($this->dateFrom, fn ($q) => $q->where('session_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->where('session_date', '<=', $this->dateTo))
            ->when($this->filterClassId, fn ($q) => $q->where('class_id', $this->filterClassId))
            ->pluck('id');

        return Funnel::query()
            ->whereHas('orders', fn ($q) => $q->whereIn('class_session_id', $sessionIds))
            ->withCount(['orders as upsell_orders' => fn ($q) => $q->whereIn('class_session_id', $sessionIds)])
            ->withSum(['orders as upsell_revenue' => fn ($q) => $q->whereIn('class_session_id', $sessionIds)], 'funnel_revenue')
            ->orderByDesc('upsell_revenue')
            ->get();
    }

    public function getPicBreakdownProperty(): \Illuminate\Support\Collection
    {
        $sessions = ClassSession::query()
            ->whereNotNull('upsell_pic_user_ids')
            ->when($this->dateFrom, fn ($q) => $q->where('session_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->where('session_date', '<=', $this->dateTo))
            ->when($this->filterClassId, fn ($q) => $q->where('class_id', $this->filterClassId))
            ->get();

        $picData = [];

        foreach ($sessions as $session) {
            foreach ($session->upsell_pic_user_ids ?? [] as $userId) {
                if (! isset($picData[$userId])) {
                    $picData[$userId] = [
                        'user_id' => $userId,
                        'sessions_count' => 0,
                        'session_ids' => [],
                    ];
                }
                $picData[$userId]['sessions_count']++;
                $picData[$userId]['session_ids'][] = $session->id;
            }
        }

        $users = User::whereIn('id', array_keys($picData))->get()->keyBy('id');

        return collect($picData)->map(function ($data) use ($users) {
            $orders = FunnelOrder::whereIn('class_session_id', $data['session_ids'])->get();

            return (object) [
                'user' => $users->get($data['user_id']),
                'sessions_count' => $data['sessions_count'],
                'orders_count' => $orders->count(),
                'revenue' => $orders->sum('funnel_revenue'),
            ];
        })
            ->sortByDesc('revenue')
            ->values();
    }

    public function getAvailableClassesProperty()
    {
        return ClassModel::query()
            ->whereHas('sessions', fn ($q) => $q->whereNotNull('upsell_funnel_ids'))
            ->with('course:id,name')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => ($c->course?->name ?? 'Unknown').' - '.$c->title]);
    }

    public function getAvailableFunnelsProperty()
    {
        return Funnel::where('status', 'published')->orderBy('name')->get(['id', 'name']);
    }

    public function getAvailablePicsProperty()
    {
        return User::whereIn('role', ['admin', 'class_admin', 'sales', 'employee'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function resetFilters(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
        $this->filterClassId = '';
        $this->filterFunnelId = '';
        $this->filterPicId = '';
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Upsell Dashboard</flux:heading>
            <flux:text class="mt-2">Monitor upsell performance across all classes</flux:text>
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 mb-6">
        <div class="flex items-end gap-3 flex-wrap">
            <div class="w-40 shrink-0">
                <flux:input type="date" wire:model.live="dateFrom" label="From" size="sm" />
            </div>
            <div class="w-40 shrink-0">
                <flux:input type="date" wire:model.live="dateTo" label="To" size="sm" />
            </div>
            <div class="w-48 shrink-0">
                <flux:select wire:model.live="filterClassId" label="Class" size="sm">
                    <flux:select.option value="">All Classes</flux:select.option>
                    @foreach($this->availableClasses as $cls)
                        <flux:select.option value="{{ $cls['id'] }}">{{ $cls['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-40 shrink-0">
                <flux:select wire:model.live="filterFunnelId" label="Funnel" size="sm">
                    <flux:select.option value="">All Funnels</flux:select.option>
                    @foreach($this->availableFunnels as $funnel)
                        <flux:select.option value="{{ $funnel->id }}">{{ $funnel->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-40 shrink-0">
                <flux:select wire:model.live="filterPicId" label="PIC" size="sm">
                    <flux:select.option value="">All PICs</flux:select.option>
                    @foreach($this->availablePics as $pic)
                        <flux:select.option value="{{ $pic->id }}">{{ $pic->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button variant="ghost" size="sm" wire:click="resetFilters">Reset</flux:button>
        </div>
    </div>

    {{-- Stats Cards --}}
    @php $stats = $this->overallStats; @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sessions with Upsell</span>
            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">{{ $stats['total_sessions'] }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Visitors</span>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1 tabular-nums">{{ $stats['visitors'] }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Conversions</span>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 tabular-nums">{{ $stats['total_conversions'] }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Revenue</span>
            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">RM {{ number_format($stats['total_revenue'], 2) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Conversion Rate</span>
            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">{{ $stats['conversion_rate'] }}%</p>
        </div>
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total Commission</span>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1 tabular-nums">RM {{ number_format($stats['total_commission'], 2) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Class Breakdown --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Performance by Class</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Revenue and conversions per class</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                            <th class="text-left py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Class</th>
                            <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sessions</th>
                            <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Visitors</th>
                            <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Orders</th>
                            <th class="text-right py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($this->classBreakdown as $class)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                                <td class="py-2.5 px-4">
                                    <a href="{{ route('classes.show', $class) }}?tab=upsell" class="group" wire:navigate>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $class->title }}</p>
                                        <p class="text-[11px] text-zinc-400">{{ $class->course?->name }}</p>
                                    </a>
                                </td>
                                <td class="py-2.5 px-3 text-right text-sm text-zinc-600 dark:text-zinc-400 tabular-nums">{{ $class->upsell_sessions_count }}</td>
                                <td class="py-2.5 px-3 text-right text-sm text-blue-600 dark:text-blue-400 tabular-nums">{{ $class->upsell_visitors }}</td>
                                <td class="py-2.5 px-3 text-right text-sm text-zinc-600 dark:text-zinc-400 tabular-nums">{{ $class->upsell_orders }}</td>
                                <td class="py-2.5 px-4 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100 tabular-nums whitespace-nowrap">RM {{ number_format($class->upsell_revenue, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center">
                                    <p class="text-xs text-zinc-400">No classes with upsell data in this period</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Funnel Breakdown --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Performance by Funnel</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Which funnels are generating the most revenue</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                            <th class="text-left py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Funnel</th>
                            <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Orders</th>
                            <th class="text-right py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($this->funnelBreakdown as $funnel)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                                <td class="py-2.5 px-4">
                                    <div class="flex items-center gap-2">
                                        <span class="flex items-center justify-center w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-500/20 shrink-0">
                                            <flux:icon name="funnel" class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                                        </span>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $funnel->name }}</p>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-right text-sm text-zinc-600 dark:text-zinc-400 tabular-nums">{{ $funnel->upsell_orders }}</td>
                                <td class="py-2.5 px-4 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100 tabular-nums whitespace-nowrap">RM {{ number_format($funnel->upsell_revenue ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-8 text-center">
                                    <p class="text-xs text-zinc-400">No funnel conversions in this period</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- PIC Performance --}}
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
        <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Performance by PIC</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Staff performance in sharing and converting upsell funnels</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                        <th class="text-left py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">PIC</th>
                        <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sessions Assigned</th>
                        <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Orders</th>
                        <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Revenue</th>
                        <th class="text-right py-2 px-4 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Avg Revenue / Session</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($this->picBreakdown as $pic)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                            <td class="py-2.5 px-4">
                                <div class="flex items-center gap-2">
                                    <span class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-semibold shrink-0">
                                        {{ strtoupper(substr($pic->user?->name ?? '?', 0, 1)) }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $pic->user?->name ?? 'Unknown' }}</p>
                                        <p class="text-[11px] text-zinc-400">{{ ucfirst(str_replace('_', ' ', $pic->user?->role ?? '')) }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-2.5 px-3 text-right text-sm text-zinc-600 dark:text-zinc-400 tabular-nums">{{ $pic->sessions_count }}</td>
                            <td class="py-2.5 px-3 text-right text-sm text-zinc-600 dark:text-zinc-400 tabular-nums">{{ $pic->orders_count }}</td>
                            <td class="py-2.5 px-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100 tabular-nums whitespace-nowrap">RM {{ number_format($pic->revenue, 2) }}</td>
                            <td class="py-2.5 px-4 text-right text-sm text-zinc-600 dark:text-zinc-400 tabular-nums whitespace-nowrap">
                                RM {{ $pic->sessions_count > 0 ? number_format($pic->revenue / $pic->sessions_count, 2) : '0.00' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center">
                                <p class="text-xs text-zinc-400">No PIC activity in this period</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
