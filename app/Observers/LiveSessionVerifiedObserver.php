<?php

namespace App\Observers;

use App\Models\LiveSession;
use App\Services\LiveHost\CommissionCalculator;

/**
 * Locks GMV and captures an immutable commission snapshot the instant a
 * LiveSession's verification_status transitions to 'verified'. Consumed by
 * the PIC verify action (Task 14) and payroll aggregation (Task 25).
 *
 * Why `saving()` not `saved()`: we mutate two columns on the same save,
 * so firing before the SQL UPDATE lets Eloquent include those columns in
 * the same statement — no extra round-trip, no re-enter observer recursion.
 *
 * Idempotency: once `gmv_locked_at` is non-null the snapshot is frozen.
 * Re-saving a verified session (e.g. PIC edits notes later) is a no-op.
 * This matches the payroll contract: the verified commission amount may
 * never drift after the fact.
 */
class LiveSessionVerifiedObserver
{
    public function saving(LiveSession $session): void
    {
        if (! $session->isDirty('verification_status')) {
            return;
        }

        if ($session->verification_status !== 'verified') {
            return;
        }

        if ($session->gmv_locked_at !== null) {
            return;
        }

        $session->gmv_locked_at = now();
        $session->commission_snapshot_json = app(CommissionCalculator::class)
            ->snapshot($session, auth()->user());
    }
}
