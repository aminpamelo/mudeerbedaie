<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Services\Blog\MarkdownService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * On-page SEO audit for a blog post.
 *
 * Produces a weighted 0-100 score plus a grouped, human-readable checklist that
 * the editor sidebar and the SEO dashboard both render. Every check states what
 * is wrong AND how to fix it — a bare score nobody can act on is worthless.
 *
 * Readability deliberately uses a language-neutral proxy (sentence and paragraph
 * length) rather than Flesch, which is calibrated for English and would
 * mis-score the Malay half of the catalogue.
 */
class SeoAnalyzer
{
    public const STATUS_PASS = 'pass';

    public const STATUS_WARN = 'warn';

    public const STATUS_FAIL = 'fail';

    /** Title tag: Google truncates around 60 characters. */
    private const TITLE_MIN = 30;

    private const TITLE_MAX = 60;

    /** Meta description: the SERP snippet clips near 160 characters. */
    private const DESC_MIN = 120;

    private const DESC_MAX = 158;

    private const CONTENT_MIN_WORDS = 300;

    private const CONTENT_GOOD_WORDS = 600;

    public function __construct(private readonly MarkdownService $markdown) {}

    /**
     * Analyse a post and return the full report.
     *
     * @return array{
     *     score: int,
     *     grade: string,
     *     passed: int,
     *     warnings: int,
     *     failed: int,
     *     word_count: int,
     *     groups: array<string, list<array{id: string, label: string, status: string, message: string, weight: int}>>
     * }
     */
    public function analyse(BlogPost $post): array
    {
        $html = (string) $post->content_html;
        $plain = $this->markdown->toPlainText($html);
        $words = $this->wordCount($plain);
        $keyword = trim((string) $post->focus_keyword);

        $groups = [
            'basics' => $this->basicsChecks($post),
            'keyword' => $this->keywordChecks($post, $keyword, $plain, $html, $words),
            'content' => $this->contentChecks($post, $html, $plain, $words),
            'technical' => $this->technicalChecks($post, $html),
        ];

        $all = array_merge(...array_values($groups));

        $earned = 0;
        $total = 0;
        $passed = $warnings = $failed = 0;

        foreach ($all as $check) {
            $total += $check['weight'];

            match ($check['status']) {
                self::STATUS_PASS => [$earned += $check['weight'], $passed++],
                // A warning is a partial credit: the page still ranks, just not optimally.
                self::STATUS_WARN => [$earned += (int) round($check['weight'] / 2), $warnings++],
                default => $failed++,
            };
        }

        $score = $total > 0 ? (int) round($earned / $total * 100) : 0;

        return [
            'score' => $score,
            'grade' => $this->grade($score),
            'passed' => $passed,
            'warnings' => $warnings,
            'failed' => $failed,
            'word_count' => $words,
            'groups' => $groups,
        ];
    }

    /**
     * Analyse and persist the result onto the post.
     *
     * @return array<string, mixed>
     */
    public function analyseAndStore(BlogPost $post): array
    {
        $report = $this->analyse($post);

        $post->forceFill([
            'seo_score' => $report['score'],
            'seo_report' => $report,
            'seo_checked_at' => now(),
        ])->saveQuietly();

        return $report;
    }

    public function grade(int $score): string
    {
        return match (true) {
            $score >= 85 => 'excellent',
            $score >= 70 => 'good',
            $score >= 50 => 'fair',
            default => 'poor',
        };
    }

    // ------------------------------------------------------------ check groups

