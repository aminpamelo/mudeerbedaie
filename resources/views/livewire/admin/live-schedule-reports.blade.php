<?php

use App\Models\LiveSchedule;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public $dateFrom = '';
    public $dateTo = '';

    public function mount()
    {
        $this->dateFrom = now()->startOfWeek()->format('Y-m-d');
        $this->dateTo = now()->endOfWeek()->format('Y-m-d');
    }

    public function getPlatformAccountsProperty()
    {
        return PlatformAccount::query()
            ->with(['platform'])
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function getLiveHostsProperty()
    {
        return User::query()
            ->where('role', 'live_host')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function getTimeSlotsProperty()
    {
        return LiveTimeSlot::query()
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();
    }

    public function getScheduleStatsProperty()
    {
        $totalSlots = $this->platformAccounts->count() * 7 * $this->timeSlots->count();

        $schedulesQuery = LiveSchedule::query()
            ->whereIn('platform_account_id', $this->platformAccounts->pluck('id'))
            ->where('is_active', true);

        $totalSchedules = (clone $schedulesQuery)->count();
        $assignedSchedules = (clone $schedulesQuery)->whereNotNull('live_host_id')->count();
        $unassignedSchedules = $totalSchedules - $assignedSchedules;

        $coveragePercentage = $totalSlots > 0 ? round(($assignedSchedules / $totalSlots) * 100, 1) : 0;

        return [
            'total_possible_slots' => $totalSlots,
            'total_schedules' => $totalSchedules,
            'assigned_schedules' => $assignedSchedules,
            'unassigned_schedules' => $unassignedSchedules,
            'coverage_percentage' => $coveragePercentage,
        ];
    }

    public function getHostWorkloadProperty()
    {
        return $this->liveHosts->map(function ($host) {
            $scheduleCount = LiveSchedule::where('live_host_id', $host->id)
                ->where('is_active', true)
                ->count();

            $platforms = LiveSchedule::where('live_host_id', $host->id)
                ->where('is_active', true)
                ->with('platformAccount')
                ->get()
                ->pluck('platformAccount.name')
                ->unique()
                ->values();

            // Calculate hours per week
            $totalMinutes = LiveSchedule::where('live_host_id', $host->id)
                ->where('is_active', true)
                ->get()
                ->sum(function ($schedule) {
                    $start = \Carbon\Carbon::parse($schedule->start_time);
                    $end = \Carbon\Carbon::parse($schedule->end_time);
                    if ($end < $start) {
                        $end->addDay();
                    }
                    return $end->diffInMinutes($start);
                });

            return [
                'host' => $host,
                'schedule_count' => $scheduleCount,
                'platforms' => $platforms,
                'hours_per_week' => round($totalMinutes / 60, 1),
            ];
        })->sortByDesc('schedule_count')->values();
    }

    public function getPlatformCoverageProperty()
    {
        return $this->platformAccounts->map(function ($platform) {
            $totalPossibleSlots = 7 * $this->timeSlots->count();

            $assignedSlots = LiveSchedule::where('platform_account_id', $platform->id)
                ->where('is_active', true)
                ->whereNotNull('live_host_id')
                ->count();

            $unassignedSlots = LiveSchedule::where('platform_account_id', $platform->id)
                ->where('is_active', true)
                ->whereNull('live_host_id')
                ->count();

            $coverage = $totalPossibleSlots > 0 ? round(($assignedSlots / $totalPossibleSlots) * 100, 1) : 0;

            // Get unique hosts for this platform
            $hosts = LiveSchedule::where('platform_account_id', $platform->id)
                ->where('is_active', true)
                ->whereNotNull('live_host_id')
                ->with('liveHost')
                ->get()
                ->pluck('liveHost')
                ->unique('id')
                ->values();

            return [
                'platform' => $platform,
                'total_possible' => $totalPossibleSlots,
                'assigned' => $assignedSlots,
                'unassigned' => $totalPossibleSlots - $assignedSlots,
                'coverage' => $coverage,
                'hosts' => $hosts,
            ];
        });
    }

    public function getDayDistributionProperty()
    {
        $days = [
            0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'
        ];

        return collect($days)->map(function ($dayName, $dayIndex) {
            $assigned = LiveSchedule::where('day_of_week', $dayIndex)
                ->where('is_active', true)
                ->whereNotNull('live_host_id')
                ->count();

            $total = LiveSchedule::where('day_of_week', $dayIndex)
                ->where('is_active', true)
                ->count();

            return [
                'day' => $dayName,
                'day_index' => $dayIndex,
                'assigned' => $assigned,
                'total' => $total,
                'coverage' => $total > 0 ? round(($assigned / $total) * 100, 1) : 0,
            ];
        })->values();
    }

    public function getTimeSlotDistributionProperty()
    {
        return $this->timeSlots->map(function ($slot) {
            $assigned = LiveSchedule::where('start_time', $slot->start_time)
                ->where('is_active', true)
                ->whereNotNull('live_host_id')
                ->count();

            $total = LiveSchedule::where('start_time', $slot->start_time)
                ->where('is_active', true)
                ->count();

            return [
                'slot' => $slot,
                'time_range' => $slot->time_range,
                'assigned' => $assigned,
                'total' => $total,
                'coverage' => $total > 0 ? round(($assigned / $total) * 100, 1) : 0,
            ];
        });
    }

    public function exportReport()
    {
        $rows = [];

        // Summary section
        $rows[] = ['=== SCHEDULE REPORT ==='];
        $rows[] = ['Generated:', now()->format('Y-m-d H:i:s')];
        $rows[] = [''];

        // Overall stats
        $rows[] = ['=== OVERALL STATISTICS ==='];
        $stats = $this->scheduleStats;
        $rows[] = ['Total Possible Slots:', $stats['total_possible_slots']];
        $rows[] = ['Assigned Schedules:', $stats['assigned_schedules']];
        $rows[] = ['Unassigned Schedules:', $stats['unassigned_schedules']];
        $rows[] = ['Coverage:', $stats['coverage_percentage'] . '%'];
        $rows[] = [''];

        // Host workload
        $rows[] = ['=== HOST WORKLOAD ==='];
        $rows[] = ['Host Name', 'Schedules', 'Hours/Week', 'Platforms'];
        foreach ($this->hostWorkload as $item) {
            $rows[] = [
                $item['host']->name,
                $item['schedule_count'],
                $item['hours_per_week'],
                $item['platforms']->implode(', '),
            ];
        }
        $rows[] = [''];

        // Platform coverage
        $rows[] = ['=== PLATFORM COVERAGE ==='];
        $rows[] = ['Platform', 'Assigned', 'Total', 'Coverage %'];
        foreach ($this->platformCoverage as $item) {
            $rows[] = [
                $item['platform']->name,
                $item['assigned'],
                $item['total_possible'],
                $item['coverage'] . '%',
            ];
        }

        $filename = 'schedule-report-' . now()->format('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, (array) $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
?>

<div>
    <x-slot:title>Schedule Reports</x-slot:title>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Schedule Reports</flux:heading>
            <flux:text class="mt-2">Analytics and insights for live host schedules</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" wire:click="exportReport">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-2" />
                    Export Report
                </div>
            </flux:button>
            <flux:button variant="primary" href="{{ route('admin.live-schedule-calendar') }}">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                    Back to Schedule
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Overall Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <flux:icon name="calendar-days" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->scheduleStats['total_possible_slots'] }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Slots</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <flux:icon name="check-circle" class="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->scheduleStats['assigned_schedules'] }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Assigned</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                    <flux:icon name="exclamation-circle" class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->scheduleStats['unassigned_schedules'] }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Unassigned</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <flux:icon name="users" class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->liveHosts->count() }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Active Hosts</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                    <flux:icon name="chart-bar" class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->scheduleStats['coverage_percentage'] }}%</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Coverage</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Host Workload -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h3 class="font-semibold text-gray-900 dark:text-white">Host Workload</h3>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 dark:bg-gray-800 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Host</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Slots</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Hours/Week</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Platforms</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->hostWorkload as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="w-3 h-3 rounded-full"
                                            style="background-color: {{ $item['host']->host_color }};"
                                        ></span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $item['host']->name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        {{ $item['schedule_count'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $item['hours_per_week'] }}h
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($item['platforms']->take(2) as $platform)
                                            <flux:badge size="sm" color="gray">{{ Str::limit($platform, 10) }}</flux:badge>
                                        @endforeach
                                        @if($item['platforms']->count() > 2)
                                            <flux:badge size="sm" color="gray">+{{ $item['platforms']->count() - 2 }}</flux:badge>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No hosts assigned to any schedules
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Platform Coverage -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h3 class="font-semibold text-gray-900 dark:text-white">Platform Coverage</h3>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 dark:bg-gray-800 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Platform</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Assigned</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Coverage</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Progress</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->platformCoverage as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $item['platform']->name }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $item['assigned'] }}/{{ $item['total_possible'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <flux:badge
                                        :color="$item['coverage'] >= 80 ? 'green' : ($item['coverage'] >= 50 ? 'yellow' : 'red')"
                                        size="sm"
                                    >
                                        {{ $item['coverage'] }}%
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                        <div
                                            class="h-2 rounded-full {{ $item['coverage'] >= 80 ? 'bg-green-500' : ($item['coverage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                            style="width: {{ $item['coverage'] }}%"
                                        ></div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No platform accounts configured
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Day Distribution -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h3 class="font-semibold text-gray-900 dark:text-white">Coverage by Day</h3>
            </div>
            <div class="p-4 space-y-3">
                @foreach($this->dayDistribution as $item)
                    <div class="flex items-center gap-3">
                        <span class="w-24 text-sm text-gray-600 dark:text-gray-400">{{ $item['day'] }}</span>
                        <div class="flex-1">
                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-4">
                                <div
                                    class="h-4 rounded-full {{ $item['coverage'] >= 80 ? 'bg-green-500' : ($item['coverage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') }} flex items-center justify-center"
                                    style="width: {{ max($item['coverage'], 10) }}%"
                                >
                                    @if($item['coverage'] > 20)
                                        <span class="text-xs font-medium text-white">{{ $item['coverage'] }}%</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400 w-16 text-right">
                            {{ $item['assigned'] }}/{{ $item['total'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Time Slot Distribution -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <h3 class="font-semibold text-gray-900 dark:text-white">Coverage by Time Slot</h3>
            </div>
            <div class="p-4 space-y-3">
                @foreach($this->timeSlotDistribution as $item)
                    <div class="flex items-center gap-3">
                        <span class="w-32 text-sm text-gray-600 dark:text-gray-400">{{ $item['time_range'] }}</span>
                        <div class="flex-1">
                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-4">
                                <div
                                    class="h-4 rounded-full {{ $item['coverage'] >= 80 ? 'bg-green-500' : ($item['coverage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') }} flex items-center justify-center"
                                    style="width: {{ max($item['coverage'], 10) }}%"
                                >
                                    @if($item['coverage'] > 20)
                                        <span class="text-xs font-medium text-white">{{ $item['coverage'] }}%</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400 w-16 text-right">
                            {{ $item['assigned'] }}/{{ $item['total'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
