<?php

use App\Models\Funnel;
use App\Models\FunnelAnalytics;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use Livewire\Volt\Component;

new class extends Component {
    public Funnel $funnel;
    public string $period = '7d';

    public function mount(Funnel $funnel): void
    {
        $this->funnel = $funnel->load(['steps' => fn ($q) => $q->orderBy('sort_order')]);
    }

    public function with(): array
    {
        $startDate = match ($this->period) {
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(7),
        };

        // Get analytics for the period
        $analytics = FunnelAnalytics::where('funnel_id', $this->funnel->id)
            ->whereNull('step_id')
            ->where('date', '>=', $startDate->toDateString())
            ->get();

        // Calculate summary metrics
        $summary = [
            'total_visitors' => $analytics->sum('unique_visitors'),
            'total_pageviews' => $analytics->sum('pageviews'),
            'total_conversions' => $analytics->sum('conversions'),
            'total_revenue' => $analytics->sum('revenue'),
            'conversion_rate' => $analytics->sum('unique_visitors') > 0
                ? round(($analytics->sum('conversions') / $analytics->sum('unique_visitors')) * 100, 2)
                : 0,
            'avg_time_on_page' => round($analytics->avg('avg_time_seconds') ?? 0),
        ];

        // Get step-level analytics
        $stepAnalytics = [];
        foreach ($this->funnel->steps as $step) {
            $stepData = FunnelAnalytics::where('funnel_id', $this->funnel->id)
                ->where('step_id', $step->id)
                ->where('date', '>=', $startDate->toDateString())
                ->get();

            $stepAnalytics[$step->id] = [
                'visitors' => $stepData->sum('unique_visitors'),
                'pageviews' => $stepData->sum('pageviews'),
                'conversions' => $stepData->sum('conversions'),
                'revenue' => $stepData->sum('revenue'),
            ];
        }

        // Recent orders
        $recentOrders = FunnelOrder::where('funnel_id', $this->funnel->id)
            ->with(['productOrder', 'session'])
            ->latest()
            ->limit(10)
            ->get();

        // Recent sessions
        $recentSessions = FunnelSession::where('funnel_id', $this->funnel->id)
            ->with('currentStep')
            ->latest()
            ->limit(10)
            ->get();

        // Daily chart data
        $chartData = $analytics->groupBy('date')->map(fn ($day) => [
            'date' => $day->first()->date,
            'visitors' => $day->sum('unique_visitors'),
            'conversions' => $day->sum('conversions'),
            'revenue' => $day->sum('revenue'),
        ])->values();

        return [
            'summary' => $summary,
            'stepAnalytics' => $stepAnalytics,
            'recentOrders' => $recentOrders,
            'recentSessions' => $recentSessions,
            'chartData' => $chartData,
        ];
    }

    public function publish(): void
    {
        $this->funnel->publish();
        $this->dispatch('funnel-published');
    }

    public function unpublish(): void
    {
        $this->funnel->unpublish();
        $this->dispatch('funnel-unpublished');
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <flux:button variant="ghost" href="{{ route('admin.funnels') }}" icon="arrow-left" size="sm">
                    Back
                </flux:button>
            </div>
            <flux:heading size="xl">{{ $funnel->name }}</flux:heading>
            <div class="flex items-center gap-4 mt-2">
                <flux:badge size="sm" color="{{ match($funnel->status) {
                    'published' => 'green',
                    'draft' => 'yellow',
                    'archived' => 'zinc',
                    default => 'zinc'
                } }}">
                    {{ ucfirst($funnel->status) }}
                </flux:badge>
                <flux:text class="text-gray-500">
                    <a href="{{ url('/f/'.$funnel->slug) }}" target="_blank" class="hover:text-blue-600">
                        {{ url('/f/'.$funnel->slug) }}
                    </a>
                </flux:text>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="outline" href="{{ route('funnel-builder.index') }}?funnel={{ $funnel->uuid }}" icon="pencil-square">
                Edit
            </flux:button>
            @if($funnel->status === 'draft')
                <flux:button variant="primary" wire:click="publish" icon="rocket-launch">
                    Publish
                </flux:button>
            @else
                <flux:button variant="ghost" wire:click="unpublish" icon="pause">
                    Unpublish
                </flux:button>
            @endif
        </div>
    </div>

    <!-- Period Filter -->
    <div class="mb-6">
        <flux:select wire:model.live="period" class="w-40">
            <option value="24h">Last 24 Hours</option>
            <option value="7d">Last 7 Days</option>
            <option value="30d">Last 30 Days</option>
            <option value="90d">Last 90 Days</option>
        </flux:select>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <flux:card class="p-4">
            <flux:text class="text-sm text-gray-500 dark:text-gray-400">Visitors</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($summary['total_visitors']) }}</flux:heading>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="text-sm text-gray-500 dark:text-gray-400">Page Views</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($summary['total_pageviews']) }}</flux:heading>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="text-sm text-gray-500 dark:text-gray-400">Conversions</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($summary['total_conversions']) }}</flux:heading>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="text-sm text-gray-500 dark:text-gray-400">Revenue</flux:text>
            <flux:heading size="xl" class="mt-1">RM {{ number_format($summary['total_revenue'], 2) }}</flux:heading>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="text-sm text-gray-500 dark:text-gray-400">Conversion Rate</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $summary['conversion_rate'] }}%</flux:heading>
        </flux:card>

        <flux:card class="p-4">
            <flux:text class="text-sm text-gray-500 dark:text-gray-400">Avg. Time</flux:text>
            <flux:heading size="xl" class="mt-1">{{ gmdate('i:s', $summary['avg_time_on_page']) }}</flux:heading>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Funnel Steps Performance -->
        <flux:card>
            <div class="p-6 border-b border-gray-200 dark:border-zinc-700">
                <flux:heading size="lg">Funnel Steps</flux:heading>
                <flux:text class="text-sm text-gray-500">Performance by step</flux:text>
            </div>
            <div class="p-6">
                @if($funnel->steps->isEmpty())
                    <div class="text-center py-8 text-gray-500">
                        No steps configured yet
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($funnel->steps as $index => $step)
                            @php
                                $stats = $stepAnalytics[$step->id] ?? ['visitors' => 0, 'pageviews' => 0, 'conversions' => 0, 'revenue' => 0];
                                $prevStats = $index > 0 ? ($stepAnalytics[$funnel->steps[$index - 1]->id] ?? ['visitors' => 0]) : null;
                                $dropoff = $prevStats && $prevStats['visitors'] > 0
                                    ? round((1 - ($stats['visitors'] / $prevStats['visitors'])) * 100, 1)
                                    : 0;
                            @endphp
                            <div class="flex items-center gap-4">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm font-semibold">
                                    {{ $index + 1 }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $step->name }}</div>
                                            <div class="text-sm text-gray-500">{{ ucfirst($step->type) }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ number_format($stats['visitors']) }} visitors
                                            </div>
                                            @if($dropoff > 0)
                                                <div class="text-sm text-red-500">
                                                    -{{ $dropoff }}% drop-off
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    @if($index < $funnel->steps->count() - 1)
                                        <div class="mt-2 h-1 bg-gray-200 dark:bg-zinc-700 rounded">
                                            @php
                                                $nextStats = $stepAnalytics[$funnel->steps[$index + 1]->id] ?? ['visitors' => 0];
                                                $progression = $stats['visitors'] > 0 ? ($nextStats['visitors'] / $stats['visitors']) * 100 : 0;
                                            @endphp
                                            <div class="h-1 bg-blue-500 rounded" style="width: {{ min($progression, 100) }}%"></div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </flux:card>

        <!-- Recent Orders -->
        <flux:card>
            <div class="p-6 border-b border-gray-200 dark:border-zinc-700">
                <flux:heading size="lg">Recent Orders</flux:heading>
                <flux:text class="text-sm text-gray-500">Latest purchases from this funnel</flux:text>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-zinc-700">
                @forelse($recentOrders as $order)
                    <div class="p-4 flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $order->productOrder?->order_number ?? 'N/A' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $order->productOrder?->email ?? 'Unknown' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-gray-900 dark:text-gray-100">
                                RM {{ number_format($order->funnel_revenue, 2) }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $order->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500">
                        No orders yet
                    </div>
                @endforelse
            </div>
        </flux:card>

        <!-- Recent Sessions -->
        <flux:card class="lg:col-span-2">
            <div class="p-6 border-b border-gray-200 dark:border-zinc-700">
                <flux:heading size="lg">Recent Sessions</flux:heading>
                <flux:text class="text-sm text-gray-500">Latest visitor sessions</flux:text>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Session</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Step</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($recentSessions as $session)
                            <tr>
                                <td class="px-6 py-4 text-sm">
                                    <code class="text-xs bg-gray-100 dark:bg-zinc-700 px-2 py-1 rounded">
                                        {{ Str::limit($session->uuid, 8) }}
                                    </code>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $session->email ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $session->currentStep?->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4">
                                    <flux:badge size="sm" color="{{ match($session->status) {
                                        'converted' => 'green',
                                        'active' => 'blue',
                                        'abandoned' => 'red',
                                        default => 'zinc'
                                    } }}">
                                        {{ ucfirst($session->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $session->utm_source ?? 'Direct' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $session->last_activity_at?->diffForHumans() ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    No sessions yet
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    </div>
</div>