    /**
     * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
     */
    private function basicsChecks(BlogPost $post): array
    {
        $title = $post->seo_title;
        $titleLength = Str::length($title);

        $description = trim((string) $post->meta_description);
        $descLength = Str::length($description);

        return [
            $this->check(
                'title_length',
                __('seo.check_title_length'),
                match (true) {
                    $titleLength === 0 => self::STATUS_FAIL,
                    $titleLength < self::TITLE_MIN, $titleLength > self::TITLE_MAX => self::STATUS_WARN,
                    default => self::STATUS_PASS,
                },
                match (true) {
                    $titleLength === 0 => __('seo.msg_title_empty'),
                    $titleLength < self::TITLE_MIN => __('seo.msg_title_short', ['count' => $titleLength, 'min' => self::TITLE_MIN]),
                    $titleLength > self::TITLE_MAX => __('seo.msg_title_long', ['count' => $titleLength, 'max' => self::TITLE_MAX]),
                    default => __('seo.msg_title_ok', ['count' => $titleLength]),
                },
                weight: 10,
            ),
            $this->check(
                'meta_description',
                __('seo.check_meta_description'),
                match (true) {
                    $descLength === 0 => self::STATUS_FAIL,
                    $descLength < self::DESC_MIN, $descLength > self::DESC_MAX => self::STATUS_WARN,
                    default => self::STATUS_PASS,
                },
                match (true) {
                    $descLength === 0 => __('seo.msg_desc_empty'),
                    $descLength < self::DESC_MIN => __('seo.msg_desc_short', ['count' => $descLength, 'min' => self::DESC_MIN]),
                    $descLength > self::DESC_MAX => __('seo.msg_desc_long', ['count' => $descLength, 'max' => self::DESC_MAX]),
                    default => __('seo.msg_desc_ok', ['count' => $descLength]),
                },
                weight: 10,
            ),
            $this->check(
                'excerpt',
                __('seo.check_excerpt'),
                filled($post->excerpt) ? self::STATUS_PASS : self::STATUS_WARN,
                filled($post->excerpt) ? __('seo.msg_excerpt_ok') : __('seo.msg_excerpt_missing'),
                weight: 4,
            ),
            $this->check(
                'slug_quality',
                __('seo.check_slug'),
                match (true) {
                    blank($post->slug) => self::STATUS_FAIL,
                    Str::length((string) $post->slug) > 75 => self::STATUS_WARN,
                    default => self::STATUS_PASS,
                },
                match (true) {
                    blank($post->slug) => __('seo.msg_slug_empty'),
                    Str::length((string) $post->slug) > 75 => __('seo.msg_slug_long'),
                    default => __('seo.msg_slug_ok'),
                },
                weight: 5,
            ),
        ];
    }

    /**
     * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
     */
    private function keywordChecks(BlogPost $post, string $keyword, string $plain, string $html, int $words): array
    {
        if ($keyword === '') {
            return [
                $this->check(
                    'focus_keyword',
                    __('seo.check_focus_keyword'),
                    self::STATUS_FAIL,
                    __('seo.msg_keyword_missing'),
                    weight: 12,
                ),
            ];
        }

        $needle = Str::lower($keyword);
        $inTitle = Str::contains(Str::lower($post->seo_title), $needle);
        $inSlug = Str::contains(Str::lower((string) $post->slug), Str::slug($keyword));
        $inDescription = Str::contains(Str::lower((string) $post->meta_description), $needle);
        $firstParagraph = Str::lower($this->firstParagraph($html));
        $inFirst = $firstParagraph !== '' && Str::contains($firstParagraph, $needle);
        $inHeading = $this->keywordInHeading($html, $needle);
        $density = $this->keywordDensity($plain, $keyword, $words);

        return [
            $this->check(
                'focus_keyword',
                __('seo.check_focus_keyword'),
                self::STATUS_PASS,
                __('seo.msg_keyword_set', ['keyword' => $keyword]),
                weight: 4,
            ),
            $this->check(
                'keyword_in_title',
                __('seo.check_keyword_title'),
                $inTitle ? self::STATUS_PASS : self::STATUS_FAIL,
                $inTitle ? __('seo.msg_keyword_title_ok') : __('seo.msg_keyword_title_missing'),
                weight: 10,
            ),
            $this->check(
                'keyword_in_slug',
                __('seo.check_keyword_slug'),
                $inSlug ? self::STATUS_PASS : self::STATUS_WARN,
                $inSlug ? __('seo.msg_keyword_slug_ok') : __('seo.msg_keyword_slug_missing'),
                weight: 6,
            ),
            $this->check(
                'keyword_in_description',
                __('seo.check_keyword_description'),
                $inDescription ? self::STATUS_PASS : self::STATUS_WARN,
                $inDescription ? __('seo.msg_keyword_desc_ok') : __('seo.msg_keyword_desc_missing'),
                weight: 6,
            ),
            $this->check(
                'keyword_in_intro',
                __('seo.check_keyword_intro'),
                $inFirst ? self::STATUS_PASS : self::STATUS_WARN,
                $inFirst ? __('seo.msg_keyword_intro_ok') : __('seo.msg_keyword_intro_missing'),
                weight: 8,
            ),
            $this->check(
                'keyword_in_heading',
                __('seo.check_keyword_heading'),
                $inHeading ? self::STATUS_PASS : self::STATUS_WARN,
                $inHeading ? __('seo.msg_keyword_heading_ok') : __('seo.msg_keyword_heading_missing'),
                weight: 6,
            ),
            $this->check(
                'keyword_density',
                __('seo.check_keyword_density'),
                match (true) {
                    $words === 0 => self::STATUS_FAIL,
                    $density < 0.3 => self::STATUS_WARN,
                    $density > 3.0 => self::STATUS_FAIL,
                    default => self::STATUS_PASS,
                },
                match (true) {
                    $words === 0 => __('seo.msg_density_no_content'),
                    $density < 0.3 => __('seo.msg_density_low', ['density' => number_format($density, 2)]),
                    $density > 3.0 => __('seo.msg_density_high', ['density' => number_format($density, 2)]),
                    default => __('seo.msg_density_ok', ['density' => number_format($density, 2)]),
                },
                weight: 6,
            ),
        ];
    }

