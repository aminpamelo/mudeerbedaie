<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Content;
use Illuminate\Http\JsonResponse;

class CmsDashboardController extends Controller
{
    /**
     * Return CMS dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $totalContents = Content::count();
        $inProgress = Content::where('stage', '!=', 'posted')->count();
        $postedThisMonth = Content::whereNotNull('posted_at')
            ->whereMonth('posted_at', now()->month)
            ->whereYear('posted_at', now()->year)
            ->count();
        $flaggedForAds = Content::where('is_flagged_for_ads', true)
            ->where('is_marked_for_ads', false)
            ->count();
        $markedForAds = Content::where('is_marked_for_ads', true)->count();

        $byStage = Content::query()
            ->selectRaw('stage, count(*) as count')
            ->groupBy('stage')
            ->pluck('count', 'stage');

        return response()->json([
            'data' => [
                'total_contents' => $totalContents,
                'in_progress' => $inProgress,
                'posted_this_month' => $postedThisMonth,
                'flagged_for_ads' => $flaggedForAds,
                'marked_for_ads' => $markedForAds,
                'by_stage' => $byStage,
            ],
        ]);
    }

    /**
     * Return top 10 posted contents sorted by views.
     */
    public function topPosts(): JsonResponse
    {
        $contents = Content::where('stage', 'posted')
            ->with([
                'creator:id,full_name,profile_photo',
                'stats' => function ($query) {
                    $query->latest('fetched_at')->limit(1);
                },
            ])
            ->get()
            ->sortByDesc(function ($content) {
                return $content->stats->first()?->views ?? 0;
            })
            ->take(10)
            ->values();

        return response()->json(['data' => $contents]);
    }
}
