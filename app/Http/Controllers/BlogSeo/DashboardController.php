<?php

namespace App\Http\Controllers\BlogSeo;

use App\Http\Controllers\Controller;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogSubscriber;
use App\Services\Seo\SeoHealthService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workspace overview: the "how is the blog doing" screen. Combines the SEO
 * health snapshot with publishing cadence, traffic and the moderation queue so
 * an editor can see everything needing attention without leaving the page.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly SeoHealthService $health) {}

    public function index(): Response
    {
        $report = $this->health->report();

        return Inertia::render('Overview', [
            'health' => [
                'score' => $report['score'],
                'grade' => $report['grade'],
                'distribution' => $report['distribution'],
            ],
            'summary' => $report['summary'],
            'topIssues' => array_slice($report['issues'], 0, 4),
            'topPosts' => $report['top_posts'],
            'weakestPosts' => $report['weakest_posts'],
            'publishing' => $this->publishingTrend(),
            'recentPosts' => $this->recentPosts(),
            'recentComments' => $this->recentComments(),
            'recentSubscribers' => $this->recentSubscribers(),
        ]);
    }

    /**
     * Posts published per month over the last six months, for the cadence chart.
     *
     * @return list<array{month: string, published: int, views: int}>
     */
    private function publishingTrend(): array
    {
        $start = now()->startOfMonth()->subMonths(5);

        $posts = BlogPost::query()
            ->published()
            ->where('published_at', '>=', $start)
            ->get(['published_at', 'view_count']);

        $months = [];

        for ($i = 0; $i < 6; $i++) {
            $cursor = $start->copy()->addMonths($i);
            $key = $cursor->format('Y-m');

            $bucket = $posts->filter(
                fn (BlogPost $p) => $p->published_at instanceof Carbon && $p->published_at->format('Y-m') === $key
            );

            $months[] = [
                'month' => $cursor->format('M'),
                'published' => $bucket->count(),
                'views' => (int) $bucket->sum('view_count'),
            ];
        }

        return $months;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentPosts(): array
    {
        return BlogPost::query()
            ->with(['category:id,name,color', 'author:id,name'])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (BlogPost $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'status' => $p->status,
                'locale' => $p->locale,
                'score' => (int) $p->seo_score,
                'views' => (int) $p->view_count,
                'category' => $p->category?->name,
                'categoryColor' => $p->category?->color,
                'author' => $p->author_name,
                'updatedAt' => $p->updated_at?->toIso8601String(),
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentComments(): array
    {
        return BlogComment::query()
            ->with(['post:id,title', 'user:id,name'])
            ->pending()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (BlogComment $c) => [
                'id' => $c->id,
                'author' => $c->display_name,
                'body' => Str::limit($c->body, 110),
                'post' => $c->post?->title,
                'createdAt' => $c->created_at?->toIso8601String(),
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentSubscribers(): array
    {
        return BlogSubscriber::query()
            ->active()
            ->with('post:id,title')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (BlogSubscriber $s) => [
                'id' => $s->id,
                'email' => $s->email,
                'name' => $s->name,
                'from' => $s->post?->title ?? ucfirst((string) $s->source),
                'createdAt' => $s->created_at?->toIso8601String(),
            ])->all();
    }
}
