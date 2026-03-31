<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdStat;
use App\Models\Content;
use App\Models\ContentStageAssignee;
use App\Models\ContentStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CmsPerformanceReportController extends Controller
{
    /**
     * Unified monthly performance report: team, content pipeline, and ads.
     */
    public function index(Request $request): JsonResponse
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        $startDate = now()->setDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString().' 23:59:59';
        $todayStr = now()->toDateString();

        return response()->json([
            'data' => [
                'team' => $this->teamReport($startStr, $endStr, $todayStr),
                'content' => $this->contentReport($startStr, $endStr),
                'ads' => $this->adsReport($startStr, $endStr),
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }

    private function teamReport(string $startStr, string $endStr, string $todayStr): array
    {
        $employees = ContentStageAssignee::query()
            ->join('content_stages', 'content_stage_assignees.content_stage_id', '=', 'content_stages.id')
            ->join('employees', 'content_stage_assignees.employee_id', '=', 'employees.id')
            ->join('contents', 'content_stages.content_id', '=', 'contents.id')
            ->whereNull('contents.deleted_at')
            ->select(
                'employees.id as employee_id',
                'employees.full_name',
                'employees.profile_photo',
                DB::raw('COUNT(DISTINCT content_stages.id) as total_assigned'),
                DB::raw("COUNT(DISTINCT CASE WHEN content_stages.status = 'completed' AND content_stages.completed_at >= '{$startStr}' AND content_stages.completed_at <= '{$endStr}' THEN content_stages.id END) as completed_this_month"),
                DB::raw("COUNT(DISTINCT CASE WHEN content_stages.status = 'completed' AND content_stages.due_date IS NOT NULL AND content_stages.completed_at > content_stages.due_date THEN content_stages.id END) as completed_late"),
                DB::raw("COUNT(DISTINCT CASE WHEN content_stages.status != 'completed' AND content_stages.due_date IS NOT NULL AND content_stages.due_date < '{$todayStr}' THEN content_stages.id END) as overdue"),
                DB::raw("COUNT(DISTINCT CASE WHEN content_stages.status = 'in_progress' THEN content_stages.id END) as in_progress"),
                DB::raw("COUNT(DISTINCT CASE WHEN content_stages.status = 'completed' AND content_stages.due_date IS NOT NULL AND content_stages.completed_at <= content_stages.due_date THEN content_stages.id END) as on_time"),
            )
            ->groupBy('employees.id', 'employees.full_name', 'employees.profile_photo')
            ->orderByDesc('completed_this_month')
            ->get()
            ->map(function ($row) {
                $totalWithDeadline = $row->on_time + $row->completed_late;
                $row->on_time_rate = $totalWithDeadline > 0
                    ? round(($row->on_time / $totalWithDeadline) * 100)
                    : null;
                $row->profile_photo_url = $row->profile_photo
                    ? asset('storage/'.$row->profile_photo)
                    : null;
                unset($row->profile_photo);

                return $row;
            });

        return [
            'summary' => [
                'total_stages_completed' => $employees->sum('completed_this_month'),
                'total_overdue' => $employees->sum('overdue'),
                'total_on_time' => $employees->sum('on_time'),
                'total_completed_late' => $employees->sum('completed_late'),
                'total_in_progress' => $employees->sum('in_progress'),
                'team_size' => $employees->count(),
            ],
            'employees' => $employees,
        ];
    }

    private function contentReport(string $startStr, string $endStr): array
    {
        // Content created this month
        $created = Content::whereBetween('created_at', [$startStr, $endStr])->count();

        // Content posted this month
        $posted = Content::where('stage', 'posted')
            ->whereBetween('posted_at', [$startStr, $endStr])
            ->count();

        // Content by current stage
        $byStage = Content::whereNull('deleted_at')
            ->select('stage', DB::raw('COUNT(*) as count'))
            ->groupBy('stage')
            ->pluck('count', 'stage')
            ->toArray();

        // Content by priority
        $byPriority = Content::whereNull('deleted_at')
            ->select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // Top performing content this month (by views)
        $topContent = Content::where('stage', 'posted')
            ->whereNull('deleted_at')
            ->with(['stats' => function ($q) {
                $q->latest('fetched_at')->limit(1);
            }])
            ->get()
            ->map(function ($content) {
                $latestStat = $content->stats->first();

                return [
                    'id' => $content->id,
                    'title' => $content->title,
                    'posted_at' => $content->posted_at,
                    'views' => $latestStat->views ?? 0,
                    'likes' => $latestStat->likes ?? 0,
                    'comments' => $latestStat->comments ?? 0,
                    'shares' => $latestStat->shares ?? 0,
                    'engagement_rate' => $latestStat ? $latestStat->engagement_rate : 0,
                ];
            })
            ->sortByDesc('views')
            ->take(5)
            ->values();

        // Total engagement across all posted content
        $totalEngagement = ContentStat::query()
            ->join('contents', 'content_stats.content_id', '=', 'contents.id')
            ->whereNull('contents.deleted_at')
            ->whereIn('content_stats.id', function ($query) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('content_stats')
                    ->groupBy('content_id');
            })
            ->select(
                DB::raw('SUM(content_stats.views) as total_views'),
                DB::raw('SUM(content_stats.likes) as total_likes'),
                DB::raw('SUM(content_stats.comments) as total_comments'),
                DB::raw('SUM(content_stats.shares) as total_shares'),
            )
            ->first();

        return [
            'summary' => [
                'created_this_month' => $created,
                'posted_this_month' => $posted,
                'total_active' => Content::whereNull('deleted_at')->where('stage', '!=', 'posted')->count(),
                'total_views' => (int) ($totalEngagement->total_views ?? 0),
                'total_likes' => (int) ($totalEngagement->total_likes ?? 0),
                'total_comments' => (int) ($totalEngagement->total_comments ?? 0),
                'total_shares' => (int) ($totalEngagement->total_shares ?? 0),
            ],
            'by_stage' => $byStage,
            'by_priority' => $byPriority,
            'top_content' => $topContent,
        ];
    }

    private function adsReport(string $startStr, string $endStr): array
    {
        // Campaigns created this month
        $campaignsCreated = AdCampaign::whereBetween('created_at', [$startStr, $endStr])->count();

        // Campaigns by status
        $byStatus = AdCampaign::whereNull('deleted_at')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Campaigns by platform
        $byPlatform = AdCampaign::whereNull('deleted_at')
            ->select('platform', DB::raw('COUNT(*) as count'))
            ->groupBy('platform')
            ->pluck('count', 'platform')
            ->toArray();

        // Total budget allocated
        $totalBudget = AdCampaign::whereNull('deleted_at')->sum('budget');

        // Total spend from ad stats
        $totalSpend = AdStat::query()
            ->join('ad_campaigns', 'ad_stats.ad_campaign_id', '=', 'ad_campaigns.id')
            ->whereNull('ad_campaigns.deleted_at')
            ->whereIn('ad_stats.id', function ($query) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('ad_stats')
                    ->groupBy('ad_campaign_id');
            })
            ->sum('ad_stats.spend');

        // Aggregate ad performance (latest stats per campaign)
        $adPerformance = AdStat::query()
            ->join('ad_campaigns', 'ad_stats.ad_campaign_id', '=', 'ad_campaigns.id')
            ->whereNull('ad_campaigns.deleted_at')
            ->whereIn('ad_stats.id', function ($query) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('ad_stats')
                    ->groupBy('ad_campaign_id');
            })
            ->select(
                DB::raw('SUM(ad_stats.impressions) as total_impressions'),
                DB::raw('SUM(ad_stats.clicks) as total_clicks'),
                DB::raw('SUM(ad_stats.conversions) as total_conversions'),
                DB::raw('SUM(ad_stats.spend) as total_spend'),
            )
            ->first();

        // Top campaigns by impressions
        $topCampaigns = AdCampaign::whereNull('deleted_at')
            ->with([
                'content:id,title',
                'stats' => function ($q) {
                    $q->latest('fetched_at')->limit(1);
                },
            ])
            ->get()
            ->map(function ($campaign) {
                $latestStat = $campaign->stats->first();

                return [
                    'id' => $campaign->id,
                    'content_title' => $campaign->content->title ?? '-',
                    'platform' => $campaign->platform,
                    'status' => $campaign->status,
                    'budget' => (float) $campaign->budget,
                    'impressions' => (int) ($latestStat->impressions ?? 0),
                    'clicks' => (int) ($latestStat->clicks ?? 0),
                    'spend' => (float) ($latestStat->spend ?? 0),
                    'conversions' => (int) ($latestStat->conversions ?? 0),
                    'ctr' => $latestStat ? $latestStat->ctr : 0,
                ];
            })
            ->sortByDesc('impressions')
            ->take(5)
            ->values();

        $totalImpressions = (int) ($adPerformance->total_impressions ?? 0);
        $totalClicks = (int) ($adPerformance->total_clicks ?? 0);

        return [
            'summary' => [
                'campaigns_created' => $campaignsCreated,
                'total_campaigns' => AdCampaign::whereNull('deleted_at')->count(),
                'total_budget' => round((float) $totalBudget, 2),
                'total_spend' => round((float) $totalSpend, 2),
                'total_impressions' => $totalImpressions,
                'total_clicks' => $totalClicks,
                'total_conversions' => (int) ($adPerformance->total_conversions ?? 0),
                'avg_ctr' => $totalImpressions > 0
                    ? round(($totalClicks / $totalImpressions) * 100, 2)
                    : 0,
            ],
            'by_status' => $byStatus,
            'by_platform' => $byPlatform,
            'top_campaigns' => $topCampaigns,
        ];
    }
}
