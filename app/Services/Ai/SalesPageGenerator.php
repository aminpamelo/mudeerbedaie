<?php

namespace App\Services\Ai;

/**
 * Contract for turning a sales-page brief into a complete, standalone HTML document.
 *
 * The builder UI and the queued job depend only on this interface, so the
 * underlying provider (laravel/ai, openai-php, etc.) can be swapped without
 * touching application code, and tests can bind a fake implementation.
 */
interface SalesPageGenerator
{
    /**
     * Generate a full HTML sales page from the given brief.
     */
    public function generate(SalesPageBrief $brief): string;
}
