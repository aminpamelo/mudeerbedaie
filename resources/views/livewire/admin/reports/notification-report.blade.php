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

    // Tab state
    public string $activeTab = 'statistics';

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
    public array $previousYearSummary = [];
    public array $channelStats = [];
    public array $monthlyStats = [];
    public array $topClasses = [];
    public array $failureReasons = [];
    public array $recipientStats = [];
    public $classes = [];

    public function mount(): void
    {
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
        $this->classes = ClassModel::orderBy('title')->get(['id', 'title']);

        $this->setLogPeriodDates();
        $this->loadYearlyData();
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
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

    public function gotoPage(int $page): void
    {
        $this->setPage($page);
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

        $driver = DB::getDriverName();
        $monthExpr = $driver === 'sqlite' ? "CAST(strftime('%m', created_at) AS INTEGER)" : 'MONTH(created_at)';

        // Initialize monthly stats
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

        // Load additional stats
        $this->loadChannelStats($startOfYear, $endOfYear);
        $this->loadPreviousYearComparison();
        $this->loadTopClasses($startOfYear, $endOfYear);
        $this->loadFailureReasons($startOfYear, $endOfYear);
        $this->loadRecipientStats($startOfYear, $endOfYear);
    }

    private function loadChannelStats(Carbon $startOfYear, Carbon $endOfYear): void
    {
        $this->channelStats = [];

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

    private function loadPreviousYearComparison(): void
    {
        $prevYear = $this->selectedYear - 1;
        $startOfPrevYear = Carbon::create($prevYear, 1, 1)->startOfDay();
        $endOfPrevYear = Carbon::create($prevYear, 12, 31)->endOfDay();

        $prevNotification = NotificationLog::query()
            ->whereBetween('created_at', [$startOfPrevYear, $endOfPrevYear])
            ->selectRaw("
                SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened
            ")
            ->first();

        $prevBroadcast = BroadcastLog::query()
            ->whereBetween('created_at', [$startOfPrevYear, $endOfPrevYear])
            ->selectRaw("
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened
            ")
            ->first();

        $prevSent = ($prevNotification->sent ?? 0) + ($prevBroadcast->sent ?? 0);
        $prevOpened = ($prevNotification->opened ?? 0) + ($prevBroadcast->opened ?? 0);
        $prevFailed = ($prevNotification->failed ?? 0) + ($prevBroadcast->failed ?? 0);

        $this->previousYearSummary = [
            'total_sent' => $prevSent,
            'total_opened' => $prevOpened,
            'total_failed' => $prevFailed,
            'sent_change' => $prevSent > 0 ? round((($this->summary['total_sent'] - $prevSent) / $prevSent) * 100, 1) : null,
            'opened_change' => $prevOpened > 0 ? round((($this->summary['total_opened'] - $prevOpened) / $prevOpened) * 100, 1) : null,
            'failed_change' => $prevFailed > 0 ? round((($this->summary['total_failed'] - $prevFailed) / $prevFailed) * 100, 1) : null,
        ];
    }

    private function loadTopClasses(Carbon $startOfYear, Carbon $endOfYear): void
    {
        $this->topClasses = NotificationLog::query()
            ->join('scheduled_notifications', 'notification_logs.scheduled_notification_id', '=', 'scheduled_notifications.id')
            ->join('classes', 'scheduled_notifications.class_id', '=', 'classes.id')
            ->whereBetween('notification_logs.created_at', [$startOfYear, $endOfYear])
            ->selectRaw("
                classes.id,
                classes.title,
                COUNT(*) as total,
                SUM(CASE WHEN notification_logs.status IN ('sent', 'delivered') THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN notification_logs.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened
            ")
            ->groupBy('classes.id', 'classes.title')
            ->havingRaw('sent > 0')
            ->orderByRaw('(SUM(CASE WHEN notification_logs.opened_at IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / SUM(CASE WHEN notification_logs.status IN (\'sent\', \'delivered\') THEN 1 ELSE 0 END)) DESC')
            ->limit(5)
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'total' => $row->total,
                'sent' => $row->sent,
                'opened' => $row->opened,
                'open_rate' => $row->sent > 0 ? round(($row->opened / $row->sent) * 100, 1) : 0,
            ])
            ->toArray();
    }

    private function loadFailureReasons(Carbon $startOfYear, Carbon $endOfYear): void
    {
        $notificationFailures = NotificationLog::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->where('status', 'failed')
            ->whereNotNull('error_message')
            ->selectRaw('error_message, COUNT(*) as count')
            ->groupBy('error_message')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $broadcastFailures = BroadcastLog::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->where('status', 'failed')
            ->whereNotNull('error_message')
            ->selectRaw('error_message, COUNT(*) as count')
            ->groupBy('error_message')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $this->failureReasons = $notificationFailures->merge($broadcastFailures)
            ->groupBy('error_message')
            ->map(fn($group) => [
                'reason' => $group->first()->error_message,
                'count' => $group->sum('count'),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->toArray();
    }

    private function loadRecipientStats(Carbon $startOfYear, Carbon $endOfYear): void
    {
        $studentStats = NotificationLog::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->where('recipient_type', 'student')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened
            ")
            ->first();

        $teacherStats = NotificationLog::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->where('recipient_type', 'teacher')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened
            ")
            ->first();

        // Add broadcast stats to student (broadcasts go to students)
        $broadcastStats = BroadcastLog::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened
            ")
            ->first();

        $studentTotal = ($studentStats->total ?? 0) + ($broadcastStats->total ?? 0);
        $studentSent = ($studentStats->sent ?? 0) + ($broadcastStats->sent ?? 0);
        $studentOpened = ($studentStats->opened ?? 0) + ($broadcastStats->opened ?? 0);

        $this->recipientStats = [
            'student' => [
                'total' => $studentTotal,
                'sent' => $studentSent,
                'opened' => $studentOpened,
                'open_rate' => $studentSent > 0 ? round(($studentOpened / $studentSent) * 100, 1) : 0,
            ],
            'teacher' => [
                'total' => $teacherStats->total ?? 0,
                'sent' => $teacherStats->sent ?? 0,
                'opened' => $teacherStats->opened ?? 0,
                'open_rate' => ($teacherStats->sent ?? 0) > 0 ? round((($teacherStats->opened ?? 0) / $teacherStats->sent) * 100, 1) : 0,
            ],
        ];
    }

    public function exportStatistics(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = "laporan-notifikasi-{$this->selectedYear}.csv";

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($handle, ['Laporan Notifikasi - Tahun ' . $this->selectedYear]);
            fputcsv($handle, []);

            // Summary
            fputcsv($handle, ['RINGKASAN TAHUNAN']);
            fputcsv($handle, ['Metrik', 'Nilai']);
            fputcsv($handle, ['Jumlah Dihantar', $this->summary['total_sent']]);
            fputcsv($handle, ['Jumlah Dibuka', $this->summary['total_opened']]);
            fputcsv($handle, ['Jumlah Diklik', $this->summary['total_clicked']]);
            fputcsv($handle, ['Jumlah Gagal', $this->summary['total_failed']]);
            fputcsv($handle, ['Kadar Buka (%)', $this->summary['open_rate']]);
            fputcsv($handle, ['Kadar Klik (%)', $this->summary['click_rate']]);
            fputcsv($handle, ['Kadar Penghantaran (%)', $this->summary['delivery_rate']]);
            fputcsv($handle, []);

            // Monthly stats
            fputcsv($handle, ['STATISTIK BULANAN']);
            fputcsv($handle, ['Bulan', 'Dihantar', 'Dibuka', 'Diklik', 'Gagal']);
            foreach ($this->monthlyStats as $month) {
                fputcsv($handle, [$month['month_name'], $month['sent'], $month['opened'], $month['clicked'], $month['failed']]);
            }
            fputcsv($handle, []);

            // Channel stats
            fputcsv($handle, ['STATISTIK MENGIKUT SALURAN']);
            fputcsv($handle, ['Saluran', 'Jumlah', 'Dihantar', 'Dibuka', 'Gagal', 'Kadar Buka (%)']);
            foreach ($this->channelStats as $channel => $stats) {
                fputcsv($handle, [ucfirst($channel), $stats['total'], $stats['sent'], $stats['opened'], $stats['failed'], $stats['open_rate']]);
            }
            fputcsv($handle, []);

            // Top classes
            if (!empty($this->topClasses)) {
                fputcsv($handle, ['TOP 5 KELAS (KADAR BUKA TERTINGGI)']);
                fputcsv($handle, ['Kelas', 'Dihantar', 'Dibuka', 'Kadar Buka (%)']);
                foreach ($this->topClasses as $class) {
                    fputcsv($handle, [$class['title'], $class['sent'], $class['opened'], $class['open_rate']]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function with(): array
    {
        $logs = collect();
        $totalLogs = 0;
        $currentPage = 1;
        $lastPage = 1;

        // Only load logs when on log tab
        if ($this->activeTab === 'logs') {
            $startDate = Carbon::parse($this->logStartDate)->startOfDay();
            $endDate = Carbon::parse($this->logEndDate)->endOfDay();

            if ($this->logType === 'all' || $this->logType === 'notification') {
                $notificationLogs = NotificationLog::query()
                    ->with(['scheduledNotification.class', 'scheduledNotification.setting', 'student.user', 'teacher.user'])
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
                    ->map(function($log) {
                        // Get notification type label from setting
                        $notificationType = $log->scheduledNotification?->setting?->notification_type;
                        $typeLabels = \App\Models\ClassNotificationSetting::getNotificationTypeLabels();
                        $notificationTypeLabel = $typeLabels[$notificationType]['name'] ?? $notificationType ?? '-';

                        return [
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
                            'notification_type_label' => $notificationTypeLabel,
                        ];
                    });

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
                        'notification_type_label' => 'Broadcast',
                    ]);

                $logs = $logs->merge($broadcastLogs);
            }

            $logs = $logs->sortByDesc('created_at')->values();
            $page = $this->getPage();
            $perPage = 20;
            $totalLogs = $logs->count();
            $currentPage = $page;
            $lastPage = max(1, ceil($logs->count() / $perPage));
            $logs = $logs->slice(($page - 1) * $perPage, $perPage)->values();
        }

        return [
            'logs' => $logs,
            'totalLogs' => $totalLogs,
            'currentPage' => $currentPage,
            'lastPage' => $lastPage,
        ];
    }
}; ?>

<div>
    <div class="mx-auto max-w-7xl space-y-6 p-6 lg:p-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl">Laporan Notifikasi</flux:heading>
                <flux:text class="mt-2">Pantau prestasi penghantaran notifikasi dan kadar buka</flux:text>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 dark:border-zinc-700">
            <nav class="-mb-px flex space-x-8">
                <button
                    wire:click="setActiveTab('statistics')"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors {{ $activeTab === 'statistics' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    <flux:icon name="chart-bar" class="w-5 h-5 inline-block mr-2" />
                    Statistik
                </button>
                <button
                    wire:click="setActiveTab('logs')"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors {{ $activeTab === 'logs' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}"
                >
                    <flux:icon name="document-text" class="w-5 h-5 inline-block mr-2" />
                    Log Notifikasi
                </button>
            </nav>
        </div>

        <!-- Statistics Tab -->
        @if($activeTab === 'statistics')
            <!-- Year Filter & Export -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-2">
                    <flux:label class="whitespace-nowrap">Tahun:</flux:label>
                    <flux:select wire:model.live="selectedYear" class="w-32">
                        @foreach($availableYears as $year)
                            <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:button variant="outline" wire:click="exportStatistics" icon="arrow-down-tray">
                    Eksport CSV
                </flux:button>
            </div>

            <!-- Summary Stats Cards with Comparison -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <flux:card>
                    <div class="p-4 text-center">
                        <flux:text class="text-sm text-gray-600 dark:text-gray-400">Jumlah Dihantar</flux:text>
                        <flux:heading size="xl" class="mt-1 text-blue-600 dark:text-blue-400">{{ number_format($summary['total_sent']) }}</flux:heading>
                        @if($previousYearSummary['sent_change'] !== null)
                            <div class="mt-1 text-xs {{ $previousYearSummary['sent_change'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $previousYearSummary['sent_change'] >= 0 ? '+' : '' }}{{ $previousYearSummary['sent_change'] }}% dari {{ $selectedYear - 1 }}
                            </div>
                        @endif
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
                        @if($previousYearSummary['failed_change'] !== null)
                            <div class="mt-1 text-xs {{ $previousYearSummary['failed_change'] <= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $previousYearSummary['failed_change'] >= 0 ? '+' : '' }}{{ $previousYearSummary['failed_change'] }}% dari {{ $selectedYear - 1 }}
                            </div>
                        @endif
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

            <!-- Two Column Layout for Additional Stats -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Top 5 Classes -->
                <flux:card>
                    <div class="p-4">
                        <flux:heading size="lg" class="mb-4">Top 5 Kelas (Kadar Buka Tertinggi)</flux:heading>
                        @if(count($topClasses) > 0)
                            <div class="space-y-3">
                                @foreach($topClasses as $index => $class)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-800 rounded-lg">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-sm font-bold text-blue-600 dark:text-blue-400">
                                                {{ $index + 1 }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $class['title'] }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $class['sent'] }} dihantar, {{ $class['opened'] }} dibuka</div>
                                            </div>
                                        </div>
                                        <flux:badge color="{{ $class['open_rate'] >= 30 ? 'green' : ($class['open_rate'] >= 15 ? 'yellow' : 'zinc') }}">
                                            {{ $class['open_rate'] }}%
                                        </flux:badge>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <flux:icon name="inbox" class="w-12 h-12 mx-auto mb-2" />
                                <p>Tiada data kelas untuk tahun ini</p>
                            </div>
                        @endif
                    </div>
                </flux:card>

                <!-- Recipient Breakdown -->
                <flux:card>
                    <div class="p-4">
                        <flux:heading size="lg" class="mb-4">Pecahan Penerima</flux:heading>
                        <div class="space-y-4">
                            <!-- Students -->
                            <div class="p-4 bg-gray-50 dark:bg-zinc-800 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="academic-cap" class="w-5 h-5 text-blue-500" />
                                        <span class="font-medium text-gray-900 dark:text-gray-100">Pelajar</span>
                                    </div>
                                    <flux:badge color="blue">{{ $recipientStats['student']['open_rate'] }}% buka</flux:badge>
                                </div>
                                <div class="grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <div class="text-gray-500 dark:text-gray-400">Jumlah</div>
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($recipientStats['student']['total']) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 dark:text-gray-400">Dihantar</div>
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($recipientStats['student']['sent']) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 dark:text-gray-400">Dibuka</div>
                                        <div class="font-semibold text-green-600 dark:text-green-400">{{ number_format($recipientStats['student']['opened']) }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Teachers -->
                            <div class="p-4 bg-gray-50 dark:bg-zinc-800 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="user" class="w-5 h-5 text-purple-500" />
                                        <span class="font-medium text-gray-900 dark:text-gray-100">Guru</span>
                                    </div>
                                    <flux:badge color="purple">{{ $recipientStats['teacher']['open_rate'] }}% buka</flux:badge>
                                </div>
                                <div class="grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <div class="text-gray-500 dark:text-gray-400">Jumlah</div>
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($recipientStats['teacher']['total']) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 dark:text-gray-400">Dihantar</div>
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($recipientStats['teacher']['sent']) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 dark:text-gray-400">Dibuka</div>
                                        <div class="font-semibold text-green-600 dark:text-green-400">{{ number_format($recipientStats['teacher']['opened']) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </flux:card>
            </div>

            <!-- Channel Stats & Failure Reasons -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Channel Stats -->
                @if(count($channelStats) > 0)
                    <flux:card>
                        <div class="p-4">
                            <flux:heading size="lg" class="mb-4">Statistik Mengikut Saluran</flux:heading>
                            <div class="space-y-3">
                                @foreach($channelStats as $channelName => $stats)
                                    <div class="p-3 bg-gray-50 dark:bg-zinc-800 rounded-lg">
                                        <div class="flex items-center justify-between mb-2">
                                            <flux:text class="font-medium capitalize">
                                                @switch($channelName)
                                                    @case('email') E-mel @break
                                                    @case('whatsapp') WhatsApp @break
                                                    @case('sms') SMS @break
                                                    @case('broadcast') Broadcast @break
                                                    @default {{ $channelName }}
                                                @endswitch
                                            </flux:text>
                                            <flux:badge size="sm" color="{{ $stats['open_rate'] >= 20 ? 'green' : ($stats['open_rate'] >= 10 ? 'yellow' : 'zinc') }}">
                                                {{ $stats['open_rate'] }}% buka
                                            </flux:badge>
                                        </div>
                                        <div class="grid grid-cols-4 gap-2 text-xs">
                                            <div>
                                                <span class="text-gray-500 dark:text-gray-400">Dihantar</span>
                                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stats['sent']) }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500 dark:text-gray-400">Dibuka</span>
                                                <div class="font-semibold text-green-600 dark:text-green-400">{{ number_format($stats['opened']) }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500 dark:text-gray-400">Diklik</span>
                                                <div class="font-semibold text-purple-600 dark:text-purple-400">{{ number_format($stats['clicked']) }}</div>
                                            </div>
                                            <div>
                                                <span class="text-gray-500 dark:text-gray-400">Gagal</span>
                                                <div class="font-semibold text-red-600 dark:text-red-400">{{ number_format($stats['failed']) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </flux:card>
                @endif

                <!-- Failure Reasons -->
                <flux:card>
                    <div class="p-4">
                        <flux:heading size="lg" class="mb-4">Sebab-Sebab Kegagalan</flux:heading>
                        @if(count($failureReasons) > 0)
                            <div class="space-y-3">
                                @foreach($failureReasons as $failure)
                                    <div class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                        <div class="text-sm text-red-700 dark:text-red-300 truncate max-w-[70%]" title="{{ $failure['reason'] }}">
                                            {{ $failure['reason'] }}
                                        </div>
                                        <flux:badge color="red">{{ number_format($failure['count']) }}</flux:badge>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <flux:icon name="check-circle" class="w-12 h-12 mx-auto mb-2 text-green-500" />
                                <p>Tiada kegagalan direkodkan</p>
                            </div>
                        @endif
                    </div>
                </flux:card>
            </div>
        @endif

        <!-- Logs Tab -->
        @if($activeTab === 'logs')
            <flux:card>
                <div class="p-4 border-b border-gray-200 dark:border-zinc-700">
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
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tetapan</th>
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
                                        <div class="text-sm text-gray-900 dark:text-gray-100 max-w-[180px] truncate" title="{{ $log['notification_type_label'] }}">
                                            {{ $log['notification_type_label'] }}
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
                                                @case('email') E-mel @break
                                                @case('whatsapp') WhatsApp @break
                                                @case('sms') SMS @break
                                                @default {{ ucfirst($log['channel']) }}
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
                                    <td colspan="10" class="px-4 py-12 text-center">
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
                        <div class="flex items-center gap-1">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="previousPage"
                                :disabled="$currentPage === 1"
                            >
                                Sebelum
                            </flux:button>

                            @php
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($lastPage, $currentPage + 2);

                                // Adjust if near start
                                if ($currentPage <= 3) {
                                    $endPage = min($lastPage, 5);
                                }

                                // Adjust if near end
                                if ($currentPage >= $lastPage - 2) {
                                    $startPage = max(1, $lastPage - 4);
                                }
                            @endphp

                            @if($startPage > 1)
                                <flux:button size="sm" variant="ghost" wire:click="gotoPage(1)">1</flux:button>
                                @if($startPage > 2)
                                    <span class="px-2 text-gray-400 dark:text-gray-500">...</span>
                                @endif
                            @endif

                            @for($page = $startPage; $page <= $endPage; $page++)
                                <flux:button
                                    size="sm"
                                    variant="{{ $page === $currentPage ? 'primary' : 'ghost' }}"
                                    wire:click="gotoPage({{ $page }})"
                                >
                                    {{ $page }}
                                </flux:button>
                            @endfor

                            @if($endPage < $lastPage)
                                @if($endPage < $lastPage - 1)
                                    <span class="px-2 text-gray-400 dark:text-gray-500">...</span>
                                @endif
                                <flux:button size="sm" variant="ghost" wire:click="gotoPage({{ $lastPage }})">{{ $lastPage }}</flux:button>
                            @endif

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
        @endif
    </div>

    @if($activeTab === 'statistics')
        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('livewire:navigated', initChart);
            document.addEventListener('DOMContentLoaded', initChart);

            function initChart() {
                const ctx = document.getElementById('monthlyChart');
                if (!ctx) return;

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
                                grid: { color: gridColor },
                                ticks: { color: textColor }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: gridColor },
                                ticks: { color: textColor }
                            }
                        }
                    }
                });
            }

            Livewire.on('yearChanged', () => {
                setTimeout(initChart, 100);
            });
        </script>
        @endpush
    @endif
</div>
