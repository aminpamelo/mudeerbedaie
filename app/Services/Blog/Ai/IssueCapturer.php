<?php

declare(strict_types=1);

namespace App\Services\Blog\Ai;

use App\Models\BlogIssue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pulls current-affairs headlines from the configured Malay news RSS feeds
 * into blog_issues (deduplicated by URL), then has the AI curator score the
 * new ones for how well they suit an Islamic commentary article.
 */
class IssueCapturer
{
    /** Ignore feed items older than this. */
    private const MAX_AGE_DAYS = 3;

    public function __construct(private IssueCurator $curator) {}

    /**
     * @return array{captured: int, scored: int, failed_feeds: array<int, string>}
     */
    public function capture(): array
    {
        $new = collect();
        $failed = [];

        foreach ((array) config('blog.ai.feeds', []) as $source => $url) {
            try {
                $new = $new->merge($this->captureFeed((string) $source, (string) $url));
            } catch (\Throwable $e) {
                $failed[] = $source;
                Log::warning('Blog issue feed failed', ['source' => $source, 'error' => $e->getMessage()]);
            }
        }

        $scored = $new->isNotEmpty() ? $this->curator->score($new) : 0;

        $this->prune();

        return ['captured' => $new->count(), 'scored' => $scored, 'failed_feeds' => $failed];
    }

    /**
     * @return Collection<int, BlogIssue> newly created issues
     */
    private function captureFeed(string $source, string $url): Collection
    {
        $response = Http::timeout(20)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; BedaieBlogBot/1.0)'])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        // Some feeds (Astro Awani) send whitespace/BOM before the XML prolog.
        $body = ltrim($response->body(), "\xEF\xBB\xBF \t\n\r");
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false || ! isset($xml->channel->item)) {
            throw new \RuntimeException('Invalid RSS');
        }

        $created = collect();

        foreach ($xml->channel->item as $item) {
            $link = $this->cleanUrl((string) $item->link);
            $title = trim(html_entity_decode(strip_tags((string) $item->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($link === '' || $title === '') {
                continue;
            }

            $publishedAt = $this->parseDate((string) $item->pubDate);
            if ($publishedAt && $publishedAt->lt(now()->subDays(self::MAX_AGE_DAYS))) {
                continue;
            }

            $issue = BlogIssue::query()->firstOrCreate(
                ['url_hash' => BlogIssue::hashUrl($link)],
                [
                    'title' => Str::limit($title, 480, ''),
                    'summary' => Str::limit(trim(html_entity_decode(strip_tags((string) $item->description), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 1000) ?: null,
                    'source' => $source,
                    'url' => Str::limit($link, 990, ''),
                    'published_at' => $publishedAt,
                    'status' => BlogIssue::STATUS_NEW,
                ]
            );

            if ($issue->wasRecentlyCreated) {
                $created->push($issue);
            }
        }

        return $created;
    }

    /**
     * Strip tracking params so the same story isn't captured twice.
     */
    private function cleanUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (! $parts || empty($parts['host'])) {
            return '';
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query = array_filter($query, fn ($key) => ! str_starts_with((string) $key, 'utm_'), ARRAY_FILTER_USE_KEY);

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].($parts['path'] ?? '').($query ? '?'.http_build_query($query) : '');
    }

    private function parseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Drop old headlines that were never written up.
     */
    private function prune(): void
    {
        BlogIssue::query()
            ->whereIn('status', [BlogIssue::STATUS_NEW, BlogIssue::STATUS_DISMISSED])
            ->where('created_at', '<', now()->subDays((int) config('blog.ai.keep_days', 14)))
            ->delete();
    }
}
