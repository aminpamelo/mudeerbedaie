<?php

namespace App\Services\Funnel;

use Illuminate\Support\HtmlString;

class PuckRenderer
{
    /**
     * Check if the Puck content is a single TextBlock containing a full HTML document.
     * When true, the page should be rendered as raw HTML instead of through the Puck template.
     */
    public function isFullPageHtml(array $content): bool
    {
        $components = $content['content'] ?? [];

        if (count($components) !== 1) {
            return false;
        }

        $component = $components[0];
        if (($component['type'] ?? '') !== 'TextBlock') {
            return false;
        }

        $textContent = trim($component['props']['content'] ?? '');

        return (bool) preg_match('/^<!DOCTYPE\s|^<html[\s>]/i', $textContent);
    }

    /**
     * Extract the raw full-page HTML from a single-TextBlock Puck content.
     * Injects funnel tracking config into the HTML before the closing </body> tag.
     */
    public function extractFullPageHtml(array $content, array $trackingScripts = []): string
    {
        $components = $content['content'] ?? [];
        $html = $components[0]['props']['content'] ?? '';

        // Inject tracking scripts before </body> if provided
        if (! empty($trackingScripts)) {
            $injection = implode("\n", $trackingScripts);
            $html = preg_replace('/<\/body>/i', $injection."\n</body>", $html, 1);
        }

        return $html;
    }

    /**
     * Render Puck JSON content to HTML.
     */
    public function render(array $content, array $context = []): HtmlString
    {
        $html = '';

        // Render root styles/settings
        $rootStyles = $this->getRootStyles($content['root'] ?? []);

        // Render each component
        foreach ($content['content'] ?? [] as $component) {
            $html .= $this->renderComponent($component, $context);
        }

        // Wrap in container with root styles
        $output = sprintf(
            '<div class="puck-page" style="%s">%s</div>',
            $rootStyles,
            $html
        );

        return new HtmlString($output);
    }

    /**
     * Get root styles from Puck root config.
     */
    protected function getRootStyles(array $root): string
    {
        $styles = [];

        if (! empty($root['props']['backgroundColor'])) {
            $styles[] = "background-color: {$root['props']['backgroundColor']}";
        }

        if (! empty($root['props']['padding'])) {
            $styles[] = "padding: {$root['props']['padding']}";
        }

        return implode('; ', $styles);
    }

    /**
     * Render a single Puck component.
     */
    protected function renderComponent(array $component, array $context = []): string
    {
        $type = $component['type'] ?? 'unknown';
        $props = $component['props'] ?? [];

        return match ($type) {
            'Container' => $this->renderContainer($props, $context),
            'Columns' => $this->renderColumns($props, $context),
            'Spacer' => $this->renderSpacer($props),
            'Divider' => $this->renderDivider($props),
            'HeroSection' => $this->renderHeroSection($props),
            'TextBlock' => $this->renderTextBlock($props),
            'ImageBlock' => $this->renderImageBlock($props),
            'VideoBlock' => $this->renderVideoBlock($props),
            'ButtonBlock' => $this->renderButtonBlock($props, $context),
            'TestimonialBlock' => $this->renderTestimonialBlock($props),
            'FeaturesGrid' => $this->renderFeaturesGrid($props),
            'PricingCard' => $this->renderPricingCard($props, $context),
            'CountdownTimer' => $this->renderCountdownTimer($props),
            'FaqAccordion' => $this->renderFaqAccordion($props),
            'OptinForm' => $this->renderOptinForm($props, $context),
            'ProductCard' => $this->renderProductCard($props, $context),
            'CheckoutForm' => $this->renderCheckoutForm($props, $context),
            'OrderBump' => $this->renderOrderBump($props, $context),
            default => $this->renderUnknown($type, $props),
        };
    }

