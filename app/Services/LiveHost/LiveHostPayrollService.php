<?php

namespace App\Services\LiveHost;

use App\Exceptions\LiveHost\PayrollRunStateException;
use App\Models\LiveHostPayrollItem;
use App\Models\LiveHostPayrollRun;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates verified live sessions into a bi-monthly payroll run and computes
 * base salary, per-live rates, GMV commission, and 2-level overrides for every
 * active host. See design doc §5.2 for the full specification.
 *
 * Tier-based override semantics (§5.2 revised):
 *
 *   For each direct (or L2) downline, sum monthly GMV per platform within the
 *   period, look up the downline's tier on that platform at period_end, and
 *   apply the tier's `l1_percent` (or `l2_percent`) to the monthly GMV. The
 *   upline's override is the sum of these contributions across every platform
 *   the downline had GMV on during the period.
 *
 *     override_l1 = Σ over direct downlines:
 *                     Σ over platforms with GMV:
 *                       monthly_gmv × tier.l1_percent / 100
 *     override_l2 = same structure, walking the L2 downline chain and using
 *                   tier.l2_percent.
 *
 * Overrides are derived from the DOWNLINE's tier schedule (not the upline's
 * profile) — the schedule is the single source of truth for all three
 * percentages (internal, l1, l2) on a given (host, platform) combination.
 */
class LiveHostPayrollService
{
    public function __construct(
        private CommissionCalculator $calculator,
        private CommissionTierResolver $tierResolver,
    ) {}

    /**
     * Generate a fresh draft payroll run for the given period.
     *
     * Cutoff is period_end + 14 days — the hard deadline for PIC to finalise
     * adjustments before the run is locked.
     */
    public function generateDraft(Carbon $periodStart, Carbon $periodEnd, User $actor): LiveHostPayrollRun
    {
        return DB::transaction(function () use ($periodStart, $periodEnd) {
            $run = LiveHostPayrollRun::create([
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'cutoff_date' => $periodEnd->copy()->addDays(14)->toDateString(),
                'status' => 'draft',
            ]);

            $this->computeItemsForRun($run, $periodStart, $periodEnd);

            return $run->load('items');
        });
    }

    /**
     * Regenerate items on a draft run. Used after the PIC adds adjustments
     * against sessions whose run is still in draft. Throws if the run has
     * already been locked.
     */
    public function recompute(LiveHostPayrollRun $run): LiveHostPayrollRun
    {
        if ($run->status !== 'draft') {
            throw PayrollRunStateException::cannotRecompute($run->status);
        }

        return DB::transaction(function () use ($run) {
            $run->items()->delete();

            $this->computeItemsForRun(
                $run,
                Carbon::parse($run->period_start)->startOfDay(),
                Carbon::parse($run->period_end)->endOfDay(),
            );

            return $run->load('items');
        });
    }

    /**
     * Transition a draft run to locked. Once locked, GMV adjustments and
     * recompute are blocked — payroll numbers are frozen.
     */
    public function lock(LiveHostPayrollRun $run, User $actor): LiveHostPayrollRun
    {
        if ($run->status !== 'draft') {
            throw PayrollRunStateException::cannotLock($run->status);
        }

        $run->update([
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $actor->id,
        ]);

        return $run->fresh(['items']);
    }

    /**
     * Transition a locked run to paid. Records paid_at for audit.
     */
    public function markPaid(LiveHostPayrollRun $run, User $actor): LiveHostPayrollRun
    {
        if ($run->status !== 'locked') {
            throw PayrollRunStateException::cannotMarkPaid($run->status);
        }

        $run->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return $run->fresh(['items']);
    }

