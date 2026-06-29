<?php

namespace App\Services\Ai;

/**
 * Immutable brief handed to a SalesPageGenerator describing what page to produce.
 */
class SalesPageBrief
{
    /**
     * @param  list<array{url: string, alt: string, title: string}>  $assets  Brand images the AI must use (real Media URLs).
     * @param  array{primary: string, secondary: string, accent: string, font: string}  $brand
     */
    public function __construct(
        public readonly string $title,
        public readonly string $prompt,
        public readonly ?string $targetAudience = null,
        public readonly ?string $tone = null,
        public readonly ?string $designNotes = null,
        public readonly ?string $stylePreset = null,
        public readonly array $assets = [],
        public readonly array $brand = [],
        public readonly ?string $currentHtml = null,
        public readonly ?string $refineInstruction = null,
        public readonly ?string $extraDirection = null,
    ) {}

    public function isRefinement(): bool
    {
        return filled($this->currentHtml) && filled($this->refineInstruction);
    }
}