    /**
     * Render Container component.
     */
    protected function renderContainer(array $props, array $context): string
    {
        $maxWidth = $props['maxWidth'] ?? '1200px';
        $padding = $props['padding'] ?? '20px';
        $backgroundColor = $props['backgroundColor'] ?? 'transparent';

        $innerHtml = '';
        $children = $props['children'] ?? [];
        if (is_array($children)) {
            foreach ($children as $child) {
                if (is_array($child)) {
                    $innerHtml .= $this->renderComponent($child, $context);
                }
            }
        }

        return sprintf(
            '<div class="puck-container" style="max-width: %s; margin: 0 auto; padding: %s; background-color: %s;">%s</div>',
            e($maxWidth),
            e($padding),
            e($backgroundColor),
            $innerHtml
        );
    }

    /**
     * Render Columns component.
     */
    protected function renderColumns(array $props, array $context): string
    {
        $columns = $props['columns'] ?? [];
        $gap = $props['gap'] ?? '20px';

        // Ensure columns is an array
        if (! is_array($columns)) {
            return '<!-- Invalid columns data -->';
        }

        $columnsHtml = '';
        foreach ($columns as $column) {
            // Skip invalid column entries
            if (! is_array($column)) {
                continue;
            }
            $width = $column['width'] ?? 'auto';
            $columnContent = '';
            $children = $column['children'] ?? [];
            if (is_array($children)) {
                foreach ($children as $child) {
                    if (is_array($child)) {
                        $columnContent .= $this->renderComponent($child, $context);
                    }
                }
            }
            $columnsHtml .= sprintf(
                '<div class="puck-column" style="flex: %s;">%s</div>',
                $width === 'auto' ? '1' : "0 0 {$width}",
                $columnContent
            );
        }

        return sprintf(
            '<div class="puck-columns" style="display: flex; gap: %s; flex-wrap: wrap;">%s</div>',
            e($gap),
            $columnsHtml
        );
    }

    /**
     * Render Spacer component.
     */
    protected function renderSpacer(array $props): string
    {
        $height = $props['height'] ?? '40px';

        return sprintf('<div class="puck-spacer" style="height: %s;"></div>', e($height));
    }

    /**
     * Render Divider component.
     */
    protected function renderDivider(array $props): string
    {
        $color = $props['color'] ?? '#e5e7eb';
        $thickness = $props['thickness'] ?? '1px';
        $margin = $props['margin'] ?? '20px 0';

        return sprintf(
            '<hr class="puck-divider" style="border: none; border-top: %s solid %s; margin: %s;">',
            e($thickness),
            e($color),
            e($margin)
        );
    }

    /**
     * Render HeroSection component.
     */
    protected function renderHeroSection(array $props): string
    {
        $title = $props['title'] ?? '';
        $subtitle = $props['subtitle'] ?? '';
        $backgroundImage = $props['backgroundImage'] ?? '';
        $backgroundColor = $props['backgroundColor'] ?? '#1f2937';
        $textColor = $props['textColor'] ?? '#ffffff';
        $minHeight = $props['minHeight'] ?? '500px';
        $ctaText = $props['ctaText'] ?? '';
        $ctaUrl = $props['ctaUrl'] ?? '#';

        $bgStyle = $backgroundImage
            ? "background-image: url('{$backgroundImage}'); background-size: cover; background-position: center;"
            : "background-color: {$backgroundColor};";

        $ctaHtml = $ctaText ? sprintf(
            '<a href="%s" class="puck-hero-cta" style="display: inline-block; padding: 12px 32px; background: #3b82f6; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 20px;">%s</a>',
            e($ctaUrl),
            e($ctaText)
        ) : '';

        return sprintf(
            '<div class="puck-hero" style="min-height: %s; %s display: flex; align-items: center; justify-content: center; text-align: center; padding: 40px 20px;">
                <div class="puck-hero-content" style="color: %s; position: relative; z-index: 1;">
                    <h1 style="font-size: 3rem; font-weight: 700; margin-bottom: 16px;">%s</h1>
                    <p style="font-size: 1.25rem; opacity: 0.9;">%s</p>
                    %s
                </div>
            </div>',
            e($minHeight),
            $bgStyle,
            e($textColor),
            e($title),
            e($subtitle),
            $ctaHtml
        );
    }

