<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * A laravel/ai agent that writes conversion-focused sales pages. The system
 * instructions are built per-request by the generator and passed in, so the
 * agent stays a thin wrapper around the configured provider/model.
 */
class SalesPageWriter implements Agent
{
    use Promptable;

    public function __construct(private readonly string $systemInstructions) {}

    public function instructions(): Stringable|string
    {
        return $this->systemInstructions;
    }

    /**
     * The model is configurable via AI_SALES_PAGE_MODEL (defaults to gpt-4o).
     */
    public function model(): string
    {
        return (string) config('ai_sales_pages.model', 'gpt-4o');
    }
}
