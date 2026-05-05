<?php

declare(strict_types=1);

namespace App\Actions\LiveHost;

use App\Models\LiveSession;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\DB;

class MatchProductOrderToLiveSession
{
    /**
     * Hours after a session's actual_end_at within which a fresh order is
     * still considered attributable to that live. Mirrors OrderRefundReconciler::TAIL_HOURS.
     */
    private const TAIL_HOURS = 12;

    /**
     * Match a TikTok Shop product order to its originating live session and
     * persist the link. Returns the matched session id (or null if no match).
     *
     * No-ops for non-tiktok_shop sources or orders missing platform_account_id.
     */
    public function handle(ProductOrder $order): ?int
    {
        if ($order->source !== 'tiktok_shop' || $order->platform_account_id === null) {
            return null;
        }

        $referenceTime = $order->paid_time ?? $order->created_at;

        if ($referenceTime === null) {
            return null;
        }

        $session = LiveSession::query()
            ->where('platform_account_id', $order->platform_account_id)
            ->whereIn('status', ['live', 'ended', 'missed'])
            ->whereNotNull('actual_start_at')
            ->where('actual_start_at', '<=', $referenceTime)
            ->where(function ($query) use ($referenceTime) {
                $query->whereNull('actual_end_at')
                    ->orWhereRaw($this->dateAddExpr(), [self::TAIL_HOURS, $referenceTime]);
            })
            ->orderBy('actual_start_at', 'desc')
            ->limit(1)
            ->first();

        $sessionId = $session?->id;

        if ($order->matched_live_session_id !== $sessionId) {
            $order->matched_live_session_id = $sessionId;
            $order->save();
        }

        return $sessionId;
    }

    /**
     * Build a driver-aware "actual_end_at + X hours >= ?" raw predicate so the
     * window check works on both MySQL and SQLite.
     */
    private function dateAddExpr(): string
    {
        if (DB::getDriverName() === 'mysql') {
            return 'DATE_ADD(actual_end_at, INTERVAL ? HOUR) >= ?';
        }

        return "datetime(actual_end_at, '+' || ? || ' hours') >= ?";
    }
}
