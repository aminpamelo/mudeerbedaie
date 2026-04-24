<?php

namespace App\Services\LiveHost;

use App\Models\LiveHostPlatformCommissionRate;
use App\Models\LiveSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class CommissionCalculator
{
    public function __construct(
        private CommissionTierResolver $tierResolver,
    ) {}

    /**
     * Compute commission for a single live session.
     *
     * Formula (per design doc §5.1):
     *   net_gmv        = gmv_amount + gmv_adjustment
     *   gmv_commission = net_gmv × host_platform_rate_percent
     *   per_live_rate  = missed ? 0 : host.per_live_rate_myr
     *   session_total  = gmv_commission + per_live_rate
     *
     * Historical fidelity: the commission rate is resolved against the
     * session's actual_start_at (falling back to scheduled_start_at, then
     * now) so sessions run last month still use last month's rate if it
     * changed since. This matters for snapshot (Task 12) and payroll
     * recompute (Task 25) where past sessions must not be re-rated.
     *
     * Callers SHOULD eager-load `liveHost.commissionProfile`,
     * `liveHost.platformCommissionRates`, and `platformAccount` to avoid
     * N+1 — especially the observer (Task 13) and payroll service (Task 25)
     * which call this per session in bulk.
     *
     * Stateless pure calculation — returns an array. Persistence of the
     * snapshot (Task 12) is handled by a separate method that wraps this.
     *
     * @return array{
     *     net_gmv: float,
     *     platform_rate_percent: float,
     *     gmv_commission: float,
     *     per_live_rate: float,
     *     session_total: float,
     *     warnings: array<int, string>
     * }
     */
    public function forSession(LiveSession $session): array
    {
        $warnings = [];

        $netGmv = (float) ($session->gmv_amount ?? 0)
            + (float) ($session->gmv_adjustment ?? 0);

        $host = $session->liveHost;
        $platformId = $session->platformAccount?->platform_id;

        $rate = $host && $platformId
            ? $this->resolveRateAt($host->id, $platformId, $this->asOf($session))
            : null;

        // Only flag a missing platform rate when there is actual GMV to
        // compute against. A zero-GMV session with no rate produces zero
        // commission either way, so surfacing a warning would be noise.
        if (! $rate && $netGmv > 0) {
            $warnings[] = 'missing_platform_rate';
        }

        $ratePercent = (float) ($rate?->commission_rate_percent ?? 0);
        $gmvCommission = round($netGmv * $ratePercent / 100, 2);

        $profile = $host?->commissionProfile;
        $isMissed = $session->status === 'missed';
        $perLiveRate = ($isMissed || ! $profile)
            ? 0.0
            : round((float) $profile->per_live_rate_myr, 2);

        return [
            'net_gmv' => round($netGmv, 2),
            'platform_rate_percent' => $ratePercent,
            'gmv_commission' => $gmvCommission,
            'per_live_rate' => $perLiveRate,
            'session_total' => round($gmvCommission + $perLiveRate, 2),
            'warnings' => $warnings,
        ];
    }

    /**
     * Compute commission for a single live session using the tier-based rate
     * model. Unlike forSession(), the commission rate is not a flat per-host-
     * per-platform number — it depends on the host's TOTAL monthly GMV on the
     * platform (passed as $monthlyGmvForPlatform). The caller is responsible
     * for aggregating monthly GMV before calling this method.
     *
     * Per-session semantics are preserved: each session still reports
     * `session.net_gmv × tier.internal_percent / 100`. Summing across all
     * sessions in the month yields `monthly_gmv × tier.internal_percent`,
     * matching the business intent.
     *
     * Return shape is identical to forSession() plus a `rate_source` array
     * documenting which tier was applied (or why none matched). The shape is
     * symmetric across branches — all five keys (`tier_id`, `tier_number`,
     * `internal_percent`, `monthly_gmv_myr`, `reason`) are always present,
     * with `null` indicating inapplicability. Callers can reuse the same
     * reporting paths without worrying about missing keys.
     *
     * The `reason` vocabulary is:
     *   - null                        : a tier matched
     *   - 'missing_host_or_platform'  : session lacks host or platform account
     *   - 'no_schedule_configured'    : host has no tier rows for this platform
     *   - 'below_tier_1_floor'        : schedule exists but monthly GMV is below floor
     *
     * When no tier matches and `net_gmv > 0`, the warning string
     * `'missing_tier_match'` is emitted (distinct from forSession()'s
     * `'missing_platform_rate'` so downstream consumers can tell which rail
     * the lookup missed on).
     *
     * @return array{
     *     net_gmv: float,
     *     platform_rate_percent: float,
     *     gmv_commission: float,
     *     per_live_rate: float,
     *     session_total: float,
     *     warnings: array<int, string>,
     *     rate_source: array{
     *         tier_id: int|null,
     *         tier_number: int|null,
     *         internal_percent: float|null,
     *         monthly_gmv_myr: float,
     *         reason: string|null
     *     }
     * }
     */
    public function forSessionInMonthlyContext(
        LiveSession $session,
        float $monthlyGmvForPlatform,
        CarbonInterface $asOf,
    ): array {
        $warnings = [];

        $netGmv = (float) ($session->gmv_amount ?? 0)
            + (float) ($session->gmv_adjustment ?? 0);

        $host = $session->liveHost;
        $platform = $session->platformAccount?->platform;

        $tier = ($host && $platform)
            ? $this->tierResolver->resolveTier($host, $platform, $monthlyGmvForPlatform, $asOf)
            : null;

        if (! $tier && $netGmv > 0) {
            $warnings[] = 'missing_tier_match';
        }

        if ($tier) {
            $ratePercent = (float) $tier->internal_percent;
            $gmvCommission = round($netGmv * $ratePercent / 100, 2);
            $rateSource = [
                'tier_id' => $tier->id,
                'tier_number' => (int) $tier->tier_number,
                'internal_percent' => $ratePercent,
                'monthly_gmv_myr' => round($monthlyGmvForPlatform, 2),
                'reason' => null,
            ];
        } else {
            $ratePercent = 0.0;
            $gmvCommission = 0.0;

            if (! $host || ! $platform) {
                $reason = 'missing_host_or_platform';
            } elseif ($this->tierResolver->hasAnyActiveTier($host, $platform, $asOf)) {
                $reason = 'below_tier_1_floor';
            } else {
                $reason = 'no_schedule_configured';
            }

            $rateSource = [
                'tier_id' => null,
                'tier_number' => null,
                'internal_percent' => null,
                'monthly_gmv_myr' => round($monthlyGmvForPlatform, 2),
                'reason' => $reason,
            ];
        }

        $profile = $host?->commissionProfile;
        $isMissed = $session->status === 'missed';
        $perLiveRate = ($isMissed || ! $profile)
            ? 0.0
            : round((float) $profile->per_live_rate_myr, 2);

        return [
            'net_gmv' => round($netGmv, 2),
            'platform_rate_percent' => $ratePercent,
            'gmv_commission' => $gmvCommission,
            'per_live_rate' => $perLiveRate,
            'session_total' => round($gmvCommission + $perLiveRate, 2),
            'warnings' => $warnings,
            'rate_source' => $rateSource,
        ];
    }

    /**
     * Audit-capturing wrapper around forSession(). Returns the pure commission
     * figures plus three metadata keys the observer (Task 13) persists to
     * `live_sessions.commission_snapshot_json` at the moment of PIC verify:
     *
     *   - snapshotted_at          : ISO 8601 "now" — when the snapshot was taken
     *   - snapshotted_by_user_id  : id of the actor who triggered verify (null if none)
     *   - rate_source             : id of the LiveHostPlatformCommissionRate row
     *                               actually applied (null when no rate matched)
     *
     * Persistence is intentionally NOT done here — the observer writes the
     * result into the session. This keeps the service pure and trivially
     * testable, and lets payroll recompute (Task 25) reuse the same shape.
     *
     * @return array{
     *     net_gmv: float,
     *     platform_rate_percent: float,
     *     gmv_commission: float,
     *     per_live_rate: float,
     *     session_total: float,
     *     warnings: array<int, string>,
     *     snapshotted_at: string,
     *     snapshotted_by_user_id: int|null,
     *     rate_source: int|null
     * }
     */
    public function snapshot(LiveSession $session, ?User $actor = null): array
    {
        $base = $this->forSession($session);

        $host = $session->liveHost;
        $platformId = $session->platformAccount?->platform_id;

        $rate = $host && $platformId
            ? $this->resolveRateAt($host->id, $platformId, $this->asOf($session))
            : null;

        return array_merge($base, [
            'snapshotted_at' => Carbon::now()->toIso8601String(),
            'snapshotted_by_user_id' => $actor?->id,
            'rate_source' => $rate?->id,
        ]);
    }

    /**
     * Resolve the platform commission rate that was active for this host
     * at the given point in time. Returns the most-recent row whose
     * effective_from <= $asOf and (effective_to IS NULL OR effective_to > $asOf).
     */
    private function resolveRateAt(int $userId, int $platformId, Carbon $asOf): ?LiveHostPlatformCommissionRate
    {
        return LiveHostPlatformCommissionRate::query()
            ->where('user_id', $userId)
            ->where('platform_id', $platformId)
            ->where('effective_from', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $asOf);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    private function asOf(LiveSession $session): Carbon
    {
        return $session->actual_start_at
            ?? $session->scheduled_start_at
            ?? Carbon::now();
    }
}
