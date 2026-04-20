<?php

namespace App\Services\LiveHost;

use App\Exceptions\LiveHost\PayrollRunStateException;
use App\Models\LiveHostPayrollItem;
use App\Models\LiveHostPayrollRun;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates verified live sessions into a bi-monthly payroll run and computes
 * base salary, per-live rates, GMV commission, and 2-level overrides for every
 * active host. See design doc §5.2 for the full specification.
 *
 * Override semantics (§5.2): the UPLINE earns override on the DOWNLINE's own
 * `gmv_commission` for the period. Overrides never stack on per-live rate or
 * base salary, and never compound across levels.
 *
 *   override_l1 = sum over direct downlines of:
 *                   downline.gmv_commission_in_period × this_host.override_rate_l1_percent
 *   override_l2 = sum over L2 downlines of:
 *                   l2.gmv_commission_in_period × this_host.override_rate_l2_percent
 *
 * The service computes every host's own GMV commission FIRST (pass 1), then
 * walks the upline tree to attribute overrides (pass 2). This two-pass design
 * keeps override math consistent regardless of iteration order.
 */
class LiveHostPayrollService
{
    public function __construct(private CommissionCalculator $calculator) {}

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
     * (sessions, GMV, commission, per-live). Pass 2 uses those aggregates
     * to compute L1/L2 overrides from each host's downlines and persists
     * the final LiveHostPayrollItem rows.
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

        // Pass 1 — per-host own aggregates (no overrides yet)
        $ownAggregates = $hosts->mapWithKeys(function (User $host) use ($sessionsByHost) {
            $sessions = $sessionsByHost->get($host->id, collect());

            return [$host->id => $this->computeOwnAggregates($host, $sessions)];
        });

        // Pass 2 — add overrides and persist
        foreach ($hosts as $host) {
            $own = $ownAggregates->get($host->id);
            [$overrideL1, $breakdownL1] = $this->computeOverrideLevel(
                $host,
                $host->directDownlines()->get(),
                $ownAggregates,
                (float) ($host->commissionProfile?->override_rate_l1_percent ?? 0),
            );
            [$overrideL2, $breakdownL2] = $this->computeOverrideLevel(
                $host,
                $host->l2Downlines()->get(),
                $ownAggregates,
                (float) ($host->commissionProfile?->override_rate_l2_percent ?? 0),
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
     * the relations CommissionCalculator needs. Grouped by live_host_id so
     * the caller can retrieve them per host without re-querying.
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
                'platformAccount',
            ])
            ->get()
            ->groupBy('live_host_id');
    }

    /**
     * Sum per-session figures for one host's own earnings. Uses
     * CommissionCalculator::forSession for each session so we benefit from
     * historical rate resolution (design doc §5.1).
     *
     * @param  Collection<int, LiveSession>  $sessions
     * @return array{
     *     sessions_count: int,
     *     total_per_live_myr: float,
     *     total_gmv_myr: float,
     *     total_gmv_adjustment_myr: float,
     *     net_gmv_myr: float,
     *     gmv_commission_myr: float,
     *     session_breakdown: array<int, array<string, mixed>>
     * }
     */
    private function computeOwnAggregates(User $host, Collection $sessions): array
    {
        $totalGmv = 0.0;
        $totalAdjustment = 0.0;
        $netGmv = 0.0;
        $gmvCommission = 0.0;
        $totalPerLive = 0.0;
        $breakdown = [];

        foreach ($sessions as $session) {
            $result = $this->calculator->forSession($session);

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
            ];
        }

        return [
            'sessions_count' => $sessions->count(),
            'total_per_live_myr' => round($totalPerLive, 2),
            'total_gmv_myr' => round($totalGmv, 2),
            'total_gmv_adjustment_myr' => round($totalAdjustment, 2),
            'net_gmv_myr' => round($netGmv, 2),
            'gmv_commission_myr' => round($gmvCommission, 2),
            'session_breakdown' => $breakdown,
        ];
    }

    /**
     * Compute one override level (L1 or L2) for the given upline.
     *
     * Returns a tuple: [override_amount, breakdown_rows]. The breakdown is
     * the per-downline detail that lands in `calculation_breakdown_json`
     * for the UI to render.
     *
     * @param  Collection<int, User>  $downlines
     * @param  Collection<int, array<string, mixed>>  $ownAggregates  keyed by user_id
     * @return array{0: float, 1: array<int, array<string, mixed>>}
     */
    private function computeOverrideLevel(User $upline, Collection $downlines, Collection $ownAggregates, float $ratePercent): array
    {
        if ($ratePercent <= 0 || $downlines->isEmpty()) {
            return [0.0, []];
        }

        $total = 0.0;
        $breakdown = [];

        foreach ($downlines as $downline) {
            $downlineAggregates = $ownAggregates->get($downline->id);

            if ($downlineAggregates === null) {
                continue;
            }

            $downlineCommission = $downlineAggregates['gmv_commission_myr'];
            $overrideAmount = round($downlineCommission * $ratePercent / 100, 2);
            $total += $overrideAmount;

            $breakdown[] = [
                'downline_user_id' => $downline->id,
                'downline_name' => $downline->name,
                'downline_gmv_commission' => $downlineCommission,
                'override_rate_percent' => $ratePercent,
                'override_amount' => $overrideAmount,
            ];
        }

        return [round($total, 2), $breakdown];
    }
}