    /**
     * Two-pass item builder. Pass 1 computes each host's own aggregates
     * (sessions, GMV, commission, per-live) in a monthly-GMV-tier-aware way.
     * Pass 2 walks each host's downline tree, applies per-(downline, platform)
     * tier lookups, and persists the final LiveHostPayrollItem rows.
     */
    private function computeItemsForRun(LiveHostPayrollRun $run, Carbon $periodStart, Carbon $periodEnd): void
    {
        $hosts = User::query()
            ->where('role', 'live_host')
            ->with(['commissionProfile', 'platformCommissionRates'])
            ->get();

        if ($hosts->isEmpty()) {
            return;
        }

        $sessionsByHost = $this->loadSessionsByHost($hosts->pluck('id')->all(), $periodStart, $periodEnd);

        // Pass 1 — per-host own aggregates (tier-aware; no overrides yet)
        $ownAggregates = $hosts->mapWithKeys(function (User $host) use ($sessionsByHost, $periodEnd) {
            $sessions = $sessionsByHost->get($host->id, collect());

            return [$host->id => $this->computeOwnAggregates($host, $sessions, $periodEnd)];
        });

        // Pass 2 — add overrides and persist
        foreach ($hosts as $host) {
            $own = $ownAggregates->get($host->id);

            [$overrideL1, $breakdownL1] = $this->computeOverrideLevel(
                $host->directDownlines()->get(),
                $ownAggregates,
                1,
                $periodEnd,
            );
            [$overrideL2, $breakdownL2] = $this->computeOverrideLevel(
                $host->l2Downlines()->get(),
                $ownAggregates,
                2,
                $periodEnd,
            );

            $baseSalary = round((float) ($host->commissionProfile?->base_salary_myr ?? 0), 2);
            $grossTotal = round(
                $baseSalary
                    + $own['total_per_live_myr']
                    + $own['gmv_commission_myr']
                    + $overrideL1
                    + $overrideL2,
                2
            );

            // Deductions aren't computed here — future task. Net == gross for now.
            $deductions = 0.0;
            $netPayout = round($grossTotal - $deductions, 2);

            LiveHostPayrollItem::create([
                'payroll_run_id' => $run->id,
                'user_id' => $host->id,
                'base_salary_myr' => $baseSalary,
                'sessions_count' => $own['sessions_count'],
                'total_per_live_myr' => $own['total_per_live_myr'],
                'total_gmv_myr' => $own['total_gmv_myr'],
                'total_gmv_adjustment_myr' => $own['total_gmv_adjustment_myr'],
                'net_gmv_myr' => $own['net_gmv_myr'],
                'gmv_commission_myr' => $own['gmv_commission_myr'],
                'override_l1_myr' => $overrideL1,
                'override_l2_myr' => $overrideL2,
                'gross_total_myr' => $grossTotal,
                'deductions_myr' => $deductions,
                'net_payout_myr' => $netPayout,
                'calculation_breakdown_json' => [
                    'sessions' => $own['session_breakdown'],
                    'overrides_l1' => $breakdownL1,
                    'overrides_l2' => $breakdownL2,
                ],
            ]);
        }
    }

    /**
     * Load all verified sessions for the hosts in the period, eager-loading
     * the relations CommissionCalculator needs (including `platformAccount.
     * platform` so the tier resolver can look up rates without N+1). Grouped
     * by live_host_id so the caller can retrieve them per host without
     * re-querying.
     *
     * @param  array<int, int>  $hostIds
     * @return Collection<int, Collection<int, LiveSession>>
     */
    private function loadSessionsByHost(array $hostIds, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return LiveSession::query()
            ->whereIn('live_host_id', $hostIds)
            ->where('verification_status', 'verified')
            ->whereBetween('actual_end_at', [$periodStart, $periodEnd])
            ->with([
                'liveHost.commissionProfile',
                'liveHost.platformCommissionRates',
                'platformAccount.platform',
            ])
            ->get()
            ->groupBy('live_host_id');
    }

