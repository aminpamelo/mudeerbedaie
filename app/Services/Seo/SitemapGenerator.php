<?php

namespace App\Services\Seo;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Product;
use App\Models\ProductCategory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds sitemap.xml from live data. Generated on request (and cached briefly)
 * rather than written to disk, so a freshly published post is discoverable
 * immediately without anyone remembering to regenerate anything.
 */
class SitemapGenerator
{
    /** Serving a stale sitemap for a minute is fine; rebuilding it per hit is not. */
    private const CACHE_SECONDS = 300;

    /**
     * @return list<array{loc: string, lastmod: ?CarbonInterface, changefreq: string, priority: string}>
     */
    public function entries(): array
    {
        $entries = [];

        // ---- static storefront pages ----
        $entries[] = $this->entry(route('storefront.home'), null, 'daily', '1.0');
        $entries[] = $this->entry(route('shop'), null, 'daily', '0.9');
        $entries[] = $this->entry(route('blog.index'), $this->latestPostDate(), 'daily', '0.9');

        // ---- blog posts ----
        BlogPost::query()
            ->published()
            ->where('noindex', false)
            ->orderByDesc('published_at')
            ->chunk(500, function (Collection $posts) use (&$entries): void {
                foreach ($posts as $post) {
                    $entries[] = $this->entry(
                        route('blog.show', $post->slug),
                        $post->updated_at ?? $post->published_at,
                        'weekly',
                        $post->is_featured ? '0.8' : '0.7',
                    );
                }
            });

        // ---- blog taxonomy ----
        foreach (BlogCategory::query()->active()->get() as $category) {
            $entries[] = $this->entry(route('blog.category', $category->slug), $category->updated_at, 'weekly', '0.6');
        }

        // Only tags that actually have a published post behind them — empty
        // taxonomy pages are crawl-budget waste and can read as thin content.
        BlogTag::query()
            ->whereHas('posts', fn ($q) => $q->published())
            ->chunk(500, function (Collection $tags) use (&$entries): void {
                foreach ($tags as $tag) {
                    $entries[] = $this->entry(route('blog.tag', $tag->slug), $tag->updated_at, 'weekly', '0.4');
                }
            });

        // ---- storefront products ----
        Product::query()
            ->where('status', 'active')
            ->where('type', 'simple')
            ->chunk(500, function (Collection $products) use (&$entries): void {
                foreach ($products as $product) {
                    $entries[] = $this->entry(
                        route('storefront.product', $product->slug),
                        $product->updated_at,
                        'weekly',
                        '0.8',
                    );
                }
            });

        foreach (ProductCategory::query()->where('is_active', true)->get() as $category) {
            $entries[] = $this->entry(route('shop').'?category='.$category->slug, $category->updated_at, 'weekly', '0.6');
        }

        return $entries;
    }

    public function toXml(): string
    {
        return cache()->remember('seo.sitemap.xml', self::CACHE_SECONDS, function (): string {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            foreach ($this->entries() as $entry) {
                $xml .= "  <url>\n";
                $xml .= '    <loc>'.e($entry['loc'])."</loc>\n";

                if ($entry['lastmod'] instanceof CarbonInterface) {
                    $xml .= '    <lastmod>'.$entry['lastmod']->toAtomString()."</lastmod>\n";
                }

                $xml .= '    <changefreq>'.$entry['changefreq']."</changefreq>\n";
                $xml .= '    <priority>'.$entry['priority']."</priority>\n";
                $xml .= "  </url>\n";
            }

            return $xml.'</urlset>';
        });
    }

    /**
     * robots.txt — keeps crawlers out of authenticated and transactional areas
     * while pointing them straight at the sitemap.
     */
    public function robotsTxt(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            '# Authenticated and transactional areas — nothing to index here.',
            'Disallow: /admin',
            'Disallow: /hr',
            'Disallow: /livehost',
            'Disallow: /live-host',
            'Disallow: /ceo',
            'Disallow: /fighter',
            'Disallow: /teacher',
            'Disallow: /student',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /settings',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ];

        return implode("\n", $lines);
    }

    public function flushCache(): void
    {
        cache()->forget('seo.sitemap.xml');
    }

    public function count(): int
    {
        return count($this->entries());
    }

    private function latestPostDate(): ?CarbonInterface
    {
        return BlogPost::query()->published()->max('published_at')
            ? BlogPost::query()->published()->latest('published_at')->first()?->published_at
            : null;
    }

    /**
     * @return array{loc: string, lastmod: ?CarbonInterface, changefreq: string, priority: string}
     */
    private function entry(string $loc, ?CarbonInterface $lastmod, string $changefreq, string $priority): array
    {
        return compact('loc', 'lastmod', 'changefreq', 'priority');
    }
}
