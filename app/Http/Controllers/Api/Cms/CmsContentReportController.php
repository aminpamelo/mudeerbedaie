<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsContentPlatformPost;
use App\Models\Content;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CmsContentReportController extends Controller
{
    /**
     * Per-content performance report combining TikTok stats,
     * cross-platform posting progress, marking, and ad campaigns.
     */
    public function index(Request $request): JsonResponse
    {
        [$start, $end] = $this->resolvePeriod($request);

        $contents = $this->buildContentRows($start, $end);

        return response()->json([
            'data' => [
                'period' => [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                ],
                'kpis' => $this->kpis($contents, $start, $end),
                'funnel' => $this->funnel(),
                'by_platform' => $this->byPlatform(),
                'contents' => $contents->values(),
                'top_performers' => $this->topPerformers($contents),
            ],
        ]);
    }

    /**
     * Streamed CSV export of the per-content table.
     */
    public function export(Request $request): StreamedResponse
    {
        [$start, $end] = $this->resolvePeriod($request);
        $contents = $this->buildContentRows($start, $end);

        $filename = 'content-report-'.$start->toDateString().'-to-'.$end->toDateString().'.csv';

        return new StreamedResponse(function () use ($contents): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Title',
                'Stage',
                'Priority',
                'Created',
                'Posted',
                'Marked',
                'TikTok Views',
                'TikTok Likes',
                'TikTok Comments',
                'TikTok Shares',
                'Cross-Posts Done',
                'Cross-Posts Total',
                'Cross Views',
                'Cross Likes',
                'Cross Comments',
                'Total Views',
                'Total Engagement',
                'Has Ad Campaign',
            ]);

            foreach ($contents as $row) {
                fputcsv($handle, [
                    $row['id'],
                    $row['title'],
                    $row['stage'],
                    $row['priority'],
                    $row['created_at'],
                    $row['posted_at'],
                    $row['is_marked'] ? 'Yes' : 'No',
                    $row['tiktok']['views'],
                    $row['tiktok']['likes'],
                    $row['tiktok']['comments'],
                    $row['tiktok']['shares'],
                    $row['cross_post']['posted'],
                    $row['cross_post']['total'],
                    $row['cross_post']['views'],
                    $row['cross_post']['likes'],
                    $row['cross_post']['comments'],
                    $row['totals']['views'],
                    $row['totals']['engagement'],
                    $row['has_ad_campaign'] ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Resolve the date range from the request, defaulting to last 30 days.
     */
    private function resolvePeriod(Request $request): array
    {
        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->subDays(30)->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        return [$start, $end];
    }

    /**
     * Build the per-content rows with all aggregated metrics.
     */
    private function buildContentRows(Carbon $start, Carbon $end)
    {
        $contents = Content::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('created_at', [$start, $end])
                    ->orWhereBetween('posted_at', [$start, $end])
                    ->orWhereBetween('marked_at', [$start, $end]);
            })
            ->with([
                'stats' => function ($q): void {
                    $q->latest('fetched_at')->limit(1);
                },
                'platformPosts.platform:id,key,name',
                'adCampaigns:id,content_id,status',
            ])
            ->orderByDesc('created_at')
            ->get();

        return $contents->map(function (Content $c): array {
            $latest = $c->stats->first();
            $tiktok = [
                'views' => (int) ($latest->views ?? 0),
                'likes' => (int) ($latest->likes ?? 0),
                'comments' => (int) ($latest->comments ?? 0),
                'shares' => (int) ($latest->shares ?? 0),
            ];

            $crossPosts = $c->platformPosts;
            $crossTotal = $crossPosts->count();
            $crossPostedCount = $crossPosts->where('status', 'posted')->count();

            $crossStats = $crossPosts->reduce(function ($carry, $post) {
                $stats = $post->stats ?? [];
                $carry['views'] += (int) ($stats['views'] ?? 0);
                $carry['likes'] += (int) ($stats['likes'] ?? 0);
                $carry['comments'] += (int) ($stats['comments'] ?? 0);

                return $carry;
            }, ['views' => 0, 'likes' => 0, 'comments' => 0]);

            $totalViews = $tiktok['views'] + $crossStats['views'];
            $totalEngagement = $tiktok['likes'] + $tiktok['comments'] + $tiktok['shares']
                + $crossStats['likes'] + $crossStats['comments'];

            $engagementRate = $totalViews > 0
                ? round(($totalEngagement / $totalViews) * 100, 2)
                : 0;

            return [
                'id' => $c->id,
                'title' => $c->title,
                'stage' => $c->stage,
                'priority' => $c->priority,
                'created_at' => optional($c->created_at)->toDateString(),
                'posted_at' => optional($c->posted_at)->toDateString(),
                'marked_at' => optional($c->marked_at)->toDateString(),
                'is_marked' => (bool) $c->is_marked_for_ads,
                'tiktok' => $tiktok,
                'cross_post' => [
                    'posted' => $crossPostedCount,
                    'total' => $crossTotal,
                    'views' => $crossStats['views'],
                    'likes' => $crossStats['likes'],
                    'comments' => $crossStats['comments'],
                ],
                'totals' => [
                    'views' => $totalViews,
                    'engagement' => $totalEngagement,
                    'engagement_rate' => $engagementRate,
                ],
                'has_ad_campaign' => $c->adCampaigns->isNotEmpty(),
            ];
        });
    }

    /**
     * KPI numbers for the date range.
     */
    private function kpis($contents, Carbon $start, Carbon $end): array
    {
        $totalContent = $contents->count();
        $postedInPeriod = $contents->filter(fn ($c) => $c['posted_at'] && $c['posted_at'] >= $start->toDateString() && $c['posted_at'] <= $end->toDateString())->count();
        $markedInPeriod = $contents->filter(fn ($c) => $c['marked_at'] && $c['marked_at'] >= $start->toDateString() && $c['marked_at'] <= $end->toDateString())->count();

        $markedRate = $postedInPeriod > 0
            ? round(($markedInPeriod / $postedInPeriod) * 100, 1)
            : 0;

        $totalViews = $contents->sum(fn ($c) => $c['totals']['views']);
        $totalEngagement = $contents->sum(fn ($c) => $c['totals']['engagement']);

        return [
            'total_content' => $totalContent,
            'posted_in_period' => $postedInPeriod,
            'marked_in_period' => $markedInPeriod,
            'marked_rate' => $markedRate,
            'total_views' => $totalViews,
            'total_engagement' => $totalEngagement,
        ];
    }

    /**
     * Lifetime funnel: counts at each step of the lifecycle.
     */
    private function funnel(): array
    {
        $byStage = Content::query()
            ->whereNull('deleted_at')
            ->selectRaw('stage, count(*) as count')
            ->groupBy('stage')
            ->pluck('count', 'stage')
            ->toArray();

        $stages = ['idea', 'shooting', 'editing', 'posting', 'posted'];
        $funnel = [];
        foreach ($stages as $stage) {
            $funnel[] = [
                'key' => $stage,
                'label' => ucfirst($stage),
                'count' => (int) ($byStage[$stage] ?? 0),
            ];
        }

        $marked = Content::query()
            ->whereNull('deleted_at')
            ->where('is_marked_for_ads', true)
            ->count();

        $crossPosted = Content::query()
            ->whereNull('deleted_at')
            ->whereHas('platformPosts', fn ($q) => $q->where('status', 'posted'))
            ->count();

        $hasAds = Content::query()
            ->whereNull('deleted_at')
            ->whereHas('adCampaigns')
            ->count();

        $funnel[] = ['key' => 'marked', 'label' => 'Marked', 'count' => $marked];
        $funnel[] = ['key' => 'cross_posted', 'label' => 'Cross-Posted', 'count' => $crossPosted];
        $funnel[] = ['key' => 'has_ads', 'label' => 'Has Ads', 'count' => $hasAds];

        return $funnel;
    }

    /**
     * Aggregate engagement per platform: TikTok + each cross-platform.
     */
    private function byPlatform(): array
    {
        // TikTok aggregate (latest stat per content)
        $tiktok = DB::table('content_stats as cs')
            ->whereIn('cs.id', function ($query): void {
                $query->select(DB::raw('MAX(id)'))
                    ->from('content_stats')
                    ->groupBy('content_id');
            })
            ->selectRaw('SUM(views) as v, SUM(likes) as l, SUM(comments) as c')
            ->first();

        $platforms = [
            [
                'key' => 'tiktok',
                'name' => 'TikTok',
                'views' => (int) ($tiktok->v ?? 0),
                'likes' => (int) ($tiktok->l ?? 0),
                'comments' => (int) ($tiktok->c ?? 0),
            ],
        ];

        // Cross-platforms: aggregate stats JSON per cms_platform
        $crossRows = CmsContentPlatformPost::query()
            ->with('platform:id,key,name')
            ->whereNotNull('stats')
            ->get();

        $byPlatform = [];
        foreach ($crossRows as $post) {
            $platform = $post->platform;
            if (! $platform) {
                continue;
            }
            $key = $platform->key;
            if (! isset($byPlatform[$key])) {
                $byPlatform[$key] = [
                    'key' => $key,
                    'name' => $platform->name,
                    'views' => 0,
                    'likes' => 0,
                    'comments' => 0,
                ];
            }
            $stats = $post->stats ?? [];
            $byPlatform[$key]['views'] += (int) ($stats['views'] ?? 0);
            $byPlatform[$key]['likes'] += (int) ($stats['likes'] ?? 0);
            $byPlatform[$key]['comments'] += (int) ($stats['comments'] ?? 0);
        }

        foreach ($byPlatform as $row) {
            $platforms[] = $row;
        }

        return $platforms;
    }

    /**
     * Top performers across three lenses.
     */
    private function topPerformers($contents): array
    {
        $byTotalViews = $contents
            ->sortByDesc(fn ($c) => $c['totals']['views'])
            ->take(5)
            ->values()
            ->map(fn ($c) => [
                'id' => $c['id'],
                'title' => $c['title'],
                'metric' => $c['totals']['views'],
            ])
            ->toArray();

        $byCrossPlatform = $contents
            ->filter(fn ($c) => $c['cross_post']['views'] > 0)
            ->sortByDesc(fn ($c) => $c['cross_post']['views'])
            ->take(5)
            ->values()
            ->map(fn ($c) => [
                'id' => $c['id'],
                'title' => $c['title'],
                'metric' => $c['cross_post']['views'],
            ])
            ->toArray();

        $byEngagementRate = $contents
            ->filter(fn ($c) => $c['totals']['views'] >= 100) // avoid noise from tiny samples
            ->sortByDesc(fn ($c) => $c['totals']['engagement_rate'])
            ->take(5)
            ->values()
            ->map(fn ($c) => [
                'id' => $c['id'],
                'title' => $c['title'],
                'metric' => $c['totals']['engagement_rate'],
            ])
            ->toArray();

        return [
            'by_total_views' => $byTotalViews,
            'by_cross_platform' => $byCrossPlatform,
            'by_engagement_rate' => $byEngagementRate,
        ];
    }
}
