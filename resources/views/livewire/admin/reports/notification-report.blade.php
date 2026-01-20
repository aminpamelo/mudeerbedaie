<?php

use App\Models\BroadcastLog;
use App\Models\ClassModel;
use App\Models\NotificationLog;
use App\Models\ScheduledNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    // Main filters
    public int $selectedYear;
    public array $availableYears = [];

    // Log filters
    public string $logType = 'all';
    public string $logClass = '';
    public string $logChannel = '';
    public string $logStatus = '';
    public string $logPeriod = 'this_month';
    public ?string $logStartDate = null;
    public ?string $logEndDate = null;
    public string $search = '';

    // Data
    public array $summary = [];
    public array $channelStats = [];
    public array $monthlyStats = [];
    public $classes = [];

    public function mount(): void
    {
        // Get available years (database-agnostic)
        $driver = DB::getDriverName();
        $yearExpr = $driver === 'sqlite' ? "strftime('%Y', created_at)" : 'YEAR(created_at)';

        $notificationYears = NotificationLog::selectRaw("DISTINCT {$yearExpr} as year")
            ->whereNotNull('created_at')
            ->pluck('year')
            ->map(fn($y) => (int) $y)
            ->toArray();

        $broadcastYears = BroadcastLog::selectRaw("DISTINCT {$yearExpr} as year")
            ->whereNotNull('created_at')
            ->pluck('year')
            ->map(fn($y) => (int) $y)
            ->toArray();

        $this->availableYears = collect(array_merge($notificationYears, $broadcastYears, [(int) date('Y')]))
            ->unique()
            ->sort()
            ->reverse()
            ->values()
            ->toArray();

        $this->selectedYear = $this->availableYears[0] ?? (int) date('Y');

        // Load classes for filter
        $this->classes = ClassModel::orderBy('title')->get(['id', 'title']);

        $this->setLogPeriodDates();
        $this->loadYearlyData();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadYearlyData();
        $this->resetPage();
    }

    public function updatedLogType(): void
    {
        $this->resetPage();
    }

    public function updatedLogClass(): void
    {
        $this->resetPage();
    }

    public function updatedLogChannel(): void
    {
        $this->resetPage();
    }

    public function updatedLogStatus(): void
    {
        $this->resetPage();
    }

    public function updatedLogPeriod(): void
    {
        $this->setLogPeriodDates();
        $this->resetPage();
    }

    public function updatedLogStartDate(): void
    {
        $this->logPeriod = 'custom';
        $this->resetPage();
    }

    public function updatedLogEndDate(): void
    {
        $this->logPeriod = 'custom';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    private function setLogPeriodDates(): void
    {
        switch ($this->logPeriod) {
            case 'today':
                $this->logStartDate = now()->format('Y-m-d');
                $this->logEndDate = now()->format('Y-m-d');
                break;
            case 'yesterday':
                $this->logStartDate = now()->subDay()->format('Y-m-d');
                $this->logEndDate = now()->subDay()->format('Y-m-d');
                break;
            case 'this_week':
                $this->logStartDate = now()->startOfWeek()->format('Y-m-d');
                $this->logEndDate = now()->endOfWeek()->format('Y-m-d');
                break;
            case 'this_month':
                $this->logStartDate = now()->startOfMonth()->format('Y-m-d');
                $this->logEndDate = now()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $this->logStartDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
                $this->logEndDate = now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'this_year':
                $this->logStartDate = now()->startOfYear()->format('Y-m-d');
                $this->logEndDate = now()->endOfYear()->format('Y-m-d');
                break;
            case 'custom':
                break;
        }
    }

    private function loadYearlyData(): void
    {
        $startOfYear = Carbon::create($this->selectedYear, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($this->selectedYear, 12, 31)->endOfDay();

        // Database-agnostic month expression
        $driver = DB::getDriverName();
        $monthExpr = $driver === 'sqlite' ? "CAST(strftime('%m', created_at) AS INTEGER)" : 'MONTH(created_at)';

        // Initialize monthly stats for all 12 months
        $this->monthlyStats = [];
        for ($month = 1; $month <= 12; $month++) {
            $this->monthlyStats[$month] = [
                'month' => $month,
                'month_name' => Carbon::create($this->selectedYear, $month, 1)->format('M'),
                'sent' => 0,
                'opened' => 0,
                'clicked' => 0,
                'failed' => 0,
            ];
        }

        // Get notification logs monthly stats
        $notificationMonthly = NotificationLog::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->selectRaw("
                {$monthExpr} as month,
                SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked
            ")
            ->groupByRaw($monthExpr)
            ->get();

        foreach ($notificationMonthly as $stat) {
            $month = (int) $stat->month;
            $this->monthlyStats[$month]['sent'] += $stat->sent;
            $this->monthlyStats[$month]['opened'] += $stat->opened;
            $this->monthlyStats[$month]['clicked'] += $stat->clicked;
            $this->monthlyStats[$month]['failed'] += $stat->failed;
        }

        // Get broadcast logs monthly stats
        $broadcastMonthly = BroadcastLog::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->selectRaw("
                {$monthExpr} as month,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked
            ")
            ->groupByRaw($monthExpr)
            ->get();

        foreach ($broadcastMonthly as $stat) {
            $month = (int) $stat->month;
            $this->monthlyStats[$month]['sent'] += $stat->sent;
            $this->monthlyStats[$month]['opened'] += $stat->opened;
            $this->monthlyStats[$month]['clicked'] += $stat->clicked;
            $this->monthlyStats[$month]['failed'] += $stat->failed;
        }

        // Calculate yearly summary
        $this->summary = [
            'total_sent' => 0,
            'total_opened' => 0,
            'total_clicked' => 0,
            'total_failed' => 0,
            'total_pending' => 0,
            'open_rate' => 0,
            'click_rate' => 0,
            'delivery_rate' => 0,
        ];

        foreach ($this->monthlyStats as $month) {
            $this->summary['total_sent'] += $month['sent'];
            $this->summary['total_opened'] += $month['opened'];
            $this->summary['total_clicked'] += $month['clicked'];
            $this->summary['total_failed'] += $month['failed'];
        }

        // Get pending count
        $pendingNotifications = NotificationLog::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->where('status', 'pending')
            ->count();

        $pendingBroadcasts = BroadcastLog::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->where('status', 'pending')
            ->count();

        $this->summary['total_pending'] = $pendingNotifications + $pendingBroadcasts;

        // Calculate rates
        if ($this->summary['total_sent'] > 0) {
            $this->summary['open_rate'] = round(($this->summary['total_opened'] / $this->summary['total_sent']) * 100, 1);
            $this->summary['click_rate'] = round(($this->summary['total_clicked'] / $this->summary['total_sent']) * 100, 1);
            $totalAttempts = $this->summary['total_sent'] + $this->summary['total_failed'];
            $this->summary['delivery_rate'] = $totalAttempts > 0
                ? round(($this->summary['total_sent'] / $totalAttempts) * 100, 1)
                : 0;
        }

        // Load channel stats for the year
        $this->loadChannelStats($startOfYear, $endOfYear);
    }

    private function loadChannelStats(Carbon $startOfYear, Carbon $endOfYear): void
    {
        $this->channelStats = [];

        // Get notification channel breakdown
        $channelBreakdown = NotificationLog::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->selectRaw("
                channel,
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked
            ")
            ->groupBy('channel')
            ->get();

        foreach ($channelBreakdown as $stat) {
            $this->channelStats[$stat->channel] = [
                'total' => $stat->total,
                'sent' => $stat->sent,
                'failed' => $stat->failed,
                'opened' => $stat->opened,
                'clicked' => $stat->clicked,
                'open_rate' => $stat->sent > 0 ? round(($stat->opened / $stat->sent) * 100, 1) : 0,
            ];
        }

        // Add broadcast stats
        $broadcastStats = BroadcastLog::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked
            ")
            ->first();

        if ($broadcastStats->total > 0) {
            $this->channelStats['broadcast'] = [
                'total' => $broadcastStats->total,
                'sent' => $broadcastStats->sent,
                'failed' => $broadcastStats->failed,
                'opened' => $broadcastStats->opened,
                'clicked' => $broadcastStats->clicked,
                'open_rate' => $broadcastStats->sent > 0
                    ? round(($broadcastStats->opened / $broadcastStats->sent) * 100, 1)
                    : 0,
            ];
        }
    }

    public function with(): array
    {
        $startDate = Carbon::parse($this->logStartDate)->startOfDay();
        $endDate = Carbon::parse($this->logEndDate)->endOfDay();

        $logs = collect();

        if ($this->logType === 'all' || $this->logType === 'notification') {
            $notificationLogs = NotificationLog::query()
                ->with(['scheduledNotification.class', 'student.user', 'teacher.user'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($this->logClass, function($q) {
                    $q->whereHas('scheduledNotification', fn($q) => $q->where('class_id', $this->logClass));
                })
                ->when($this->logChannel, fn($q) => $q->where('channel', $this->logChannel))
                ->when($this->logStatus, fn($q) => $q->where('status', $this->logStatus))
                ->when($this->search, function($q) {
                    $q->where(function($query) {
                        $query->where('destination', 'like', "%{$this->search}%")
                            ->orWhereHas('student.user', fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                            ->orWhereHas('teacher.user', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
                    });
                })
                ->latest()
                ->limit(500)
                ->get()
                ->map(fn($log) => [
                    'id' => $log->id,
                    'type' => 'notification',
                    'channel' => $log->channel,
                    'destination' => $log->destination,
                    'recipient_name' => $log->recipient_name,
                    'recipient_type' => $log->recipient_type,
                    'status' => $log->status,
                    'status_label' => $log->status_label,
                    'status_color' => $log->status_badge_color,
                    'error_message' => $log->error_message,
                    'sent_at' => $log->sent_at,
                    'opened_at' => $log->opened_at,
                    'open_count' => $log->open_count,
                    'clicked_at' => $log->clicked_at,
                    'click_count' => $log->click_count,
                    'created_at' => $log->created_at,
                    'source' => $log->scheduledNotification?->class?->title ?? 'Notifikasi Kelas',
                ]);

            $logs = $logs->merge($notificationLogs);
        }

        if ($this->logType === 'all' || $this->logType === 'broadcast') {
            $broadcastLogs = BroadcastLog::query()
                ->with(['broadcast', 'student.user'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($this->logStatus, fn($q) => $q->where('status', $this->logStatus))
                ->when($this->search, function($q) {
                    $q->where(function($query) {
                        $query->where('email', 'like', "%{$this->search}%")
                            ->orWhereHas('student.user', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
                    });
                })
                ->latest()
                ->limit(500)
                ->get()
                ->map(fn($log) => [
                    'id' => $log->id,
                    'type' => 'broadcast',
                    'channel' => 'email',
                    'destination' => $log->email,
                    'recipient_name' => $log->student?->user?->name ?? 'Unknown',
                    'recipient_type' => 'student',
                    'status' => $log->status,
                    'status_label' => ucfirst($log->status),
                    'status_color' => match($log->status) {
                        'sent' => 'green',
                        'failed' => 'red',
                        'pending' => 'yellow',
                        default => 'zinc',
                    },
                    'error_message' => $log->error_message,
                    'sent_at' => $log->sent_at,
                    'opened_at' => $log->opened_at,
                    'open_count' => $log->open_count,
                    'clicked_at' => $log->clicked_at,
                    'click_count' => $log->click_count,
                    'created_at' => $log->created_at,
                    'source' => $log->broadcast?->name ?? 'Broadcast',
                ]);

            $logs = $logs->merge($broadcastLogs);
        }

        // Sort and paginate
        $logs = $logs->sortByDesc('created_at')->values();
        $page = $this->getPage();
        $perPage = 20;
        $paginatedLogs = $logs->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'logs' => $paginatedLogs,
            'totalLogs' => $logs->count(),
            'currentPage' => $page,
            'lastPage' => max(1, ceil($logs->count() / $perPage)),
        ];
    }
}; ?>

<div>
    <div class="mx-auto max-w-7xl space-y-6 p-6 lg:p-8">
        <!-- Header with Year Filter -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl">Laporan Notifikasi</flux:heading>
                <flux:text class="mt-2">Pantau prestasi penghantaran notifikasi dan kadar buka</flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:label class="whitespace-nowrap">Tahun:</flux:label>
                <flux:select wire:model.live="selectedYear" class="w-32">
                    @foreach($availableYears as $year)
                        <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <!-- Summary Stats Cards (Yearly) -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <flux:card>
                <div class="p-4 text-center">
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">Jumlah Dihantar</flux:text>
                    <flux:heading size="xl" class="mt-1 text-blue-600 dark:text-blue-400">{{ number_format($summary['total_sent']) }}</flux:heading>
                    <flux:text class="text-xs text-gray-500 dark:text-gray-500">Tahun {{ $selectedYear }}</flux:text>
                </div>
            </flux:card>

            <flux:card>
                <div class="p-4 text-center">
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">Dibuka</flux:text>
                    <flux:heading size="xl" class="mt-1 text-green-600 dark:text-green-400">{{ number_format($summary['total_opened']) }}</flux:heading>
                    <flux:text class="text-xs text-gray-500 dark:text-gray-500">{{ $summary['open_rate'] }}% kadar buka</flux:text>
                </div>
            </flux:card>

            <flux:card>
                <div class="p-4 text-center">
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">Diklik</flux:text>
                    <flux:heading size="xl" class="mt-1 text-purple-600 dark:text-purple-400">{{ number_format($summary['total_clicked']) }}</flux:heading>
                    <flux:text class="text-xs text-gray-500 dark:text-gray-500">{{ $summary['click_rate'] }}% kadar klik</flux:text>
                </div>
            </flux:card>

            <flux:card>
                <div class="p-4 text-center">
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">Gagal</flux:text>
                    <flux:heading size="xl" class="mt-1 text-red-600 dark:text-red-400">{{ number_format($summary['total_failed']) }}</flux:heading>
                </div>
            </flux:card>

            <flux:card>
                <div class="p-4 text-center">
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">Menunggu</flux:text>
                    <flux:heading size="xl" class="mt-1 text-yellow-600 dark:text-yellow-400">{{ number_format($summary['total_pending']) }}</flux:heading>
                </div>
            </flux:card>

            <flux:card>
                <div class="p-4 text-center">
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">Kadar Penghantaran</flux:text>
                    <flux:heading size="xl" class="mt-1 {{ $summary['delivery_rate'] >= 90 ? 'text-green-600 dark:text-green-400' : ($summary['delivery_rate'] >= 70 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                        {{ $summary['delivery_rate'] }}%
                    </flux:heading>
                </div>
            </flux:card>
        </div>

        <!-- Monthly Line Chart -->
        <flux:card>
            <div class="p-4">
                <flux:heading size="lg" class="mb-4">Trend Bulanan {{ $selectedYear }}</flux:heading>
                <div class="h-72">
                    <canvas id="monthlyChart" wire:ignore></canvas>
                </div>
            </div>
        </flux:card>

        <!-- Channel Stats (Centered) -->
        @if(count($channelStats) > 0)
            <flux:card>
                <div class="p-4">
                    <flux:heading size="lg" class="mb-4 text-center">Statistik Mengikut Saluran ({{ $selectedYear }})</flux:heading>
                    <div class="flex flex-wrap justify-center gap-4">
                        @foreach($channelStats as $channelName => $stats)
                            <div class="p-4 bg-gray-50 dark:bg-zinc-800 rounded-lg w-full sm:w-64">
                                <div class="flex items-center justify-between mb-2">
                                    <flux:text class="font-medium capitalize">
                                        @switch($channelName)
                                            @case('email')
                                                E-mel
                                                @break
                                            @case('whatsapp')
                                                WhatsApp
                                                @break
                                            @case('sms')
                                                SMS
                                                @break
                                            @case('broadcast')
                                                Broadcast
                                                @break
                                            @default
                                                {{ $channelName }}
                                        @endswitch
                                    </flux:text>
                                    <flux:badge size="sm" color="{{ $stats['open_rate'] >= 20 ? 'green' : ($stats['open_rate'] >= 10 ? 'yellow' : 'zinc') }}">
                                        {{ $stats['open_rate'] }}% buka
                                    </flux:badge>
                                </div>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Dihantar:</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($stats['sent']) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Dibuka:</span>
                                        <span class="font-medium text-green-600 dark:text-green-400">{{ number_format($stats['opened']) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Diklik:</span>
                                        <span class="font-medium text-purple-600 dark:text-purple-400">{{ number_format($stats['clicked']) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Gagal:</span>
                                        <span class="font-medium text-red-600 dark:text-red-400">{{ number_format($stats['failed']) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </flux:card>
        @endif

        <!-- Log Penghantaran with Filters -->
        <flux:card>
            <div class="p-4 border-b border-gray-200 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">Log Penghantaran</flux:heading>

                <!-- Log Filters -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <flux:label>Jenis</flux:label>
                        <flux:select wire:model.live="logType">
                            <flux:select.option value="all">Semua</flux:select.option>
                            <flux:select.option value="notification">Notifikasi</flux:select.option>
                            <flux:select.option value="broadcast">Broadcast</flux:select.option>
                        </flux:select>
                    </div>

                    <div>
                        <flux:label>Kelas</flux:label>
                        <flux:select wire:model.live="logClass">
                            <flux:select.option value="">Semua Kelas</flux:select.option>
                            @foreach($classes as $class)
                                <flux:select.option value="{{ $class->id }}">{{ $class->title }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <flux:label>Saluran</flux:label>
                        <flux:select wire:model.live="logChannel">
                            <flux:select.option value="">Semua</flux:select.option>
                            <flux:select.option value="email">E-mel</flux:select.option>
                            <flux:select.option value="whatsapp">WhatsApp</flux:select.option>
                            <flux:select.option value="sms">SMS</flux:select.option>
                        </flux:select>
                    </div>

                    <div>
                        <flux:label>Status</flux:label>
                        <flux:select wire:model.live="logStatus">
                            <flux:select.option value="">Semua</flux:select.option>
                            <flux:select.option value="sent">Dihantar</flux:select.option>
                            <flux:select.option value="delivered">Diterima</flux:select.option>
                            <flux:select.option value="failed">Gagal</flux:select.option>
                            <flux:select.option value="pending">Menunggu</flux:select.option>
                        </flux:select>
                    </div>

                    <div>
                        <flux:label>Tempoh</flux:label>
                        <flux:select wire:model.live="logPeriod">
                            <flux:select.option value="today">Hari Ini</flux:select.option>
                            <flux:select.option value="yesterday">Semalam</flux:select.option>
                            <flux:select.option value="this_week">Minggu Ini</flux:select.option>
                            <flux:select.option value="this_month">Bulan Ini</flux:select.option>
                            <flux:select.option value="last_month">Bulan Lepas</flux:select.option>
                            <flux:select.option value="this_year">Tahun Ini</flux:select.option>
                            <flux:select.option value="custom">Tersuai</flux:select.option>
                        </flux:select>
                    </div>

                    @if($logPeriod === 'custom')
                        <div>
                            <flux:label>Dari</flux:label>
                            <flux:input type="date" wire:model.live="logStartDate" />
                        </div>
                        <div>
                            <flux:label>Hingga</flux:label>
                            <flux:input type="date" wire:model.live="logEndDate" />
                        </div>
                    @endif
                </div>

                <!-- Search -->
                <div class="mt-4">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari penerima atau destinasi..."
                        icon="magnifying-glass"
                        class="max-w-sm"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Penerima</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sumber</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Saluran</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dihantar</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dibuka</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Diklik</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ralat</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($logs as $log)
                            <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $log['recipient_name'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $log['destination'] }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100 max-w-[150px] truncate" title="{{ $log['source'] }}">
                                        {{ $log['source'] }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <flux:badge size="sm" color="{{ $log['type'] === 'broadcast' ? 'blue' : 'zinc' }}">
                                        {{ $log['type'] === 'broadcast' ? 'Broadcast' : 'Notifikasi' }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <flux:badge size="sm" color="zinc">
                                        @switch($log['channel'])
                                            @case('email')
                                                E-mel
                                                @break
                                            @case('whatsapp')
                                                WhatsApp
                                                @break
                                            @case('sms')
                                                SMS
                                                @break
                                            @default
                                                {{ ucfirst($log['channel']) }}
                                        @endswitch
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <flux:badge size="sm" color="{{ $log['status_color'] }}">
                                        {{ $log['status_label'] }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($log['sent_at'])
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ $log['sent_at']->format('d M Y') }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $log['sent_at']->format('H:i') }}</div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($log['opened_at'])
                                        <div class="text-sm text-green-600 dark:text-green-400">{{ $log['opened_at']->format('d M Y') }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $log['opened_at']->format('H:i') }} ({{ $log['open_count'] }}x)</div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($log['clicked_at'])
                                        <div class="text-sm text-purple-600 dark:text-purple-400">{{ $log['clicked_at']->format('d M Y') }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $log['clicked_at']->format('H:i') }} ({{ $log['click_count'] }}x)</div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($log['error_message'])
                                        <div class="text-xs text-red-600 dark:text-red-400 max-w-[200px] truncate" title="{{ $log['error_message'] }}">
                                            {{ $log['error_message'] }}
                                        </div>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <flux:icon.inbox class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Tiada log ditemui</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Cuba ubah penapis untuk melihat lebih banyak data.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($totalLogs > 20)
                <div class="px-4 py-4 border-t border-gray-200 dark:border-zinc-700 flex items-center justify-between">
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                        Menunjukkan {{ ($currentPage - 1) * 20 + 1 }} - {{ min($currentPage * 20, $totalLogs) }} daripada {{ $totalLogs }} rekod
                    </flux:text>
                    <div class="flex gap-2">
                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="previousPage"
                            :disabled="$currentPage === 1"
                        >
                            Sebelum
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="nextPage"
                            :disabled="$currentPage >= $lastPage"
                        >
                            Seterusnya
                        </flux:button>
                    </div>
                </div>
            @endif
        </flux:card>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:navigated', initChart);
        document.addEventListener('DOMContentLoaded', initChart);

        function initChart() {
            const ctx = document.getElementById('monthlyChart');
            if (!ctx) return;

            // Destroy existing chart if any
            if (window.monthlyChartInstance) {
                window.monthlyChartInstance.destroy();
            }

            const monthlyData = @json($monthlyStats);
            const labels = Object.values(monthlyData).map(m => m.month_name);
            const sentData = Object.values(monthlyData).map(m => m.sent);
            const openedData = Object.values(monthlyData).map(m => m.opened);
            const clickedData = Object.values(monthlyData).map(m => m.clicked);
            const failedData = Object.values(monthlyData).map(m => m.failed);

            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            const textColor = isDark ? '#9ca3af' : '#6b7280';

            window.monthlyChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Dihantar',
                            data: sentData,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Dibuka',
                            data: openedData,
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Diklik',
                            data: clickedData,
                            borderColor: '#a855f7',
                            backgroundColor: 'rgba(168, 85, 247, 0.1)',
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Gagal',
                            data: failedData,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.3,
                            fill: true,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: textColor,
                                usePointStyle: true,
                                padding: 20,
                            }
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#27272a' : '#ffffff',
                            titleColor: isDark ? '#ffffff' : '#111827',
                            bodyColor: isDark ? '#d1d5db' : '#4b5563',
                            borderColor: isDark ? '#3f3f46' : '#e5e7eb',
                            borderWidth: 1,
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: gridColor,
                            },
                            ticks: {
                                color: textColor,
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: gridColor,
                            },
                            ticks: {
                                color: textColor,
                            }
                        }
                    }
                }
            });
        }

        // Re-init chart when year changes
        Livewire.on('yearChanged', () => {
            setTimeout(initChart, 100);
        });
    </script>
    @endpush
</div>
