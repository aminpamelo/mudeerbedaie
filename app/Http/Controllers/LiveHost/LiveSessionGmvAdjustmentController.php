<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreLiveSessionGmvAdjustmentRequest;
use App\Models\LiveHostPayrollRun;
use App\Models\LiveSession;
use App\Models\LiveSessionGmvAdjustment;
use App\Services\LiveHost\CommissionCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiveSessionGmvAdjustmentController extends Controller
{
    public function store(StoreLiveSessionGmvAdjustmentRequest $request, LiveSession $session): RedirectResponse
    {
        $this->assertNotPayrollLocked($session);

        DB::transaction(function () use ($request, $session) {
            LiveSessionGmvAdjustment::create([
                'live_session_id' => $session->id,
                'amount_myr' => $request->validated('amount'),
                'reason' => $request->validated('reason'),
                'status' => 'approved',
                'adjusted_by' => $request->user()->id,
                'adjusted_at' => now(),
            ]);

            $this->recomputeSession($session);
        });

        return back()->with('success', 'GMV adjustment recorded.');
    }

    public function destroy(Request $request, LiveSession $session, LiveSessionGmvAdjustment $adjustment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin_livehost', 'admin'], true), 403);
        abort_unless($adjustment->live_session_id === $session->id, 404);

        $this->assertNotPayrollLocked($session);

        DB::transaction(function () use ($adjustment, $session) {
            $adjustment->delete();
            $this->recomputeSession($session);
        });

        return back()->with('success', 'GMV adjustment removed.');
    }

    /**
     * Approve a `proposed` adjustment (typically created by the
     * OrderRefundReconciler) so it contributes to the session's
     * cached gmv_adjustment aggregate.
     */
    public function approve(Request $request, LiveSession $session, LiveSessionGmvAdjustment $adjustment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin_livehost', 'admin'], true), 403);
        abort_unless($adjustment->live_session_id === $session->id, 404);
        abort_if($adjustment->status !== 'proposed', 422, 'Only proposed adjustments may be approved.');

        $this->assertNotPayrollLocked($session);

        DB::transaction(function () use ($adjustment, $session, $user) {
            $adjustment->update([
                'status' => 'approved',
                'adjusted_by' => $adjustment->adjusted_by ?? $user->id,
            ]);

            $this->recomputeSession($session);
        });

        return back()->with('success', 'GMV adjustment approved.');
    }

    /**
     * Reject a `proposed` adjustment. The row stays in the database for audit
     * but never contributes to the session's cached aggregate (the `approved`
     * scope filters it out).
     */
    public function reject(Request $request, LiveSession $session, LiveSessionGmvAdjustment $adjustment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin_livehost', 'admin'], true), 403);
        abort_unless($adjustment->live_session_id === $session->id, 404);
        abort_if($adjustment->status !== 'proposed', 422, 'Only proposed adjustments may be rejected.');

        $this->assertNotPayrollLocked($session);

        $adjustment->update(['status' => 'rejected']);

        return back()->with('success', 'GMV adjustment rejected.');
    }

    /**
     * Hard-stop adjustments when a locked payroll run covers this session's
     * calendar month. Keeps the payroll contract intact: once a period is
     * locked, the numbers feeding it must not drift.
     */
    private function assertNotPayrollLocked(LiveSession $session): void
    {
        if ($session->actual_end_at === null) {
            return;
        }

        $locked = LiveHostPayrollRun::query()
            ->where('status', 'locked')
            ->where('period_start', '<=', $session->actual_end_at)
            ->where('period_end', '>=', $session->actual_end_at)
            ->exists();

        abort_if(
            $locked,
            403,
            'Payroll locked for this period; GMV adjustments are no longer allowed.'
        );
    }

    /**
     * Recompute the cached `gmv_adjustment` aggregate on the parent session
     * and, if the session is already verified (gmv_locked_at set),
     * re-snapshot `commission_snapshot_json` so the audit trail reflects
     * the new net_gmv.
     *
     * Only `approved` rows contribute — `proposed` and `rejected` rows are
     * excluded.
     */
    private function recomputeSession(LiveSession $session): void
    {
        $total = (float) LiveSessionGmvAdjustment::query()
            ->where('live_session_id', $session->id)
            ->approved()
            ->sum('amount_myr');

        $session->gmv_adjustment = $total;

        if ($session->gmv_locked_at !== null) {
            $session->commission_snapshot_json = app(CommissionCalculator::class)
                ->snapshot($session, auth()->user());
        }

        $session->save();
    }
}
