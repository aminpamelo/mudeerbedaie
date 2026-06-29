<?php

namespace App\Services\Ai;

use App\Ai\Agents\SalesPageWriter;
use Illuminate\Support\Str;

class LaravelAiSalesPageGenerator implements SalesPageGenerator
{
    public function generate(SalesPageBrief $brief): string
    {
        $response = (new SalesPageWriter($this->buildInstructions($brief)))->prompt(
            $this->buildUserMessage($brief),
            timeout: (int) config('ai_sales_pages.timeout', 180),
        );

        return $this->cleanHtml($response->text);
    }

    private function buildInstructions(SalesPageBrief $brief): string
    {
        $brand = array_merge([
            'primary' => '#2563EB',
            'secondary' => '#7C3AED',
            'accent' => '#EC4899',
            'font' => 'Plus Jakarta Sans',
        ], $brief->brand);

        $designDirection = filled($brief->designNotes)
            ? trim($brief->designNotes)
            : "Use the brand palette: primary {$brand['primary']}, secondary {$brand['secondary']}, accent {$brand['accent']}.";

        $typography = DesignSystemLibrary::typographyInstruction($brief->stylePreset);

        return <<<PROMPT
        You are an award-winning direct-response copywriter AND a senior product/visual designer. You write
        high-converting, beautifully designed sales / landing pages and output them as a single, complete,
        production-ready HTML document.

        STRICT OUTPUT RULES:
        - Return ONLY the raw HTML document. No markdown, no code fences, no commentary.
        - Start with <!DOCTYPE html> and include <html>, <head>, and <body>.
        - Style the page with Tailwind CSS via the Play CDN: <script src="https://cdn.tailwindcss.com"></script>
        - Fully responsive (mobile-first) and visually polished.

        TYPOGRAPHY SYSTEM (load the fonts in <head> and apply them throughout):
        {$typography}
        - CRITICAL: after loading the fonts you MUST actually apply them. Add an inline tailwind config that maps the fonts, e.g.
          <script>tailwind.config = { theme: { extend: { fontFamily: { heading: ['<HeadingFont>','serif'], body: ['<BodyFont>','sans-serif'] } } } }</script>
          set the body to the body font (class "font-body" or a base <style> on body), and put "font-heading" on every h1/h2/h3. Headings must VISIBLY render in the heading font, not the default sans-serif.

        DESIGN PRINCIPLES (apply like a senior product designer):
        - Strong hierarchy: an oversized hero headline using clamp() (e.g. clamp(2.5rem, 6vw, 4.75rem)), clear section H2/H3, body 16–18px, and small UPPERCASE labels with wide letter-spacing for eyebrows/tags.
        - Tighten letter-spacing slightly on large display headings (never on body). Line-height ~1.05–1.15 for big headings, ~1.6 for body.
        - Weight hierarchy: headings 600–800, body 400, labels 500. Use the heading font ONLY for headings, the body font for everything else.
        - Generous whitespace and a consistent 8px spacing rhythm. Constrain paragraphs to ~65–75 characters per line (max-w-prose / max-w-2xl) for readability.
        - One disciplined accent colour for primary CTAs; avoid rainbow palettes. Premium polish: soft shadows, rounded-2xl cards, refined gradients, hairline borders.
        - SVG icons only (never emoji as icons). Restrained, tasteful motion (150–300ms). Never sacrifice legibility for style.

        If the DESIGN DIRECTION below explicitly names fonts or colours, those OVERRIDE the typography system and palette above.

        DESIGN DIRECTION (authoritative for colours, mood and effects):
        {$designDirection}

        CONTRAST & LEGIBILITY (critical — never break these):
        - Every piece of text MUST have strong contrast against whatever is directly behind it (WCAG AA, 4.5:1 minimum).
        - NEVER place light text on a light background or dark text on a dark background.
        - This applies to EVERY element, including marquee/announcement bars, badges, buttons and nav: a light-coloured bar needs dark text; a dark bar needs light text.
        - For any text over an image or gradient, add a dark overlay/scrim behind it so the text stays clearly readable.
        - The hero headline must be instantly legible: if the headline is light, its background must be dark, and vice-versa.

        ANIMATION SAFETY (critical — content must NEVER be invisible):
        - All content MUST be fully visible by default. Do NOT initialise ANY element with opacity:0, visibility:hidden, or a hiding transform.
        - Do NOT gate visibility on scroll or JavaScript (no "reveal on scroll" that starts hidden). If JavaScript never runs, every section, button and heading must still be fully visible.
        - If you want entrance motion, use a pure CSS @keyframes animation that runs automatically on page load and ENDS at opacity:1 — never leave the hidden start state applied.

        PAGE STRUCTURE (adapt to the offer): sticky nav, hero with a strong headline + sub-headline + primary CTA,
        trust / USP bar, problem-agitate-solution, benefit-driven sections, testimonials, a clear pricing / offer block
        with any stated discount, FAQ, risk-reversal / guarantee, urgency, and a final CTA with footer.

        IMAGES:
        - Only use image URLs explicitly provided in the brief. Do NOT invent, hotlink, or use placeholder image services.
        - If no images are provided, use CSS gradients, SVG shapes and emoji-free icon markup instead of <img> tags.

        Write persuasive, specific, benefit-led copy. Avoid lorem ipsum. Write in the SAME LANGUAGE as the brief.
        PROMPT;
    }