    /**
     * Sum per-session figures for one host's own earnings.
     *
     * This is a two-step pass per host:
     *   1. Aggregate monthly GMV per platform (net_gmv per platform summed
     *      across every verified session in the period).
     *   2. Loop the sessions again and call
     *      CommissionCalculator::forSessionInMonthlyContext with the
     *      platform-level monthly GMV so the tier resolver picks the right
     *      bracket for each session.
     *
     * The per-session session_total stays accurate because every session on
     * the same platform sees the same monthly GMV context, so summing over
     * sessions equals `monthly_gmv × tier.internal_percent` for that tier.
     *
     * @param  Collection<int, LiveSession>  $sessions
     * @return array{
     *     sessions_count: int,
     *     total_per_live_myr: float,
     *     total_gmv_myr: float,
     *     total_gmv_adjustment_myr: float,
     *     net_gmv_myr: float,
     *     gmv_commission_myr: float,
     *     platform_monthly_gmv: array<int, float>,
     *     session_breakdown: array<int, array<string, mixed>>
     * }
     */
    private function computeOwnAggregates(User $host, Collection $sessions, Carbon $periodEnd): array
    {
        // Step 1 — aggregate monthly GMV per platform.
        $monthlyGmvByPlatform = [];
        foreach ($sessions as $session) {
            $platformId = $session->platformAccount?->platform_id;
            if ($platformId === null) {
                continue;
            }
            $sessionNetGmv = (float) ($session->gmv_amount ?? 0)
                + (float) ($session->gmv_adjustment ?? 0);
            $monthlyGmvByPlatform[$platformId] = ($monthlyGmvByPlatform[$platformId] ?? 0.0) + $sessionNetGmv;
        }

        $totalGmv = 0.0;
        $totalAdjustment = 0.0;
        $netGmv = 0.0;
        $gmvCommission = 0.0;
        $totalPerLive = 0.0;
        $breakdown = [];

        // Step 2 — compute per-session figures using monthly-GMV context.
        foreach ($sessions as $session) {
            $platformId = $session->platformAccount?->platform_id;
            $monthlyGmv = $platformId !== null
                ? (float) ($monthlyGmvByPlatform[$platformId] ?? 0.0)
                : 0.0;

            $result = $this->calculator->forSessionInMonthlyContext(
                $session,
                $monthlyGmv,
                $periodEnd,
            );

            $totalGmv += (float) ($session->gmv_amount ?? 0);
            $totalAdjustment += (float) ($session->gmv_adjustment ?? 0);
            $netGmv += $result['net_gmv'];
            $gmvCommission += $result['gmv_commission'];
            $totalPerLive += $result['per_live_rate'];

            $breakdown[] = [
                'id' => $session->id,
                'actual_end_at' => optional($session->actual_end_at)->toIso8601String(),
                'gmv_amount' => round((float) ($session->gmv_amount ?? 0), 2),
                'gmv_adjustment' => round((float) ($session->gmv_adjustment ?? 0), 2),
                'net_gmv' => $result['net_gmv'],
                'platform_rate_percent' => $result['platform_rate_percent'],
                'gmv_commission' => $result['gmv_commission'],
                'per_live' => $result['per_live_rate'],
                'session_total' => $result['session_total'],
                'rate_source' => $result['rate_source'] ?? null,
            ];
        }

        return [
            'sessions_count' => $sessions->count(),
            'total_per_live_myr' => round($totalPerLive, 2),
            'total_gmv_myr' => round($totalGmv, 2),
            'total_gmv_adjustment_myr' => round($totalAdjustment, 2),
            'net_gmv_myr' => round($netGmv, 2),
            'gmv_commission_myr' => round($gmvCommission, 2),
            'platform_monthly_gmv' => array_map(fn ($v) => round((float) $v, 2), $monthlyGmvByPlatform),
            'session_breakdown' => $breakdown,
        ];
    }

