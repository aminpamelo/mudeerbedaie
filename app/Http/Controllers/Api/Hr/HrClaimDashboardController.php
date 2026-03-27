<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ClaimRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HrClaimDashboardController extends Controller
{
    /**
     * Get claim dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $pendingCount = ClaimRequest::query()
            ->where('status', 'pending')
            ->count();

        $monthlyTotal = ClaimRequest::query()
            ->whereIn('status', ['approved', 'paid'])
            ->whereYear('claim_date', now()->year)
            ->whereMonth('claim_date', now()->month)
            ->sum('approved_amount');

        $yearlyTotal = ClaimRequest::query()
            ->whereIn('status', ['approved', 'paid'])
            ->whereYear('claim_date', now()->year)
            ->sum('approved_amount');

        $pendingAmount = ClaimRequest::query()
            ->where('status', 'pending')
            ->sum('amount');

        $byStatus = ClaimRequest::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $recentRequests = ClaimRequest::query()
            ->with(['employee', 'claimType'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'pending_count' => $pendingCount,
                'monthly_total' => (float) $monthlyTotal,
                'yearly_total' => (float) $yearlyTotal,
                'pending_amount' => (float) $pendingAmount,
                'by_status' => $byStatus,
                'recent_requests' => $recentRequests,
            ],
        ]);
    }
}
