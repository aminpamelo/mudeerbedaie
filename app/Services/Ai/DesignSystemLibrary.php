<?php

namespace App\Services\Ai;

/**
 * Curated design intelligence baked into the AI sales-page engine: premium
 * Google-Font pairings + style guidance distilled from the UI/UX design system.
 * The generator injects either a chosen preset or — for "auto" — the whole menu
 * so the model picks an expert pairing instead of a random one.
 */
class DesignSystemLibrary
{
    /**
     * @return array<string, array{label: string, heading: string, body: string, google: string, vibe: string}>
     */
    public static function presets(): array
    {
        return [
            'luxe_serif' => [
                'label' => 'Luxe Serif — Cormorant + Montserrat',
                'heading' => 'Cormorant',
                'body' => 'Montserrat',
                'google' => 'https://fonts.googleapis.com/css2?family=Cormorant:wght@400;500;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap',
                'vibe' => 'Refined, feminine, high-end. Elegant Cormorant display serif headlines over clean Montserrat body. Best for premium books, beauty, fashion, spiritual & lifestyle brands.',
            ],
            'modern_serif' => [
                'label' => 'Modern Serif — Fraunces + Inter',
                'heading' => 'Fraunces',
                'body' => 'Inter',
                'google' => 'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;0,9..144,900;1,9..144,400&family=Inter:wght@300;400;500;600;700&display=swap',
                'vibe' => 'Warm, characterful modern serif (Fraunces) with a clean Inter body. Premium yet approachable. Best for editorial brands, wellness, modern luxury and books.',
            ],
            'editorial_serif' => [
                'label' => 'Editorial — Playfair Display + Source Serif 4',
                'heading' => 'Playfair Display',
                'body' => 'Source Serif 4',
                'google' => 'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,900;1,400&family=Source+Serif+4:ital,wght@0,300;0,400;0,600;1,300&display=swap',
                'vibe' => 'High-contrast all-serif editorial, book-like reading aesthetic. Best for publications, long-form, scholarly and premium editorial content.',
            ],
            'luxe_minimal' => [
                'label' => 'Luxe Minimal — Bodoni Moda + Jost',
                'heading' => 'Bodoni Moda',
                'body' => 'Jost',
                'google' => 'https://fonts.googleapis.com/css2?family=Bodoni+Moda:wght@400;500;600;700&family=Jost:wght@300;400;500;600;700&display=swap',
                'vibe' => 'Dramatic high-contrast luxury minimalist. Bodoni headlines with geometric Jost body. Best for high-end fashion and premium minimalist products.',
            ],
            'classic_elegant' => [
                'label' => 'Classic Elegant — Playfair Display + Inter',
                'heading' => 'Playfair Display',
                'body' => 'Inter',
                'google' => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;900&family=Inter:wght@300;400;500;600;700&display=swap',
                'vibe' => 'Timeless elegant pairing. Best for luxury, beauty, editorial and high-end e-commerce.',
            ],
            'modern_sans' => [
                'label' => 'Modern Tech — Space Grotesk + DM Sans',
                'heading' => 'Space Grotesk',
                'body' => 'DM Sans',
                'google' => 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap',
                'vibe' => 'Modern, geometric, tech/AI/startup. Best for software, apps, developer tools and AI products.',
            ],
            'friendly_clean' => [
                'label' => 'Friendly Clean — Plus Jakarta Sans',
                'heading' => 'Plus Jakarta Sans',
                'body' => 'Plus Jakarta Sans',
                'google' => 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
                'vibe' => 'Clean, friendly, professional single-family. Best for SaaS, B2B, dashboards and productivity tools.',
            ],
            'warm_friendly' => [
                'label' => 'Warm Friendly — Poppins + Open Sans',
                'heading' => 'Poppins',
                'body' => 'Open Sans',
                'google' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@300;400;500;600;700&display=swap',
                'vibe' => 'Warm, approachable and rounded. Best for education, community, wellness and family products.',
            ],
            'bold_impact' => [
                'label' => 'Bold Impact — Bebas Neue + Source Sans 3',
                'heading' => 'Bebas Neue',
                'body' => 'Source Sans 3',
                'google' => 'https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Source+Sans+3:wght@300;400;500;600;700;800&display=swap',
                'vibe' => 'Bold, condensed, high-impact marketing. Best for events, promos, sports and urgency-driven offers.',
            ],
        ];
    }

    /**
     * Options for a UI <select>: 'auto' + each preset label.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = ['auto' => 'Auto — let the AI designer choose (recommended)'];

        foreach (self::presets() as $key => $preset) {
            $options[$key] = $preset['label'];
        }

        return $options;
    }

    /**
     * The typography instruction injected into the generation prompt.
     */
    public static function typographyInstruction(?string $key): string
    {
        $presets = self::presets();

        if ($key !== null && $key !== 'auto' && isset($presets[$key])) {
            $p = $presets[$key];

            return "Use this exact font pairing — load it in <head> with:\n"
                ."<link href=\"{$p['google']}\" rel=\"stylesheet\">\n"
                ."Headings: \"{$p['heading']}\". Body/UI: \"{$p['body']}\". Mood: {$p['vibe']}";
        }

        // Auto: present the curated menu and let the model pick the best fit.
        $menu = [];
        foreach ($presets as $preset) {
            $menu[] = "- {$preset['heading']} + {$preset['body']} — {$preset['vibe']}\n  load: <link href=\"{$preset['google']}\" rel=\"stylesheet\">";
        }

        return "Act as a senior type designer. Choose the SINGLE best font pairing for this product, audience and tone from the curated menu below, and load it in <head> with its <link>. Do NOT use default system fonts or Plus Jakarta Sans unless it is genuinely the best fit. Prefer an elegant serif pairing for premium / book / beauty / spiritual products.\n\nCURATED FONT MENU:\n".implode("\n", $menu);
    }

    public static function isValidPreset(?string $key): bool
    {
        return $key === null || $key === 'auto' || array_key_exists($key, self::presets());
    }
}