    /**
     * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
     */
    private function contentChecks(BlogPost $post, string $html, string $plain, int $words): array
    {
        $images = $this->countTags($html, '//img');
        $imagesMissingAlt = $this->countTags($html, '//img[not(@alt) or @alt=""]');
        $h2Count = $this->countTags($html, '//h2');
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $internal = $this->countLinks($html, internal: true, host: $appHost);
        $external = $this->countLinks($html, internal: false, host: $appHost);
        $readability = $this->readability($plain);

        return [
            $this->check(
                'content_length',
                __('seo.check_content_length'),
                match (true) {
                    $words < self::CONTENT_MIN_WORDS => self::STATUS_FAIL,
                    $words < self::CONTENT_GOOD_WORDS => self::STATUS_WARN,
                    default => self::STATUS_PASS,
                },
                match (true) {
                    $words < self::CONTENT_MIN_WORDS => __('seo.msg_content_thin', ['count' => $words, 'min' => self::CONTENT_MIN_WORDS]),
                    $words < self::CONTENT_GOOD_WORDS => __('seo.msg_content_ok_short', ['count' => $words, 'good' => self::CONTENT_GOOD_WORDS]),
                    default => __('seo.msg_content_ok', ['count' => $words]),
                },
                weight: 12,
            ),
            $this->check(
                'headings',
                __('seo.check_headings'),
                $h2Count >= 2 ? self::STATUS_PASS : ($h2Count === 1 ? self::STATUS_WARN : self::STATUS_FAIL),
                match (true) {
                    $h2Count === 0 => __('seo.msg_headings_none'),
                    $h2Count === 1 => __('seo.msg_headings_one'),
                    default => __('seo.msg_headings_ok', ['count' => $h2Count]),
                },
                weight: 8,
            ),
            $this->check(
                'featured_image',
                __('seo.check_featured_image'),
                $post->featured_image_id ? self::STATUS_PASS : self::STATUS_FAIL,
                $post->featured_image_id ? __('seo.msg_featured_ok') : __('seo.msg_featured_missing'),
                weight: 8,
            ),
            $this->check(
                'image_alt',
                __('seo.check_image_alt'),
                match (true) {
                    $images === 0 => self::STATUS_WARN,
                    $imagesMissingAlt > 0 => self::STATUS_FAIL,
                    default => self::STATUS_PASS,
                },
                match (true) {
                    $images === 0 => __('seo.msg_images_none'),
                    $imagesMissingAlt > 0 => __('seo.msg_images_no_alt', ['count' => $imagesMissingAlt]),
                    default => __('seo.msg_images_ok', ['count' => $images]),
                },
                weight: 8,
            ),
            $this->check(
                'internal_links',
                __('seo.check_internal_links'),
                $internal > 0 ? self::STATUS_PASS : self::STATUS_WARN,
                $internal > 0 ? __('seo.msg_internal_ok', ['count' => $internal]) : __('seo.msg_internal_missing'),
                weight: 7,
            ),
            $this->check(
                'external_links',
                __('seo.check_external_links'),
                $external > 0 ? self::STATUS_PASS : self::STATUS_WARN,
                $external > 0 ? __('seo.msg_external_ok', ['count' => $external]) : __('seo.msg_external_missing'),
                weight: 4,
            ),
            $this->check(
                'readability',
                __('seo.check_readability'),
                $readability['status'],
                $readability['message'],
                weight: 6,
            ),
        ];
    }