    private function buildUserMessage(SalesPageBrief $brief): string
    {
        $lines = [];
        $lines[] = 'PAGE TITLE: '.$brief->title;
        $lines[] = '';
        $lines[] = 'BRIEF / OFFER:';
        $lines[] = $brief->prompt;

        if (filled($brief->targetAudience)) {
            $lines[] = '';
            $lines[] = 'TARGET AUDIENCE: '.$brief->targetAudience;
        }

        if (filled($brief->tone)) {
            $lines[] = 'TONE OF VOICE: '.$brief->tone;
        }

        if (! empty($brief->assets)) {
            $lines[] = '';
            $lines[] = 'AVAILABLE IMAGES (use these exact URLs as <img> sources where appropriate):';
            foreach ($brief->assets as $i => $asset) {
                $label = $asset['alt'] ?: ($asset['title'] ?: 'image '.($i + 1));
                $lines[] = sprintf('- %s  (description: %s)', $asset['url'], $label);
            }
        }

        if ($brief->isRefinement()) {
            $lines[] = '';
            $lines[] = 'EDIT THE EXISTING PAGE BELOW according to this instruction: '.$brief->refineInstruction;
            $lines[] = 'Scope rules:';
            $lines[] = '- For a small or specific tweak, keep the rest of the page intact and change only what is asked.';
            $lines[] = '- If the instruction asks for a fresh / new / different / redesigned look (e.g. "fresh design", "redesign", "buat lain", "tukar layout"), you MUST produce a visibly DIFFERENT design: restructure the hero, sections, cards, spacing and visual treatment so it clearly does not look like the current page. Keep the core offer and copy meaning, and stay within the brand colours and the fonts from the typography system.';
            $lines[] = 'Return the full updated HTML document.';
            $lines[] = '';
            $lines[] = 'CURRENT HTML:';
            $lines[] = $brief->currentHtml ?? '';
        } else {
            if (filled($brief->extraDirection)) {
                $lines[] = '';
                $lines[] = 'FRESH DESIGN REQUEST — the user wants a brand-new design, visibly different from any previous version: '.$brief->extraDirection;
                $lines[] = 'Invent a new layout, hero treatment, section arrangement and visual style from scratch. Do not reuse a previous structure. Stay within the brand colours and the typography system above.';
            }

            $lines[] = '';
            $lines[] = 'Generate the complete HTML sales page now.';
        }

        return implode("\n", $lines);
    }

    /**
     * Strip markdown code fences the model may wrap the HTML in, and trim to the document.
     */
    private function cleanHtml(string $html): string
    {
        $html = trim($html);

        if (Str::startsWith($html, '```')) {
            $html = preg_replace('/^```[a-zA-Z]*\s*/', '', $html);
            $html = preg_replace('/\s*```$/', '', (string) $html);
            $html = trim((string) $html);
        }

        if (preg_match('/<!DOCTYPE\s|<html[\s>]/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
            if ($start > 0) {
                $html = substr($html, $start);
            }
        }

        return trim($html);
    }
}
