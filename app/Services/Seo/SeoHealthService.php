<?php

namespace App\Services\Seo;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogSubscriber;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Site-wide SEO health: aggregates the per-post audit scores and sweeps the
 * whole indexable surface — blog posts AND storefront products — for the
 * issues that actually suppress rankings.
 *
 * Everything here is computed from the local database. No external API, no
 * credentials, so it works the moment the module is installed.
 */
class SeoHealthService
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_NOTICE = 'notice';

    /** How many affected records to list per issue before saying "and N more". */
    private const SAMPLE_LIMIT = 5;

    public function __construct(private readonly SeoAnalyzer $analyzer) {}

    /**
     * Build the full dashboard payload.
     *
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $posts = BlogPost::query()
            ->with('category:id,name')
            ->get([
                'id', 'title', 'slug', 'excerpt', 'status', 'locale', 'category_id',
                'meta_title', 'meta_description', 'focus_keyword', 'featured_image_id',
                'noindex', 'seo_score', 'seo_report', 'view_count', 'published_at',
                'reading_time', 'created_at',
            ]);

        $published = $posts->filter(fn (BlogPost $p) => $p->is_published);

        $products = Product::query()
            ->where('status', 'active')
            ->get(['id', 'name', 'slug', 'description', 'short_description']);

        $issues = $this->collectIssues($published, $products);

        return [
            'score' => $this->overallScore($published, $issues),
            'grade' => $this->analyzer->grade($this->overallScore($published, $issues)),
            'summary' => $this->summary($posts, $published, $products, $issues),
            'distribution' => $this->distribution($published),
            'issues' => $issues,
            'top_posts' => $this->topPosts($published),
            'weakest_posts' => $this->weakestPosts($published),
            'generated_at' => now(),
        ];
    }

    /**
     * Overall health = average post score, penalised by unresolved critical issues.
     *
     * @param  Collection<int, BlogPost>  $published
     * @param  list<array<string, mixed>>  $issues
     */
    private function overallScore(Collection $published, array $issues): int
    {
        if ($published->isEmpty()) {
            return 0;
        }

        $average = (float) $published->avg('seo_score');

        $criticalCount = collect($issues)
            ->where('severity', self::SEVERITY_CRITICAL)
            ->sum('count');

        // Each critical issue shaves 2 points, capped at 20 so a large site with
        // many small problems can still read as healthy overall.
        $penalty = min(20, $criticalCount * 2);

        return (int) max(0, min(100, round($average - $penalty)));
    }

    /**
     * @param  Collection<int, BlogPost>  $posts
     * @param  Collection<int, BlogPost>  $published
     * @param  Collection<int, Product>  $products
     * @param  list<array<string, mixed>>  $issues
     * @return array<string, int|float>
     */
    private function summary(Collection $posts, Collection $published, Collection $products, array $issues): array
    {
        return [
            'total_posts' => $posts->count(),
            'published_posts' => $published->count(),
            'draft_posts' => $posts->where('status', BlogPost::STATUS_DRAFT)->count(),
            'scheduled_posts' => $posts->where('status', BlogPost::STATUS_SCHEDULED)->count(),
            'avg_post_score' => $published->isEmpty() ? 0 : (int) round((float) $published->avg('seo_score')),
            'indexable_pages' => $published->where('noindex', false)->count() + $products->count(),
            'noindex_pages' => $published->where('noindex', true)->count(),
            'total_issues' => collect($issues)->sum('count'),
            'critical_issues' => collect($issues)->where('severity', self::SEVERITY_CRITICAL)->sum('count'),
            'total_views' => (int) $published->sum('view_count'),
            'pending_comments' => BlogComment::query()->pending()->count(),
            'subscribers' => BlogSubscriber::query()->active()->count(),
            'active_products' => $products->count(),
        ];
    }

    /**
     * Grade buckets, for the dashboard's score-distribution bars.
     *
     * @param  Collection<int, BlogPost>  $published
     * @return array<string, int>
     */
    private function distribution(Collection $published): array
    {
        $buckets = ['excellent' => 0, 'good' => 0, 'fair' => 0, 'poor' => 0];

        foreach ($published as $post) {
            $buckets[$this->analyzer->grade((int) $post->seo_score)]++;
        }

        return $buckets;
    }

    /**
     * Sweep posts and products for ranking-suppressing problems.
     *
     * @param  Collection<int, BlogPost>  $posts
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function collectIssues(Collection $posts, Collection $products): array
    {
        $issues = [];

        $add = function (string $key, string $label, string $severity, Collection $affected, string $fix, string $type = 'post') use (&$issues): void {
            if ($affected->isEmpty()) {
                return;
            }

            $issues[] = [
                'key' => $key,
                'label' => $label,
                'severity' => $severity,
                'type' => $type,
                'count' => $affected->count(),
                'fix' => $fix,
                'samples' => $affected->take(self::SAMPLE_LIMIT)->values()->all(),
            ];
        };

        // ---- blog posts ----
        $add(
            'missing_meta_description',
            __('seo.issue_missing_meta_description'),
            self::SEVERITY_CRITICAL,
            $this->mapPosts($posts->filter(fn (BlogPost $p) => blank($p->meta_description))),
            'Open the post and write a 120-158 character description in the SEO panel.',
        );

        $add(
            'missing_featured_image',
            __('seo.issue_missing_featured_image'),
            self::SEVERITY_CRITICAL,
            $this->mapPosts($posts->filter(fn (BlogPost $p) => blank($p->featured_image_id))),
            'Pick a featured image from the media library — it is also the social share preview.',
        );

        $add(
            'missing_focus_keyword',
            __('seo.issue_missing_focus_keyword'),
            self::SEVERITY_WARNING,
            $this->mapPosts($posts->filter(fn (BlogPost $p) => blank($p->focus_keyword))),
            'Set the phrase you want the article to rank for, then re-run the audit.',
        );

        $add(
            'thin_content',
            __('seo.issue_thin_content'),
            self::SEVERITY_WARNING,
            $this->mapPosts($posts->filter(function (BlogPost $p): bool {
                $words = data_get($p->seo_report, 'word_count');

                return $words !== null && $words < 300;
            })),
            'Expand the article past 300 words, ideally 600+.',
        );

        $add(
            'long_title',
            __('seo.issue_long_title'),
            self::SEVERITY_NOTICE,
            $this->mapPosts($posts->filter(fn (BlogPost $p) => Str::length($p->seo_title) > 60)),
            'Shorten the title (or set a shorter SEO title) so it is not truncated in results.',
        );

        $add(
            'noindex',
            __('seo.issue_noindex'),
            self::SEVERITY_NOTICE,
            $this->mapPosts($posts->where('noindex', true)),
            'If this was not deliberate, switch indexing back on in the SEO panel.',
        );

        $add(
            'duplicate_title',
            __('seo.issue_duplicate_title'),
            self::SEVERITY_WARNING,
            $this->mapPosts($this->duplicatesBy($posts, fn (BlogPost $p) => Str::lower(trim($p->seo_title)))),
            'Two posts competing on the same title split their own ranking. Differentiate them.',
        );

        $add(
            'duplicate_meta',
            __('seo.issue_duplicate_meta'),
            self::SEVERITY_NOTICE,
            $this->mapPosts($this->duplicatesBy(
                $posts->filter(fn (BlogPost $p) => filled($p->meta_description)),
                fn (BlogPost $p) => Str::lower(trim((string) $p->meta_description))
            )),
            'Give each post its own description so search results do not look copy-pasted.',
        );

        // ---- storefront products ----
        $add(
            'product_missing_description',
            __('seo.issue_missing_description'),
            self::SEVERITY_WARNING,
            $products
                ->filter(fn (Product $p) => blank($p->description) && blank($p->short_description))
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'title' => $p->name,
                    'url' => route('storefront.product', $p->slug),
                    'edit_url' => route('products.edit', $p->id),
                    'score' => null,
                ])
                ->values(),
            'Product pages with no description have nothing for Google to index. Add copy.',
            type: 'product',
        );

        // Critical first, then by how many records are affected.
        usort($issues, function (array $a, array $b): int {
            $order = [self::SEVERITY_CRITICAL => 0, self::SEVERITY_WARNING => 1, self::SEVERITY_NOTICE => 2];

            return [$order[$a['severity']], -$a['count']] <=> [$order[$b['severity']], -$b['count']];
        });

        return $issues;
    }

    /**
     * Rows whose key value appears more than once.
     *
     * @param  Collection<int, BlogPost>  $posts
     * @return Collection<int, BlogPost>
     */
    private function duplicatesBy(Collection $posts, callable $key): Collection
    {
        $counts = $posts->groupBy($key)->filter(fn (Collection $group) => $group->count() > 1);

        return $counts->flatten(1)->values();
    }

    /**
     * @param  Collection<int, BlogPost>  $posts
     * @return Collection<int, array<string, mixed>>
     */
    private function mapPosts(Collection $posts): Collection
    {
        return $posts->map(fn (BlogPost $post) => [
            'id' => $post->id,
            'title' => $post->title,
            'url' => route('blog.show', $post->slug),
            'edit_url' => route('blogseo.posts.edit', $post->id),
            'score' => (int) $post->seo_score,
        ])->values();
    }

    /**
     * @param  Collection<int, BlogPost>  $published
     * @return list<array<string, mixed>>
     */
    private function topPosts(Collection $published): array
    {
        return $published->sortByDesc('view_count')
            ->take(6)
            ->map(fn (BlogPost $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'views' => (int) $p->view_count,
                'score' => (int) $p->seo_score,
                'category' => $p->category?->name,
                'edit_url' => route('blogseo.posts.edit', $p->id),
            ])->values()->all();
    }

    /**
     * @param  Collection<int, BlogPost>  $published
     * @return list<array<string, mixed>>
     */
    private function weakestPosts(Collection $published): array
    {
        return $published->sortBy('seo_score')
            ->take(6)
            ->map(fn (BlogPost $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'score' => (int) $p->seo_score,
                'grade' => $this->analyzer->grade((int) $p->seo_score),
                'edit_url' => route('blogseo.posts.edit', $p->id),
            ])->values()->all();
    }
}
