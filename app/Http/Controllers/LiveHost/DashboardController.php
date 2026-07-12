<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostMentee;
use App\Models\LiveHostMenteeDailyVideo;
use App\Models\LiveHostMenteeMonthlyScore;
use App\Models\LiveHostMentoringProgram;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use App\Services\LiveHost\SessionCoverageMatrix;
use App\Services\Mentoring\MenteeDailySalesResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        if ($request->user()?->isLiveHostAssistant()) {
            return Inertia::render('SchedulerDashboard', $this->schedulerStats());
        }

        return Inertia::render('Dashboard', [
            'coverage' => $this->coverageSummary(),
            'mentoring' => $this->mentoringSummary(),
            'pendingReplacements' => SessionReplacementRequest::query()
                ->where('status', SessionReplacementRequest::STATUS_PENDING)
                ->count(),
        ]);
    }

    /**
     * Session-slot settlement health for the current month: the four coverage
     * buckets (belum upload / belum verify / verified / TikTok suggestions)
     * summed across every linked account, plus a settled percentage. Mirrors the
     * Coverage Matrix so the dashboard is its at-a-glance headline.
     *
     * @return array<string, mixed>
     */
    private function coverageSummary(): array
    {
        $now = CarbonImmutable::now();
        $monthValue = $now->format('Y-m');

        $coverage = app(SessionCoverageMatrix::class)->monthly($now->year, $now->month, $now->month, [
            'hostId' => null,
            'platformAccountId' => null,
            'liveAccountId' => null,
            'includeUnlinked' => false,
        ]);

        $keys = ['needs_upload', 'needs_verify', 'verified', 'suggestions', 'total'];
        $totals = array_fill_keys($keys, 0);
        $accountsOutstanding = 0;

        foreach ($coverage['accounts'] as $account) {
            $cell = $account['scores'][$monthValue] ?? [];
            foreach ($keys as $k) {
                $totals[$k] += $cell[$k] ?? 0;
            }
            if (($cell['needs_upload'] ?? 0) + ($cell['needs_verify'] ?? 0) > 0) {
                $accountsOutstanding++;
            }
        }

        return [
            'month_label' => $now->format('M Y'),
            'needs_upload' => $totals['needs_upload'],
            'needs_verify' => $totals['needs_verify'],
            'verified' => $totals['verified'],
            'suggestions' => $totals['suggestions'],
            'total_sessions' => $totals['total'],
            'settled_pct' => $totals['total'] > 0 ? (int) round($totals['verified'] / $totals['total'] * 100) : null,
            'accounts' => count($coverage['accounts']),
            'accounts_outstanding' => $accountsOutstanding,
            'sessions_today' => LiveSession::whereDate('scheduled_start_at', today())->count(),
        ];
    }

    /**
     * Cross-program mentoring health for the current month: active program /
     * mentee counts, summed effective sales (auto GMV + overrides), average
     * attitude, today's daily-video compliance, and a per-program breakdown.
     * Mirrors the Mentoring Overview so the dashboard is its at-a-glance headline.
     *
     * @return array<string, mixed>
     */
    private function mentoringSummary(): array
    {
        $now = CarbonImmutable::now();
        $monthKey = $now->format('Y-m');
        $periods = [['year' => $now->year, 'month' => $now->month]];

        $programs = LiveHostMentoringProgram::query()
            ->where('status', 'active')
            ->withCount(['mentees as active_mentees_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('title')
            ->get();

        // Scope to mentees inside ACTIVE programs so the figures reconcile with
        // the per-program breakdown and the Mentoring Overview page.
        $activeMentees = LiveHostMentee::query()
            ->where('status', 'active')
            ->whereIn('program_id', $programs->pluck('id'))
            ->get(['id', 'program_id', 'mentee_user_id']);

        $salesTotals = app(MenteeDailySalesResolver::class)->monthlyTotals($activeMentees, $periods);

        $salesByProgram = [];
        $totalSales = 0.0;
        foreach ($activeMentees as $mentee) {
            $sales = (float) ($salesTotals[$mentee->id][$monthKey] ?? 0);
            $totalSales += $sales;
            $salesByProgram[$mentee->program_id] = ($salesByProgram[$mentee->program_id] ?? 0) + $sales;
        }

        $attitudes = LiveHostMenteeMonthlyScore::query()
            ->whereIn('mentee_id', $activeMentees->pluck('id'))
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->whereNotNull('attitude_score')
            ->pluck('attitude_score');

        return [
            'month_label' => $now->format('M Y'),
            'active_programs' => $programs->count(),
            'active_mentees' => $activeMentees->count(),
            'sales_month' => round($totalSales, 2),
            'avg_attitude' => $attitudes->isNotEmpty() ? (int) round($attitudes->avg()) : null,
            'video' => $this->videoComplianceFor($activeMentees->pluck('id')),
            'programs' => $programs->map(fn (LiveHostMentoringProgram $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'mentees' => (int) ($p->active_mentees_count ?? 0),
                'sales_month' => round($salesByProgram[$p->id] ?? 0, 2),
            ])->values(),
        ];
    }

    /**
     * Today's daily-video KPI compliance for a set of mentees: how many logged at
     * least one video today, the shortfall, and total videos. The daily video is
     * a mentee-scoped KPI logged by hosts in the Pocket.
     *
     * @param  Collection<int, int>  $menteeIds
     * @return array{active_mentees: int, posted: int, missing: int, videos_today: int, pct: int|null}
     */
    private function videoComplianceFor(Collection $menteeIds): array
    {
        $today = today()->toDateString();
        $total = $menteeIds->count();

        $posted = LiveHostMenteeDailyVideo::query()
            ->whereIn('mentee_id', $menteeIds)
            ->whereDate('video_date', $today)
            ->distinct()
            ->count('mentee_id');

        $videosToday = LiveHostMenteeDailyVideo::query()
            ->whereIn('mentee_id', $menteeIds)
            ->whereDate('video_date', $today)
            ->count();

        return [
            'active_mentees' => $total,
            'posted' => $posted,
            'missing' => max(0, $total - $posted),
            'videos_today' => $videosToday,
            'pct' => $total > 0 ? (int) round($posted / $total * 100) : null,
        ];
    }

    /**
     * JSON endpoint used by the Dashboard page to poll the "live now" counters
     * every ~10 seconds without a full Inertia reload.
     */
    public function liveNowJson(Request $request)
    {
        abort_if($request->user()?->isLiveHostAssistant() === true, 403);

        return response()->json([
            'liveNow' => $this->liveNow(),
            'stats' => [
                'liveNow' => LiveSession::where('status', 'live')->count(),
                'totalHosts' => User::where('role', 'live_host')->count(),
                'activeHosts' => User::where('role', 'live_host')->where('status', 'active')->count(),
            ],
        ]);
    }

    /**
     * Props for the scheduler-focused dashboard. No GMV, commission, or
     * financial fields — the livehost_assistant role must not see revenue.
     *
     * @return array<string, mixed>
     */
    private function schedulerStats(): array
    {
        $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);
        $weekEnd = $weekStart->endOfWeek(CarbonImmutable::SATURDAY);
        $today = today();

        $weekSlots = LiveScheduleAssignment::query()
            ->where('is_template', false)
            ->whereDate('schedule_date', '>=', $weekStart->toDateString())
            ->whereDate('schedule_date', '<=', $weekEnd->toDateString())
            ->get(['id', 'schedule_date', 'live_host_id', 'platform_account_id', 'status']);

        $assignedCount = $weekSlots->whereNotNull('live_host_id')->count();
        $totalCount = $weekSlots->count();

        $todaySlots = LiveScheduleAssignment::query()
            ->where('is_template', false)
            ->whereDate('schedule_date', $today)
            ->with(['liveHost:id,name', 'platformAccount:id,name'])
            ->orderBy('schedule_date')
            ->orderBy('id')
            ->get(['id', 'schedule_date', 'live_host_id', 'platform_account_id', 'status']);

        return [
            'stats' => [
                'coveragePercent' => $totalCount === 0 ? 0 : (int) round($assignedCount / $totalCount * 100),
                'unassignedCount' => $totalCount - $assignedCount,
                'activeHosts' => User::query()->where('role', 'live_host')->where('status', 'active')->count(),
                'platformAccounts' => PlatformAccount::query()->count(),
            ],
            'unassignedThisWeek' => $weekSlots
                ->whereNull('live_host_id')
                ->take(20)
                ->values()
                ->map(fn (LiveScheduleAssignment $s) => [
                    'id' => $s->id,
                    'schedule_date' => $s->schedule_date?->toDateString(),
                    'platform_account_id' => $s->platform_account_id,
                    'status' => $s->status,
                ]),
            'todaySlots' => $todaySlots->map(fn (LiveScheduleAssignment $s) => [
                'id' => $s->id,
                'schedule_date' => $s->schedule_date?->toDateString(),
                'status' => $s->status,
                'host_name' => $s->liveHost?->name,
                'platform_account_label' => $s->platformAccount?->name,
            ]),
        ];
    }

    private function liveNow(): Collection
    {
        return LiveSession::query()
            ->with(['platformAccount.platform', 'liveHost'])
            ->where('status', 'live')
            ->orderByDesc('actual_start_at')
            ->take(5)
            ->get()
            ->map(fn (LiveSession $s) => [
                'id' => $s->id,
                'hostName' => $s->liveHost?->name,
                'initials' => $this->initials($s->liveHost?->name),
                'platformAccount' => $s->platformAccount?->name,
                'platformType' => $s->platformAccount?->platform?->slug,
                'sessionId' => 'LS-'.str_pad((string) $s->id, 5, '0', STR_PAD_LEFT),
                'startedAt' => $s->actual_start_at?->toIso8601String(),
                'viewers' => 0,
            ]);
    }

    private function initials(?string $name): string
    {
        if (! $name) {
            return '??';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(mb_substr(($parts[0] ?? '').($parts[1] ?? ''), 0, 2, 'UTF-8'), 'UTF-8');
    }
}
