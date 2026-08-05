<?php

namespace App\Http\Controllers\BlogSeo;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\Seo\SeoAnalyzer;
use App\Services\Seo\SeoHealthService;
use App\Services\Seo\SitemapGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class SeoController extends Controller
{
    public function __construct(
        private readonly SeoHealthService $health,
        private readonly SeoAnalyzer $analyzer,
        private readonly SitemapGenerator $sitemap,
    ) {}

    public function index(): Response
    {
        $report = $this->health->report();

        return Inertia::render('SeoHealth', [
            'report' => $report,
            'technical' => [
                'sitemapUrl' => route('sitemap'),
                'sitemapCount' => $this->sitemap->count(),
                'robotsUrl' => url('robots.txt'),
                'robotsExists' => file_exists(public_path('robots.txt')),
                'feedUrl' => route('blog.feed'),
            ],
            'generatedAt' => $report['generated_at']->toIso8601String(),
        ]);
    }

    /**
     * Re-run the on-page audit for every post. Cheap at this scale — it is
     * string analysis over already-rendered HTML, no network calls.
     */
    public function reanalyseAll(): RedirectResponse
    {
        $count = 0;

        BlogPost::query()->chunkById(50, function ($posts) use (&$count): void {
            foreach ($posts as $post) {
                $this->analyzer->analyseAndStore($post);
                $count++;
            }
        });

        return back()->with('success', "Re-scored {$count} post(s).");
    }

    public function regenerateSitemap(): RedirectResponse
    {
        $this->sitemap->flushCache();

        return back()->with('success', 'Sitemap cache cleared — it rebuilds on the next request.');
    }

    public function regenerateRobots(): RedirectResponse
    {
        Artisan::call('seo:robots');

        return back()->with('success', 'robots.txt regenerated.');
    }
}
