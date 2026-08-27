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
        $this->embedVideos($document, $xpath);
        $this->optimiseImages($xpath);
        $this->hardenLinks($xpath);

        return $this->extractBodyHtml($document);
    }

    /**
     * Turn a paragraph that is just a YouTube/Vimeo link — the shape produced
     * when an author pastes a bare video URL on its own line — into a
     * responsive, lazy-loaded embed. Links inside prose (mixed with other text)
     * are left as ordinary hyperlinks.
     */
    private function embedVideos(DOMDocument $document, DOMXPath $xpath): void
    {
        $paragraphs = $xpath->query('//p');

        if ($paragraphs === false) {
            return;
        }

        $replacements = [];

        foreach ($paragraphs as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }

            $anchor = $this->soleAnchor($paragraph);

            if (! $anchor instanceof DOMElement) {
                continue;
            }

            $embedUrl = $this->videoEmbedUrl($anchor->getAttribute('href'));

            if ($embedUrl === null) {
                continue;
            }

            $replacements[] = [$paragraph, $this->buildVideoEmbed($document, $embedUrl)];
        }

        // Mutate after iterating so we never edit the live NodeList mid-loop.
        foreach ($replacements as [$paragraph, $embed]) {
            $paragraph->parentNode?->replaceChild($embed, $paragraph);
        }
    }

    /**
     * Return the anchor when a paragraph holds a single link and nothing else
     * but whitespace, otherwise null.
     */
    private function soleAnchor(DOMElement $paragraph): ?DOMElement
    {
        $anchor = null;

        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($anchor !== null || strtolower($child->nodeName) !== 'a') {
                    return null;
                }

                $anchor = $child;

                continue;
            }

            if (trim($child->textContent) !== '') {
                return null;
            }
        }

        return $anchor;
    }

    /**
     * Map a YouTube or Vimeo watch/share URL to its player embed URL.
     */
    private function videoEmbedUrl(string $url): ?string
    {
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $matches) === 1) {
            return 'https://www.youtube.com/embed/'.$matches[1];
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $url, $matches) === 1) {
            return 'https://player.vimeo.com/video/'.$matches[1];
        }

        return null;
    }

    private function buildVideoEmbed(DOMDocument $document, string $embedUrl): DOMElement
    {
        $wrapper = $document->createElement('div');
        $wrapper->setAttribute('class', 'blog-video');
        $wrapper->setAttribute('style', 'position:relative;width:100%;padding-bottom:56.25%;height:0;overflow:hidden;');

        $iframe = $document->createElement('iframe');
        $iframe->setAttribute('src', $embedUrl);
        $iframe->setAttribute('title', 'Video');
        $iframe->setAttribute('loading', 'lazy');
        $iframe->setAttribute('frameborder', '0');
        $iframe->setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
        $iframe->setAttribute('allowfullscreen', 'allowfullscreen');
        $iframe->setAttribute('style', 'position:absolute;top:0;left:0;width:100%;height:100%;border:0;');

        $wrapper->appendChild($iframe);

        return $wrapper;
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
