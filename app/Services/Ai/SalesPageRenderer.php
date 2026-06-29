<?php

namespace App\Services\Ai;

use App\Models\AiSalesPage;
use App\Models\AiSalesPageVersion;

/**
 * Assembles the stored raw HTML into a complete, servable document: ensures a
 * full <html> wrapper, injects SEO/OG meta, and appends custom CSS/JS. Used for
 * both the admin live preview and the public published page.
 */
class SalesPageRenderer
{
    /**
     * Build the public document from a page's published version (falls back to the working draft).
     */
    public function published(AiSalesPage $page): string
    {
        $version = $page->publishedVersion;

        $html = $version?->html ?? $page->html ?? '';
        $css = $version?->custom_css ?? $page->custom_css;
        $js = $version?->custom_js ?? $page->custom_js;

        return $this->document($html, $css, $js, $this->metaFor($page));
    }

    /**
     * Build the preview document from the working draft on the page.
     */
    public function preview(AiSalesPage $page): string
    {
        return $this->document($page->html ?? '', $page->custom_css, $page->custom_js, $this->metaFor($page));
    }

    public function fromVersion(AiSalesPageVersion $version, AiSalesPage $page): string
    {
        return $this->document($version->html ?? '', $version->custom_css, $version->custom_js, $this->metaFor($page));
    }

    /**
     * @param  array{title?: string|null, description?: string|null, og_image?: string|null}  $meta
     */
    public function document(string $html, ?string $customCss, ?string $customJs, array $meta = []): string
    {
        $html = trim($html);

        if ($html === '') {
            $html = $this->placeholder();
        }

        $html = $this->ensureFullDocument($html, $meta['title'] ?? null);
        $html = $this->injectMeta($html, $meta);

        if (filled($customCss)) {
            $html = $this->injectBeforeHead($html, "\n<style>\n".$customCss."\n</style>\n");
        }

        if (filled($customJs)) {
            $html = $this->injectBeforeBody($html, "\n<script>\n".$customJs."\n</script>\n");
        }

        return $html;
    }

    /**
     * @return array{title: string|null, description: string|null, og_image: string|null}
     */
    private function metaFor(AiSalesPage $page): array
    {
        return [
            'title' => $page->meta_title ?: $page->title,
            'description' => $page->meta_description,
            'og_image' => $page->ogImage?->url,
        ];
    }

    private function ensureFullDocument(string $html, ?string $title): string
    {
        if (preg_match('/<html[\s>]/i', $html)) {
            return $html;
        }

        $safeTitle = e($title ?? 'Sales Page');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$safeTitle}</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body>
        {$html}
        </body>
        </html>
        HTML;
    }

    /**
     * @param  array{title?: string|null, description?: string|null, og_image?: string|null}  $meta
     */
    private function injectMeta(string $html, array $meta): string
    {
        $tags = [];

        $title = $meta['title'] ?? null;
        $description = $meta['description'] ?? null;
        $ogImage = $meta['og_image'] ?? null;

        if (filled($title)) {
            $tags[] = '<meta property="og:title" content="'.e($title).'">';
            // Only add a <title> if the document doesn't already declare one.
            if (! preg_match('/<title[\s>]/i', $html)) {
                $tags[] = '<title>'.e($title).'</title>';
            }
        }

        if (filled($description)) {
            $tags[] = '<meta name="description" content="'.e($description).'">';
            $tags[] = '<meta property="og:description" content="'.e($description).'">';
        }

        if (filled($ogImage)) {
            $tags[] = '<meta property="og:image" content="'.e($ogImage).'">';
        }

        if (filled($title) || filled($description) || filled($ogImage)) {
            $tags[] = '<meta property="og:type" content="website">';
        }

        if (empty($tags)) {
            return $html;
        }

        return $this->injectBeforeHead($html, "\n".implode("\n", $tags)."\n");
    }

    private function injectBeforeHead(string $html, string $fragment): string
    {
        if (preg_match('/<\/head>/i', $html)) {
            return preg_replace('/<\/head>/i', $fragment.'</head>', $html, 1);
        }

        if (preg_match('/<body[^>]*>/i', $html)) {
            return preg_replace('/(<body[^>]*>)/i', $fragment.'$1', $html, 1);
        }

        return $fragment.$html;
    }

    private function injectBeforeBody(string $html, string $fragment): string
    {
        if (preg_match('/<\/body>/i', $html)) {
            return preg_replace('/<\/body>/i', $fragment.'</body>', $html, 1);
        }

        return $html.$fragment;
    }

    private function placeholder(): string
    {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<script src="https://cdn.tailwindcss.com"></script></head>'
            .'<body class="min-h-screen flex items-center justify-center bg-zinc-950 text-zinc-400 font-sans">'
            .'<div class="text-center"><p class="text-lg">This sales page has no content yet.</p>'
            .'<p class="text-sm mt-2">Generate it with AI to get started.</p></div></body></html>';
    }
}