    /**
     * Render TextBlock component.
     */
    protected function renderTextBlock(array $props): string
    {
        $content = $props['content'] ?? '';
        $align = $props['align'] ?? 'left';
        $fontSize = $props['fontSize'] ?? '16px';
        $color = $props['color'] ?? '#374151';

        // Detect and handle full HTML documents pasted into TextBlock
        $content = $this->sanitizeFullHtmlDocument($content);

        return sprintf(
            '<div class="puck-text" style="text-align: %s; font-size: %s; color: %s; line-height: 1.7;">%s</div>',
            e($align),
            e($fontSize),
            e($color),
            $content // Allow HTML in text blocks
        );
    }

    /**
     * Detect if content is a full HTML document and extract body content + scoped styles.
     */
    protected function sanitizeFullHtmlDocument(string $content): string
    {
        $trimmed = trim($content);

        // Check if this is a full HTML document
        if (! preg_match('/^<!DOCTYPE\s|^<html[\s>]/i', $trimmed)) {
            return $content;
        }

        $scopeId = 'puck-scoped-'.substr(md5($content), 0, 8);
        $styles = '';
        $bodyContent = '';

        // Extract <style> tags and scope their CSS rules
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/si', $trimmed, $styleMatches)) {
            foreach ($styleMatches[1] as $cssContent) {
                $styles .= $this->scopeCssRules($cssContent, ".{$scopeId}");
            }
        }

        // Extract body content
        if (preg_match('/<body[^>]*>(.*)<\/body>/si', $trimmed, $bodyMatch)) {
            $bodyContent = $bodyMatch[1];
        } else {
            $bodyContent = preg_replace('/<!DOCTYPE[^>]*>/i', '', $trimmed);
            $bodyContent = preg_replace('/<\/?html[^>]*>/i', '', $bodyContent);
            $bodyContent = preg_replace('/<head[^>]*>.*?<\/head>/si', '', $bodyContent);
        }