    /**
     * @return list<array{id: string, label: string, status: string, message: string, weight: int}>
     */
    private function technicalChecks(BlogPost $post, string $html): array
    {
        return [
            $this->check(
                'indexable',
                __('seo.check_indexable'),
                $post->noindex ? self::STATUS_FAIL : self::STATUS_PASS,
                $post->noindex ? __('seo.msg_noindex_on') : __('seo.msg_noindex_off'),
                weight: 8,
            ),
            $this->check(
                'social_image',
                __('seo.check_social_image'),
                ($post->og_image_id || $post->featured_image_id) ? self::STATUS_PASS : self::STATUS_WARN,
                ($post->og_image_id || $post->featured_image_id) ? __('seo.msg_social_ok') : __('seo.msg_social_missing'),
                weight: 5,
            ),
            $this->check(
                'category',
                __('seo.check_category'),
                $post->category_id ? self::STATUS_PASS : self::STATUS_WARN,
                $post->category_id ? __('seo.msg_category_ok') : __('seo.msg_category_missing'),
                weight: 4,
            ),
        ];
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @return array{id: string, label: string, status: string, message: string, weight: int}
     */
    private function check(string $id, string $label, string $status, string $message, int $weight): array
    {
        return compact('id', 'label', 'status', 'message', 'weight');
    }

    public function wordCount(string $plain): int
    {
        if (trim($plain) === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', trim($plain), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private function keywordDensity(string $plain, string $keyword, int $words): float
    {
        if ($words === 0 || $keyword === '') {
            return 0.0;
        }

        $occurrences = substr_count(Str::lower($plain), Str::lower($keyword));
        $keywordWords = max(1, $this->wordCount($keyword));

        return round($occurrences * $keywordWords / $words * 100, 2);
    }

    private function firstParagraph(string $html): string
    {
        $xpath = $this->xpath($html);

        if (! $xpath instanceof DOMXPath) {
            return '';
        }

        $nodes = $xpath->query('//p');

        if ($nodes === false) {
            return '';
        }

        foreach ($nodes as $node) {
            $text = trim($node->textContent);

            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function keywordInHeading(string $html, string $needle): bool
    {
        $xpath = $this->xpath($html);

        if (! $xpath instanceof DOMXPath) {
            return false;
        }

        $nodes = $xpath->query('//h2|//h3|//h4');

        if ($nodes === false) {
            return false;
        }

        foreach ($nodes as $node) {
            if (Str::contains(Str::lower($node->textContent), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function countTags(string $html, string $query): int
    {
        $xpath = $this->xpath($html);

        if (! $xpath instanceof DOMXPath) {
            return 0;
        }

        $nodes = $xpath->query($query);

        return $nodes === false ? 0 : $nodes->count();
    }

    private function countLinks(string $html, bool $internal, ?string $host): int
    {
        $xpath = $this->xpath($html);

        if (! $xpath instanceof DOMXPath) {
            return 0;
        }

        $nodes = $xpath->query('//a[@href]');

        if ($nodes === false) {
            return 0;
        }

        $count = 0;

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $href = $node->getAttribute('href');

            if (Str::startsWith($href, '#')) {
                continue;
            }

            $linkHost = parse_url($href, PHP_URL_HOST);
            $isInternal = $linkHost === null || $linkHost === $host;

            if ($isInternal === $internal) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Language-neutral readability proxy: long sentences are hard to read in
     * both English and Malay, whereas Flesch scoring is English-calibrated.
     *
     * @return array{status: string, message: string}
     */
    private function readability(string $plain): array
    {
        if (trim($plain) === '') {
            return ['status' => self::STATUS_FAIL, 'message' => __('seo.msg_readability_no_content')];
        }

        $sentences = preg_split('/[.!?]+\s/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sentenceCount = max(1, count($sentences));
        $average = round($this->wordCount($plain) / $sentenceCount, 1);

        return match (true) {
            $average > 25 => [
                'status' => self::STATUS_WARN,
                'message' => __('seo.msg_readability_long', ['avg' => $average]),
            ],
            $average < 6 => [
                'status' => self::STATUS_WARN,
                'message' => __('seo.msg_readability_short', ['avg' => $average]),
            ],
            default => [
                'status' => self::STATUS_PASS,
                'message' => __('seo.msg_readability_ok', ['avg' => $average]),
            ],
        };
    }

    private function xpath(string $html): ?DOMXPath
    {
        if (trim($html) === '') {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? new DOMXPath($document) : null;
    }
}
