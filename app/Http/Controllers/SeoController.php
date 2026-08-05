<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Services\Seo\SitemapGenerator;
use Illuminate\Http\Response;

/**
 * Serves the machine-readable SEO surface: sitemap.xml, robots.txt and the
 * blog's RSS feed. All generated from live data so publishing a post makes it
 * discoverable without anyone regenerating a file.
 */
class SeoController extends Controller
{
    public function __construct(private readonly SitemapGenerator $sitemap) {}

    public function sitemap(): Response
    {
        return response($this->sitemap->toXml(), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }

    public function robots(): Response
    {
        return response($this->sitemap->robotsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    /**
     * RSS 2.0 feed of the most recent articles.
     */
    public function feed(): Response
    {
        $posts = BlogPost::query()
            ->published()
            ->where('noindex', false)
            ->with(['author:id,name', 'category:id,name'])
            ->latest('published_at')
            ->limit(30)
            ->get();

        // The XML prolog is prepended here rather than written into the Blade
        // template: this environment runs with short_open_tag enabled, so a
        // literal `<?xml` in a view is parsed as a PHP open tag.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".view('blog.feed', [
            'posts' => $posts,
            'updatedAt' => $posts->first()?->published_at ?? now(),
        ])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
        ]);
    }
}
