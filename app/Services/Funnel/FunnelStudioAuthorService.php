<?php

declare(strict_types=1);

namespace App\Services\Funnel;

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelStepContent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Programmatically authors single-page selling funnels for the Funnel Studio
 * MCP server: a funnel with one landing step whose content is the marketer's
 * (AI-generated) HTML wrapped in a Puck CustomHtml block, plus a main product
 * so the embedded [checkout_form] can take payment.
 */
class FunnelStudioAuthorService
{
    /**
     * The tag the marketer drops into their HTML where the real checkout form
     * should appear. The public renderer swaps it for the live Livewire
     * checkout bound to this step's product.
     */
    public const CHECKOUT_TAG = '[checkout_form]';

    /**
     * Create a complete, single-page selling funnel owned by the given user.
     *
     * @param  array{product_id?: int|null, name?: string|null, price: float, compare_at_price?: float|null}  $product
     */
    public function createSellingFunnel(User $user, string $name, string $html, array $product, bool $publish = false): Funnel
    {
        return DB::transaction(function () use ($user, $name, $html, $product, $publish): Funnel {
            $funnel = Funnel::create([
                'user_id' => $user->id,
                'name' => $name,
                'type' => 'sales',
                'status' => 'draft',
                'settings' => [],
            ]);

            $step = $funnel->steps()->create([
                'name' => 'Landing Page',
                'slug' => 'landing',
                'type' => 'landing',
                'sort_order' => 0,
                'is_active' => true,
            ]);

            $this->writeLandingContent($step, $html);
            $this->syncMainProduct($step, $product);

            if ($publish) {
                $funnel->publish();
            }

            return $funnel->fresh();
        });
    }

    /**
     * Update an existing funnel's landing page: its HTML and/or its price.
     */
    public function updateLandingPage(Funnel $funnel, ?string $html = null, ?float $price = null): void
    {
        $step = $funnel->steps()->where('type', 'landing')->orderBy('sort_order')->first()
            ?? $funnel->steps()->orderBy('sort_order')->first();

        if (! $step) {
            return;
        }

        if ($html !== null) {
            $this->writeLandingContent($step, $html);
        }

        if ($price !== null) {
            $main = $step->products()->where('type', 'main')->first();
            $main?->update(['funnel_price' => $price]);
        }
    }

    /**
     * Store the given HTML as the step's published CustomHtml content,
     * guaranteeing a checkout tag is present so the page can take payment.
     */
    protected function writeLandingContent(FunnelStep $step, string $html): void
    {
        $content = [
            'content' => [[
                'type' => 'CustomHtml',
                'props' => [
                    'html' => $this->ensureCheckoutTag($html),
                    'backgroundColor' => '#ffffff',
                    'maxWidth' => '100%',
                    'align' => 'center',
                    'padding' => '0px',
                ],
            ]],
            'root' => ['props' => ['backgroundColor' => '#ffffff', 'padding' => '0px']],
        ];

        $existing = $step->content()->first();

        if ($existing) {
            $existing->update([
                'content' => $content,
                'is_published' => true,
                'published_at' => now(),
                'version' => $existing->version + 1,
            ]);

            return;
        }

        FunnelStepContent::create([
            'funnel_step_id' => $step->id,
            'content' => $content,
            'is_published' => true,
            'published_at' => now(),
            'version' => 1,
        ]);
    }

    /**
     * Attach (or update) the step's main product so checkout has something to
     * charge. Uses an existing catalog product when product_id is given,
     * otherwise a custom inline product carrying just a name and price.
     *
     * @param  array{product_id?: int|null, name?: string|null, price: float, compare_at_price?: float|null}  $product
     */
    protected function syncMainProduct(FunnelStep $step, array $product): void
    {
        $attributes = [
            'type' => 'main',
            'funnel_price' => $product['price'],
            'compare_at_price' => $product['compare_at_price'] ?? null,
            'sort_order' => 0,
            'is_active' => true,
        ];

        if (! empty($product['product_id'])) {
            $attributes['product_id'] = $product['product_id'];
        } else {
            $attributes['name'] = $product['name'] ?? 'Offer';
        }

        $existing = $step->products()->where('type', 'main')->first();

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        $step->products()->create($attributes);
    }

    /**
     * Ensure the HTML carries a checkout tag; append one if the author didn't
     * include it, so the published page is always able to take payment.
     */
    public function ensureCheckoutTag(string $html): string
    {
        if (preg_match('/\[\s*checkout[\s_-]+form\s*\]/i', $html)) {
            return $html;
        }

        return rtrim($html)."\n\n".self::CHECKOUT_TAG;
    }
}
