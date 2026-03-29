<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ClaimRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Get pending claim requests for dashboard.
     */
    public function pending(Request $request): JsonResponse
    {
        $claims = ClaimRequest::query()
            ->with(['employee', 'claimType'])
            ->where('status', 'pending')
            ->orderByDesc('submitted_at')
            ->paginate($request->get('per_page', 10));

        return response()->json($claims);
    }

    /**
     * Get claims distribution by type for dashboard.
     */
    public function distribution(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month');

        $query = ClaimRequest::query()
            ->join('claim_types', 'claim_requests.claim_type_id', '=', 'claim_types.id')
            ->whereIn('claim_requests.status', ['approved', 'paid'])
            ->whereYear('claim_requests.claim_date', $year);

        if ($month) {
            $query->whereMonth('claim_requests.claim_date', $month);
        }

        $distribution = $query
            ->select(
                'claim_types.name',
                DB::raw('count(*) as count'),
                DB::raw('sum(claim_requests.approved_amount) as total_amount')
            )
            ->groupBy('claim_types.id', 'claim_types.name')
            ->orderByDesc('total_amount')
            ->get();

        return response()->json(['data' => $distribution]);
    }
}
