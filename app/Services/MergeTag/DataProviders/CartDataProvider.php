<?php

declare(strict_types=1);

namespace App\Services\MergeTag\DataProviders;

use App\Models\FunnelCart;
use App\Services\MergeTag\DataProviderInterface;
use Carbon\Carbon;

class CartDataProvider implements DataProviderInterface
{
    public function getValue(string $field, array $context): ?string
    {
        // Get cart from context
        $cart = $context['funnel_cart'] ?? $context['cart'] ?? null;

        if (! $cart instanceof FunnelCart) {
            // Try to get cart data directly from context
            return $this->getFromDirectContext($field, $context);
        }

        return match ($field) {
            'total' => $this->formatCurrency($this->getTotal($cart), $this->getCurrency($cart)),
            'total_raw' => $this->formatNumber($this->getTotal($cart)),
            'items_count' => (string) $this->getItemsCount($cart),
            'items_list' => $this->formatItemsList($cart),
            'first_item_name' => $this->getFirstItemName($cart),
            'checkout_url' => $this->getCheckoutUrl($cart, $context),
            'abandoned_at' => $this->getAbandonedTime($cart),
            'recovery_url' => $this->getRecoveryUrl($cart, $context),
            default => null,
        };
    }

    protected function getFromDirectContext(string $field, array $context): ?string
    {
        return match ($field) {
            'total' => isset($context['cart_total']) ? $this->formatCurrency((float) $context['cart_total'], $context['currency'] ?? 'MYR') : null,
            'total_raw' => isset($context['cart_total']) ? $this->formatNumber((float) $context['cart_total']) : null,
            'items_count' => isset($context['cart_items_count']) ? (string) $context['cart_items_count'] : null,
            'items_list' => $context['cart_items_list'] ?? null,
            'first_item_name' => $context['cart_first_item'] ?? null,
            'checkout_url' => $context['checkout_url'] ?? null,
            'recovery_url' => $context['recovery_url'] ?? $context['checkout_url'] ?? null,
            default => null,
        };
    }

    protected function getTotal(FunnelCart $cart): float
    {
        return (float) ($cart->total ?? $cart->subtotal ?? 0);
    }

    protected function getCurrency(FunnelCart $cart): string
    {
        return $cart->currency ?? 'MYR';
    }

    protected function getItemsCount(FunnelCart $cart): int
    {
        // Check for items relationship
        if ($cart->relationLoaded('items') || method_exists($cart, 'items')) {
            return $cart->items?->count() ?? 0;
        }

        // From cart_data JSON field
        $cartData = $cart->cart_data ?? $cart->items_data ?? null;
        if ($cartData) {
            if (is_string($cartData)) {
                $cartData = json_decode($cartData, true);
            }
            if (is_array($cartData)) {
                return count($cartData);
            }
        }

        return 0;
    }

    protected function formatItemsList(FunnelCart $cart): string
    {
        $items = [];

        // Check for items relationship
        if ($cart->relationLoaded('items') && $cart->items) {
            foreach ($cart->items as $item) {
                $qty = $item->quantity ?? 1;
                $name = $item->product?->name ?? $item->name ?? 'Item';
                $items[] = "- {$name} (x{$qty})";
            }
        } else {
            // Try cart_data JSON field
            $cartData = $cart->cart_data ?? $cart->items_data ?? null;
            if ($cartData) {
                if (is_string($cartData)) {
                    $cartData = json_decode($cartData, true);
                }
                if (is_array($cartData)) {
                    foreach ($cartData as $item) {
                        $qty = $item['quantity'] ?? 1;
                        $name = $item['name'] ?? $item['product_name'] ?? 'Item';
                        $items[] = "- {$name} (x{$qty})";
                    }
                }
            }
        }

        return empty($items) ? '- Empty cart' : implode("\n", $items);
    }

    protected function getFirstItemName(FunnelCart $cart): ?string
    {
        // Check for items relationship
        if ($cart->relationLoaded('items') && $cart->items?->first()) {
            return $cart->items->first()->product?->name ?? $cart->items->first()->name;
        }

        // Try cart_data JSON field
        $cartData = $cart->cart_data ?? $cart->items_data ?? null;
        if ($cartData) {
            if (is_string($cartData)) {
                $cartData = json_decode($cartData, true);
            }
            if (is_array($cartData) && ! empty($cartData)) {
                $firstItem = reset($cartData);

                return $firstItem['name'] ?? $firstItem['product_name'] ?? null;
            }
        }

        return null;
    }

    protected function getCheckoutUrl(FunnelCart $cart, array $context): ?string
    {
        // Check if cart has a recovery token
        $token = $cart->recovery_token ?? $cart->cart_token ?? null;

        if ($token) {
            $baseUrl = config('app.url');

            return "{$baseUrl}/cart/recover/{$token}";
        }

        // Try to construct from funnel
        $funnel = $cart->funnel ?? $context['funnel'] ?? null;
        if ($funnel) {
            $baseUrl = config('app.url');
            $funnelSlug = $funnel->slug ?? $funnel->id;

            return "{$baseUrl}/f/{$funnelSlug}/checkout";
        }

        return null;
    }

    protected function getRecoveryUrl(FunnelCart $cart, array $context): ?string
    {
        // Recovery URL is same as checkout URL for abandoned cart
        return $this->getCheckoutUrl($cart, $context);
    }

    protected function getAbandonedTime(FunnelCart $cart): ?string
    {
        $abandonedAt = $cart->abandoned_at ?? null;

        if (! $abandonedAt) {
            // If no abandoned_at, calculate from updated_at
            $lastActivity = $cart->updated_at ?? $cart->created_at;
            if ($lastActivity) {
                return Carbon::parse($lastActivity)->diffForHumans();
            }

            return null;
        }

        return Carbon::parse($abandonedAt)->diffForHumans();
    }

    protected function formatCurrency(float $amount, string $currency): string
    {
        $symbol = match (strtoupper($currency)) {
            'MYR' => 'RM',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'SGD' => 'S$',
            default => $currency.' ',
        };

        return $symbol.' '.number_format($amount, 2);
    }

    protected function formatNumber(float $amount): string
    {
        return number_format($amount, 2);
    }
}
