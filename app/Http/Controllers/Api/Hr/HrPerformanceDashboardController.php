<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceReview;
use App\Models\ReviewCycle;
use Illuminate\Http\JsonResponse;

class HrPerformanceDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $activeCycles = ReviewCycle::whereIn('status', ['active', 'in_review'])->count();
        $totalReviews = PerformanceReview::count();
        $completedReviews = PerformanceReview::where('status', 'completed')->count();
        $activePips = PerformanceImprovementPlan::where('status', 'active')->count();

        $ratingDistribution = PerformanceReview::where('status', 'completed')
            ->whereNotNull('overall_rating')
            ->selectRaw('
                CASE
                    WHEN overall_rating < 1.5 THEN 1
                    WHEN overall_rating < 2.5 THEN 2
                    WHEN overall_rating < 3.5 THEN 3
                    WHEN overall_rating < 4.5 THEN 4
                    ELSE 5
                END as rating_bucket,
                COUNT(*) as count
            ')
            ->groupBy('rating_bucket')
            ->pluck('count', 'rating_bucket');

        return response()->json([
            'data' => [
                'active_cycles' => $activeCycles,
                'total_reviews' => $totalReviews,
                'completed_reviews' => $completedReviews,
                'completion_rate' => $totalReviews > 0 ? round(($completedReviews / $totalReviews) * 100) : 0,
                'active_pips' => $activePips,
                'rating_distribution' => $ratingDistribution,
            ],
        ]);
    }
}