    /**
     * Compute one override level (L1 or L2) for the given upline. For each
     * downline, walk their per-platform monthly GMV and look up the downline's
     * tier for that (platform, gmv, asOf) triple. The override percentage
     * comes from the tier row itself — `l1_percent` for $level === 1,
     * `l2_percent` for $level === 2.
     *
     * Returns a tuple: [override_total, breakdown_rows]. The breakdown is the
     * audit-ready per-(downline, platform) detail stored in
     * `calculation_breakdown_json` — each row captures tier_id, tier_number,
     * monthly_gmv_myr, override_rate_percent, override_amount, and a `reason`
     * field so a PIC can reconstruct why a given override was paid (or why
     * it paid zero). Reason values:
     *
     *   - null                     a real payout occurred
     *   - 'below_tier_1_floor'     host has a schedule but monthly GMV is
     *                              below the lowest tier's min boundary
     *   - 'no_schedule_configured' host has no tier schedule at all on that
     *                              platform (admin needs to create one)
     *   - 'zero_rate_in_tier'      the resolved tier exists but its
     *                              `l1_percent`/`l2_percent` is 0 (typical
     *                              of the zero-override backfill strategy;
     *                              admin hasn't configured override rates yet)
     *
     * Zero-amount rows are intentionally emitted so PICs can distinguish these
     * cases at a glance instead of wondering whether the computation silently
     * skipped a downline.
     *
     * @param  Collection<int, User>  $downlines
     * @param  Collection<int, array<string, mixed>>  $ownAggregates  keyed by user_id
     * @return array{0: float, 1: array<int, array<string, mixed>>}
     */
    private function computeOverrideLevel(Collection $downlines, Collection $ownAggregates, int $level, Carbon $periodEnd): array
    {
        if ($downlines->isEmpty()) {
            return [0.0, []];
        }

        $total = 0.0;
        $breakdown = [];

        foreach ($downlines as $downline) {
            $downlineAggregates = $ownAggregates->get($downline->id);
            if ($downlineAggregates === null) {
                continue;
            }

            foreach ($downlineAggregates['platform_monthly_gmv'] ?? [] as $platformId => $monthlyGmv) {
                $monthlyGmv = (float) $monthlyGmv;
                if ($monthlyGmv <= 0.0) {
                    continue;
                }

                $platform = Platform::find($platformId);
                if ($platform === null) {
                    continue;
                }

                $tier = $this->tierResolver->resolveTier($downline, $platform, $monthlyGmv, $periodEnd);

                if ($tier === null) {
                    $hasSchedule = $this->tierResolver->hasAnyActiveTier($downline, $platform, $periodEnd);

                    $breakdown[] = [
                        'downline_user_id' => $downline->id,
                        'downline_name' => $downline->name,
                        'platform_id' => (int) $platformId,
                        'monthly_gmv_myr' => round($monthlyGmv, 2),
                        'tier_id' => null,
                        'tier_number' => null,
                        'override_rate_percent' => null,
                        'override_amount' => 0.00,
                        'reason' => $hasSchedule ? 'below_tier_1_floor' : 'no_schedule_configured',
                    ];

                    continue;
                }

                $ratePercent = $level === 1
                    ? (float) $tier->l1_percent
                    : (float) $tier->l2_percent;

                if ($ratePercent <= 0.0) {
                    $breakdown[] = [
                        'downline_user_id' => $downline->id,
                        'downline_name' => $downline->name,
                        'platform_id' => (int) $platformId,
                        'monthly_gmv_myr' => round($monthlyGmv, 2),
                        'tier_id' => $tier->id,
                        'tier_number' => (int) $tier->tier_number,
                        'override_rate_percent' => 0.00,
                        'override_amount' => 0.00,
                        'reason' => 'zero_rate_in_tier',
                    ];

                    continue;
                }

                $amount = round($monthlyGmv * $ratePercent / 100, 2);
                $total += $amount;

                $breakdown[] = [
                    'downline_user_id' => $downline->id,
                    'downline_name' => $downline->name,
                    'platform_id' => (int) $platformId,
                    'monthly_gmv_myr' => round($monthlyGmv, 2),
                    'tier_id' => $tier->id,
                    'tier_number' => (int) $tier->tier_number,
                    'override_rate_percent' => $ratePercent,
                    'override_amount' => $amount,
                    'reason' => null,
                ];
            }
        }

        return [round($total, 2), $breakdown];
    }
}