        // Remove <script> tags to prevent external JS frameworks from interfering with page rendering
        $bodyContent = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $bodyContent);

        $extracted = '';
        if ($styles !== '') {
            $extracted .= "<style>{$styles}</style>";
        }
        // Use transform to create a containing block so position:fixed elements
        // inside the scoped content don't escape and overlay the rest of the page.
        $extracted .= sprintf(
            '<div class="%s" style="transform: translateZ(0); overflow: hidden; position: relative;">%s</div>',
            $scopeId,
            $bodyContent
        );

        return $extracted;
    }

    /**
     * Scope CSS rules by prefixing all selectors with a container selector.
     */
    protected function scopeCssRules(string $css, string $scopeSelector): string
    {
        // Process CSS: prefix each rule's selector(s) with the scope selector.
        // This handles normal rules and preserves @media, @keyframes, @font-face blocks.
        $result = '';
        $length = strlen($css);
        $i = 0;

        while ($i < $length) {
            // Skip whitespace
            while ($i < $length && ctype_space($css[$i])) {
                $result .= $css[$i];
                $i++;
            }

            if ($i >= $length) {
                break;
            }

            // Handle @-rules
            if ($css[$i] === '@') {
                $atRule = '@';
                $i++;
                // Read the at-rule name
                while ($i < $length && $css[$i] !== '{' && $css[$i] !== ';') {
                    $atRule .= $css[$i];
                    $i++;
                }

                if ($i >= $length) {
                    $result .= $atRule;
                    break;
                }

                $atRuleName = strtolower(trim(explode(' ', trim(substr($atRule, 1)))[0] ?? ''));

                if ($css[$i] === ';') {
                    // At-rule without block (e.g., @import, @charset)
                    $result .= $atRule.';';
                    $i++;
                } elseif (in_array($atRuleName, ['keyframes', '-webkit-keyframes', 'font-face'])) {
                    // Preserve these blocks as-is (no scoping needed)
                    $result .= $atRule.'{';
                    $i++; // skip {
                    $braceCount = 1;
                    while ($i < $length && $braceCount > 0) {
                        if ($css[$i] === '{') {
                            $braceCount++;
                        } elseif ($css[$i] === '}') {
                            $braceCount--;
                        }
                        if ($braceCount > 0) {
                            $result .= $css[$i];
                        }
                        $i++;
                    }
                    $result .= '}';
                } elseif ($atRuleName === 'media') {
                    // Recurse into @media blocks to scope inner rules
                    $result .= $atRule.'{';
                    $i++; // skip {
                    $innerCss = '';
                    $braceCount = 1;
                    while ($i < $length && $braceCount > 0) {
                        if ($css[$i] === '{') {
                            $braceCount++;
                        } elseif ($css[$i] === '}') {
                            $braceCount--;
                        }
                        if ($braceCount > 0) {
                            $innerCss .= $css[$i];
                        }
                        $i++;
                    }
                    $result .= $this->scopeCssRules($innerCss, $scopeSelector);
                    $result .= '}';
                } else {
                    // Other at-rules with blocks - preserve as-is
                    $result .= $atRule.'{';
                    $i++; // skip {
                    $braceCount = 1;
                    while ($i < $length && $braceCount > 0) {
                        if ($css[$i] === '{') {
                            $braceCount++;
                        } elseif ($css[$i] === '}') {
                            $braceCount--;
                        }
                        if ($braceCount > 0) {
                            $result .= $css[$i];
                        }
                        $i++;
                    }
                    $result .= '}';
                }

                continue;
            }

            // Handle comments
            if ($i + 1 < $length && $css[$i] === '/' && $css[$i + 1] === '*') {
                $commentEnd = strpos($css, '*/', $i + 2);
                if ($commentEnd === false) {
                    $result .= substr($css, $i);
                    break;
                }
                $result .= substr($css, $i, $commentEnd + 2 - $i);
                $i = $commentEnd + 2;

                continue;
            }

            // Read selector(s) until {
            $selector = '';
            while ($i < $length && $css[$i] !== '{') {
                $selector .= $css[$i];
                $i++;
            }

            if ($i >= $length) {
                $result .= $selector;
                break;
            }

            $i++; // skip {

            // Read declaration block until matching }
            $declarations = '';
            $braceCount = 1;
            while ($i < $length && $braceCount > 0) {
                if ($css[$i] === '{') {
                    $braceCount++;
                } elseif ($css[$i] === '}') {
                    $braceCount--;
                }
                if ($braceCount > 0) {
                    $declarations .= $css[$i];
                }
                $i++;
            }

            // Scope the selector
            $selector = trim($selector);
            if ($selector !== '') {
                $scopedSelectors = [];
                foreach (explode(',', $selector) as $sel) {
                    $sel = trim($sel);
                    if ($sel === '') {
                        continue;
                    }
                    // For body/html selectors, replace with the scope container
                    if (preg_match('/^(html|body)$/i', $sel)) {
                        $scopedSelectors[] = $scopeSelector;
                    } elseif (preg_match('/^(html|body)\s+/i', $sel)) {
                        $scopedSelectors[] = $scopeSelector.' '.preg_replace('/^(html|body)\s+/i', '', $sel);
                    } else {
                        $scopedSelectors[] = $scopeSelector.' '.$sel;
                    }
                }
                $result .= implode(', ', $scopedSelectors).'{'.$declarations.'}';
            }
        }

        return $result;
    }

    /**
     * Render ImageBlock component.
     */
    protected function renderImageBlock(array $props): string
    {
        $src = $props['src'] ?? '';
        $alt = $props['alt'] ?? '';
        $width = $props['width'] ?? '100%';
        $borderRadius = $props['borderRadius'] ?? '8px';

        if (empty($src)) {
            return '';
        }

        return sprintf(
            '<div class="puck-image" style="text-align: center;">
                <img src="%s" alt="%s" style="max-width: %s; border-radius: %s; height: auto;">
            </div>',
            e($src),
            e($alt),
            e($width),
            e($borderRadius)
        );
    }

    /**
     * Render VideoBlock component.
     */
    protected function renderVideoBlock(array $props): string
    {
        $url = $props['url'] ?? '';
        $aspectRatio = $props['aspectRatio'] ?? '16/9';

        if (empty($url)) {
            return '';
        }

        // Convert YouTube/Vimeo URLs to embed
        $embedUrl = $this->getEmbedUrl($url);

        return sprintf(
            '<div class="puck-video" style="position: relative; padding-bottom: 56.25%%; height: 0; overflow: hidden; border-radius: 8px;">
                <iframe src="%s" style="position: absolute; top: 0; left: 0; width: 100%%; height: 100%%;" frameborder="0" allowfullscreen></iframe>
            </div>',
            e($embedUrl)
        );
    }

    /**
     * Convert video URL to embed URL.
     */
    protected function getEmbedUrl(string $url): string
    {
        // YouTube
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/', $url, $matches)) {
            return "https://www.youtube.com/embed/{$matches[1]}";
        }

        // Vimeo
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return "https://player.vimeo.com/video/{$matches[1]}";
        }

        return $url;
    }

    /**
     * Render ButtonBlock component.
     */
    protected function renderButtonBlock(array $props, array $context): string
    {
        $text = $props['text'] ?? 'Click Here';
        $url = $props['url'] ?? '#';
        $backgroundColor = $props['backgroundColor'] ?? '#3b82f6';
        $textColor = $props['textColor'] ?? '#ffffff';
        $size = $props['size'] ?? 'medium';
        $fullWidth = $props['fullWidth'] ?? false;
        $action = $props['action'] ?? 'link';

        $padding = match ($size) {
            'small' => '8px 16px',
            'large' => '16px 40px',
            default => '12px 24px',
        };

        $fontSize = match ($size) {
            'small' => '14px',
            'large' => '18px',
            default => '16px',
        };

        $widthStyle = $fullWidth ? 'width: 100%; display: block;' : 'display: inline-block;';

        // Handle different actions
        $actionAttr = match ($action) {
            'next_step' => 'data-funnel-action="next"',
            'add_to_cart' => 'data-funnel-action="add-to-cart"',
            'checkout' => 'data-funnel-action="checkout"',
            default => '',
        };

        // Add tracking data for thank you page button clicks
        $trackingAttr = '';
        if (! empty($context['session_uuid'])) {
            $trackingAttr = sprintf(
                'data-track-click="true" data-session-uuid="%s" data-step-id="%s"',
                e($context['session_uuid']),
                e($context['step_id'] ?? '')
            );
        }

        return sprintf(
            '<div class="puck-button-wrapper" style="text-align: center; margin: 10px 0;">
                <a href="%s" class="puck-button" %s %s style="%s padding: %s; background-color: %s; color: %s; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: %s; text-align: center; cursor: pointer; transition: all 0.2s;">%s</a>
            </div>',
            e($url),
            $actionAttr,
            $trackingAttr,
            $widthStyle,
            $padding,
            e($backgroundColor),
            e($textColor),
            $fontSize,
            e($text)
        );
    }

    /**
     * Render TestimonialBlock component.
     */
    protected function renderTestimonialBlock(array $props): string
    {
        $quote = $props['quote'] ?? '';
        $author = $props['author'] ?? '';
        $role = $props['role'] ?? '';
        $avatar = $props['avatar'] ?? '';
        $rating = $props['rating'] ?? 5;

        $starsHtml = str_repeat('★', $rating).str_repeat('☆', 5 - $rating);

        $avatarHtml = $avatar ? sprintf(
            '<img src="%s" alt="%s" style="width: 48px; height: 48px; border-radius: 50%%; object-fit: cover; margin-right: 12px;">',
            e($avatar),
            e($author)
        ) : '';

        return sprintf(
            '<div class="puck-testimonial" style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <div style="color: #fbbf24; font-size: 20px; margin-bottom: 12px;">%s</div>
                <p style="font-style: italic; color: #374151; margin-bottom: 16px; line-height: 1.6;">"%s"</p>
                <div style="display: flex; align-items: center;">
                    %s
                    <div>
                        <div style="font-weight: 600; color: #111827;">%s</div>
                        <div style="font-size: 14px; color: #6b7280;">%s</div>
                    </div>
                </div>
            </div>',
            $starsHtml,
            e($quote),
            $avatarHtml,
            e($author),
            e($role)
        );
    }

    /**
     * Render FeaturesGrid component.
     */
    protected function renderFeaturesGrid(array $props): string
    {
        $features = $props['features'] ?? [];
        $columns = $props['columns'] ?? 3;

        $featuresHtml = '';
        foreach ($features as $feature) {
            $featuresHtml .= sprintf(
                '<div class="puck-feature" style="text-align: center; padding: 20px;">
                    <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #3b82f6; font-size: 24px;">%s</div>
                    <h3 style="font-weight: 600; color: #111827; margin-bottom: 8px;">%s</h3>
                    <p style="color: #6b7280; font-size: 14px;">%s</p>
                </div>',
                $feature['icon'] ?? '✓',
                e($feature['title'] ?? ''),
                e($feature['description'] ?? '')
            );
        }

        return sprintf(
            '<div class="puck-features" style="display: grid; grid-template-columns: repeat(%d, 1fr); gap: 24px;">%s</div>',
            $columns,
            $featuresHtml
        );
    }

    /**
     * Render PricingCard component.
     */
    protected function renderPricingCard(array $props, array $context): string
    {
        $name = $props['name'] ?? 'Plan';
        $price = $props['price'] ?? '0';
        $currency = $props['currency'] ?? 'RM';
        $period = $props['period'] ?? '';
        $originalPrice = $props['originalPrice'] ?? null;
        $features = $props['features'] ?? [];
        $ctaText = $props['ctaText'] ?? 'Get Started';
        $ctaUrl = $props['ctaUrl'] ?? '#';
        $featured = $props['featured'] ?? false;

        $featuresHtml = '';
        foreach ($features as $feature) {
            $featuresHtml .= sprintf('<li style="padding: 8px 0; color: #374151;">✓ %s</li>', e($feature));
        }

        $originalPriceHtml = $originalPrice ? sprintf(
            '<span style="text-decoration: line-through; color: #9ca3af; font-size: 18px; margin-right: 8px;">%s%s</span>',
            e($currency),
            e($originalPrice)
        ) : '';

        $borderStyle = $featured ? 'border: 2px solid #3b82f6;' : 'border: 1px solid #e5e7eb;';

        return sprintf(
            '<div class="puck-pricing" style="background: white; border-radius: 16px; padding: 32px; text-align: center; %s">
                <h3 style="font-size: 20px; font-weight: 600; color: #111827; margin-bottom: 16px;">%s</h3>
                <div style="margin-bottom: 24px;">
                    %s
                    <span style="font-size: 48px; font-weight: 700; color: #111827;">%s%s</span>
                    <span style="color: #6b7280;">%s</span>
                </div>
                <ul style="list-style: none; padding: 0; margin-bottom: 24px; text-align: left;">%s</ul>
                <a href="%s" style="display: block; padding: 12px 24px; background: #3b82f6; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">%s</a>
            </div>',
            $borderStyle,
            e($name),
            $originalPriceHtml,
            e($currency),
            e($price),
            $period ? "/{$period}" : '',
            $featuresHtml,
            e($ctaUrl),
            e($ctaText)
        );
    }

    /**
     * Render CountdownTimer component.
     */
    protected function renderCountdownTimer(array $props): string
    {
        $endDate = $props['endDate'] ?? '';
        $backgroundColor = $props['backgroundColor'] ?? '#1f2937';
        $textColor = $props['textColor'] ?? '#ffffff';

        return sprintf(
            '<div class="puck-countdown" data-end-date="%s" style="display: flex; justify-content: center; gap: 16px; padding: 24px; background: %s; border-radius: 12px;">
                <div style="text-align: center;">
                    <div class="countdown-days" style="font-size: 36px; font-weight: 700; color: %s;">00</div>
                    <div style="font-size: 12px; color: %s; opacity: 0.8;">DAYS</div>
                </div>
                <div style="text-align: center;">
                    <div class="countdown-hours" style="font-size: 36px; font-weight: 700; color: %s;">00</div>
                    <div style="font-size: 12px; color: %s; opacity: 0.8;">HOURS</div>
                </div>
                <div style="text-align: center;">
                    <div class="countdown-minutes" style="font-size: 36px; font-weight: 700; color: %s;">00</div>
                    <div style="font-size: 12px; color: %s; opacity: 0.8;">MINS</div>
                </div>
                <div style="text-align: center;">
                    <div class="countdown-seconds" style="font-size: 36px; font-weight: 700; color: %s;">00</div>
                    <div style="font-size: 12px; color: %s; opacity: 0.8;">SECS</div>
                </div>
            </div>',
            e($endDate),
            e($backgroundColor),
            e($textColor),
            e($textColor),
            e($textColor),
            e($textColor),
            e($textColor),
            e($textColor),
            e($textColor),
            e($textColor)
        );
    }

    /**
     * Render FaqAccordion component.
     */
    protected function renderFaqAccordion(array $props): string
    {
        $items = $props['items'] ?? [];

        $itemsHtml = '';
        foreach ($items as $index => $item) {
            $itemsHtml .= sprintf(
                '<div class="puck-faq-item" style="border-bottom: 1px solid #e5e7eb;">
                    <button onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === \'none\' ? \'block\' : \'none\'" style="width: 100%%; display: flex; justify-content: space-between; align-items: center; padding: 16px 0; background: none; border: none; cursor: pointer; text-align: left;">
                        <span style="font-weight: 500; color: #111827;">%s</span>
                        <span style="color: #6b7280;">+</span>
                    </button>
                    <div style="display: none; padding-bottom: 16px; color: #6b7280; line-height: 1.6;">%s</div>
                </div>',
                e($item['question'] ?? ''),
                e($item['answer'] ?? '')
            );
        }

        return sprintf('<div class="puck-faq">%s</div>', $itemsHtml);
    }

    /**
     * Render OptinForm component.
     */
    protected function renderOptinForm(array $props, array $context): string
    {
        $headline = $props['headline'] ?? '';
        $description = $props['description'] ?? '';
        $buttonText = $props['buttonText'] ?? 'Subscribe';
        $buttonColor = $props['buttonColor'] ?? '#3b82f6';
        $showName = $props['showName'] ?? false;
        $showPhone = $props['showPhone'] ?? false;

        $funnelUuid = $context['funnel_uuid'] ?? '';
        $stepId = $context['step_id'] ?? '';

        $nameField = $showName ? '<input type="text" name="name" placeholder="Your Name" required style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; margin-bottom: 12px; font-size: 16px;">' : '';
        $phoneField = $showPhone ? '<input type="tel" name="phone" placeholder="Phone Number" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; margin-bottom: 12px; font-size: 16px;">' : '';

        return sprintf(
            '<div class="puck-optin" style="max-width: 400px; margin: 0 auto; text-align: center;">
                <h3 style="font-size: 24px; font-weight: 600; color: #111827; margin-bottom: 8px;">%s</h3>
                <p style="color: #6b7280; margin-bottom: 24px;">%s</p>
                <form class="funnel-optin-form" data-funnel="%s" data-step="%s" method="POST">
                    %s
                    <input type="email" name="email" placeholder="Your Email" required style="width: 100%%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; margin-bottom: 12px; font-size: 16px;">
                    %s
                    <button type="submit" style="width: 100%%; padding: 12px; background: %s; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 16px; cursor: pointer;">%s</button>
                </form>
            </div>',
            e($headline),
            e($description),
            e($funnelUuid),
            e($stepId),
            $nameField,
            $phoneField,
            e($buttonColor),
            e($buttonText)
        );
    }

    /**
     * Render ProductCard component.
     */
    protected function renderProductCard(array $props, array $context): string
    {
        $name = $props['name'] ?? 'Product';
        $description = $props['description'] ?? '';
        $image = $props['image'] ?? '';
        $price = $props['price'] ?? '0';
        $currency = $props['currency'] ?? 'RM';
        $originalPrice = $props['originalPrice'] ?? null;
        $productId = $props['productId'] ?? null;

        $imageHtml = $image ? sprintf(
            '<img src="%s" alt="%s" style="width: 100%%; height: 200px; object-fit: cover; border-radius: 8px 8px 0 0;">',
            e($image),
            e($name)
        ) : '';

        $originalPriceHtml = $originalPrice ? sprintf(
            '<span style="text-decoration: line-through; color: #9ca3af; margin-right: 8px;">%s%s</span>',
            e($currency),
            e($originalPrice)
        ) : '';

        return sprintf(
            '<div class="puck-product" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                %s
                <div style="padding: 20px;">
                    <h3 style="font-weight: 600; color: #111827; margin-bottom: 8px;">%s</h3>
                    <p style="color: #6b7280; font-size: 14px; margin-bottom: 16px;">%s</p>
                    <div style="margin-bottom: 16px;">
                        %s
                        <span style="font-size: 24px; font-weight: 700; color: #111827;">%s%s</span>
                    </div>
                    <button data-product-id="%s" data-funnel-action="add-to-cart" style="width: 100%%; padding: 12px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Add to Cart</button>
                </div>
            </div>',
            $imageHtml,
            e($name),
            e($description),
            $originalPriceHtml,
            e($currency),
            e($price),
            e($productId)
        );
    }

    /**
     * Render CheckoutForm component.
     */
    protected function renderCheckoutForm(array $props, array $context): string
    {
        $headline = $props['headline'] ?? 'Lengkapkan Pesanan Anda';
        $showOrderSummary = $props['showOrderSummary'] ?? true;

        // Note: The actual Livewire checkout component is rendered by the view (funnel/show.blade.php)
        // for checkout step types. This placeholder just shows the headline and a marker div.
        return sprintf(
            '<div class="puck-checkout">
                <h2 style="font-size: 24px; font-weight: 600; color: #111827; margin-bottom: 24px; text-align: center;">%s</h2>
                <div id="funnel-checkout-form" data-funnel="%s" data-step="%s">
                    <!-- Checkout form will be rendered by Livewire component below -->
                </div>
            </div>',
            e($headline),
            e($context['funnel_uuid'] ?? ''),
            e($context['step_id'] ?? '')
        );
    }

    /**
     * Render OrderBump component.
     */
    protected function renderOrderBump(array $props, array $context): string
    {
        $headline = $props['headline'] ?? 'Wait! Special One-Time Offer';
        $description = $props['description'] ?? '';
        $price = $props['price'] ?? '0';
        $currency = $props['currency'] ?? 'RM';
        $originalPrice = $props['originalPrice'] ?? null;
        $bumpId = $props['bumpId'] ?? null;

        $originalPriceHtml = $originalPrice ? sprintf(
            '<span style="text-decoration: line-through; color: #9ca3af; margin-right: 8px;">%s%s</span>',
            e($currency),
            e($originalPrice)
        ) : '';

        return sprintf(
            '<div class="puck-order-bump" style="border: 2px dashed #fbbf24; border-radius: 12px; padding: 20px; background: #fffbeb; margin: 20px 0;">
                <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                    <input type="checkbox" name="order_bump_%s" value="%s" style="width: 20px; height: 20px; margin-top: 4px;">
                    <div>
                        <div style="font-weight: 600; color: #92400e; margin-bottom: 4px;">%s</div>
                        <p style="color: #78350f; font-size: 14px; margin-bottom: 8px;">%s</p>
                        <div>
                            %s
                            <span style="font-weight: 700; color: #92400e;">%s%s</span>
                        </div>
                    </div>
                </label>
            </div>',
            e($bumpId),
            e($bumpId),
            e($headline),
            e($description),
            $originalPriceHtml,
            e($currency),
            e($price)
        );
    }

    /**
     * Render unknown component type.
     */
    protected function renderUnknown(string $type, array $props): string
    {
        return sprintf(
            '<!-- Unknown component type: %s -->',
            e($type)
        );
    }
}
