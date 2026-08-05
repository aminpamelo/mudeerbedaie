<?php

namespace App\Services\Blog;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Renders the Markdown authored in the admin editor into the HTML cached on
 * `blog_posts.content_html`, then post-processes it for SEO and performance:
 * stable heading IDs (so the article table-of-contents can deep-link and
 * scroll-spy), lazy-loaded images, and safe external links.
 *
 * Raw HTML is permitted because only role-gated admins author posts — the same
 * trust level the existing AI sales-page builder operates at. Unsafe link
 * protocols (javascript:, data:) are still blocked by the converter.
 */
class MarkdownService
{
    private ?MarkdownConverter $converter = null;

    /**
     * Convert Markdown source to the rendered, post-processed HTML.
     */
    public function toHtml(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        $html = (string) $this->converter()->convert($markdown);

        return $this->postProcess($html);
    }

    /**
     * Extract the h2/h3 outline from rendered HTML for the article sidebar.
     *
     * @return list<array{level: int, id: string, text: string}>
     */
    public function tableOfContents(?string $html): array
    {
        if (blank($html)) {
            return [];
        }

        $document = $this->loadDocument($html);

        if (! $document instanceof DOMDocument) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $headings = $xpath->query('//h2|//h3');
        $items = [];

        if ($headings === false) {
            return [];
        }

        foreach ($headings as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }

            $text = trim($heading->textContent);

            if ($text === '') {
                continue;
            }

            $items[] = [
                'level' => (int) substr($heading->nodeName, 1),
                'id' => $heading->getAttribute('id'),
                'text' => $text,
            ];
        }

        return $items;
    }

    /**
     * Plain-text version of the body, used for word counts and SEO analysis.
     */
    public function toPlainText(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $text = preg_replace('/\s+/u', ' ', strip_tags((string) $html));

        return trim((string) $text);
    }

    private function converter(): MarkdownConverter
    {
        if ($this->converter instanceof MarkdownConverter) {
            return $this->converter;
        }

        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new StrikethroughExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new TaskListExtension);

        return $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Add heading anchors, lazy-load images and harden outbound links.
     */
    private function postProcess(string $html): string
    {
        $document = $this->loadDocument($html);

        if (! $document instanceof DOMDocument) {
            return $html;
        }

        $xpath = new DOMXPath($document);

        $this->addHeadingIds($xpath);
        $this->optimiseImages($xpath);
        $this->hardenLinks($xpath);

        return $this->extractBodyHtml($document);
    }

    private function addHeadingIds(DOMXPath $xpath): void
    {
        $headings = $xpath->query('//h2|//h3|//h4');

        if ($headings === false) {
            return;
        }

        $used = [];

        foreach ($headings as $heading) {
            if (! $heading instanceof DOMElement || $heading->getAttribute('id') !== '') {
                continue;
            }

            $base = Str::slug($heading->textContent) ?: 'section';
            $id = $base;
            $suffix = 2;

            // Two headings can legitimately share a title; IDs must stay unique
            // or the TOC links and scroll-spy target the wrong section.
            while (isset($used[$id])) {
                $id = "{$base}-{$suffix}";
                $suffix++;
            }

            $used[$id] = true;
            $heading->setAttribute('id', $id);
        }
    }

    private function optimiseImages(DOMXPath $xpath): void
    {
        $images = $xpath->query('//img');

        if ($images === false) {
            return;
        }

        foreach ($images as $image) {
            if (! $image instanceof DOMElement) {
                continue;
            }

            if ($image->getAttribute('loading') === '') {
                $image->setAttribute('loading', 'lazy');
            }

            if ($image->getAttribute('decoding') === '') {
                $image->setAttribute('decoding', 'async');
            }
        }
    }

    private function hardenLinks(DOMXPath $xpath): void
    {
        $links = $xpath->query('//a[@href]');

        if ($links === false) {
            return;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        foreach ($links as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = $link->getAttribute('href');
            $linkHost = parse_url($href, PHP_URL_HOST);

            // Only outbound links get target/rel — internal links keep their
            // link equity and stay in the same tab.
            if ($linkHost !== null && $linkHost !== $host) {
                $link->setAttribute('target', '_blank');
                $link->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private function loadDocument(string $html): ?DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // The meta charset keeps DOMDocument from mangling UTF-8 (Malay
        // diacritics, curly quotes) into HTML entities.
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="cm-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    private function extractBodyHtml(DOMDocument $document): string
    {
        $root = $document->getElementById('cm-root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }
}
