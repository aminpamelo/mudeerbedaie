<?php

declare(strict_types=1);

namespace App\Services\MergeTag\DataProviders;

use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Services\MergeTag\DataProviderInterface;
use Carbon\Carbon;

class OrderDataProvider implements DataProviderInterface
{
    public function getValue(string $field, array $context): ?string
    {
        // Get order from context (support both ProductOrder and FunnelOrder)
        $order = $context['product_order'] ?? $context['order'] ?? $context['funnel_order'] ?? null;

        if (! $order instanceof ProductOrder && ! $order instanceof FunnelOrder) {
            return null;
        }

        return match ($field) {
            'number' => $this->getOrderNumber($order),
            'total' => $this->formatCurrency($this->getTotal($order), $this->getCurrency($order)),
            'total_raw' => $this->formatNumber($this->getTotal($order)),
            'subtotal' => $this->formatCurrency($this->getSubtotal($order), $this->getCurrency($order)),
            'subtotal_raw' => $this->formatNumber($this->getSubtotal($order)),
            'currency' => $this->getCurrency($order),
            'status' => $this->getStatus($order),
            'items_count' => (string) $this->getItemsCount($order),
            'items_list' => $this->formatItemsList($order),
            'first_item_name' => $this->getFirstItemName($order),
            'discount_amount' => $this->formatCurrency($this->getDiscountAmount($order), $this->getCurrency($order)),
            'coupon_code' => $this->getCouponCode($order),
            'date' => $this->formatDate($order),
            'shipping_address' => $this->getShippingAddress($order),
            'billing_address' => $this->getBillingAddress($order),
            default => null,
        };
    }

    protected function getOrderNumber(ProductOrder|FunnelOrder $order): string
    {
        if ($order instanceof ProductOrder) {
            return $order->order_number ?? $order->id;
        }

        return $order->order_number ?? "FO-{$order->id}";
    }

    protected function getTotal(ProductOrder|FunnelOrder $order): float
    {
        if ($order instanceof ProductOrder) {
            return (float) ($order->total_amount ?? $order->amount ?? 0);
        }

        return (float) ($order->total ?? 0);
    }

    protected function getSubtotal(ProductOrder|FunnelOrder $order): float
    {
        if ($order instanceof ProductOrder) {
            return (float) ($order->subtotal ?? $order->total_amount ?? 0);
        }

        return (float) ($order->subtotal ?? $order->total ?? 0);
    }

    protected function getCurrency(ProductOrder|FunnelOrder $order): string
    {
        return $order->currency ?? 'MYR';
    }

    protected function getStatus(ProductOrder|FunnelOrder $order): string
    {
        return $order->status ?? 'pending';
    }

    protected function getItemsCount(ProductOrder|FunnelOrder $order): int
    {
        if ($order instanceof ProductOrder) {
            // ProductOrder might have items relationship or quantity field
            if ($order->relationLoaded('items') || method_exists($order, 'items')) {
                return $order->items?->count() ?? 1;
            }

            return $order->quantity ?? 1;
        }

        // FunnelOrder
        if ($order->relationLoaded('items') || method_exists($order, 'items')) {
            return $order->items?->count() ?? 1;
        }

        return 1;
    }

    protected function formatItemsList(ProductOrder|FunnelOrder $order): string
    {
        $items = [];

        if ($order instanceof ProductOrder) {
            // Check for items relationship
            if ($order->relationLoaded('items') && $order->items) {
                foreach ($order->items as $item) {
                    $qty = $item->quantity ?? 1;
                    $name = $item->product?->name ?? $item->name ?? 'Item';
                    $items[] = "- {$name} (x{$qty})";
                }
            } elseif ($order->product) {
                // Single product order
                $qty = $order->quantity ?? 1;
                $items[] = "- {$order->product->name} (x{$qty})";
            }
        } else {
            // FunnelOrder
            if ($order->relationLoaded('items') && $order->items) {
                foreach ($order->items as $item) {
                    $qty = $item->quantity ?? 1;
                    $name = $item->product?->name ?? $item->name ?? 'Item';
                    $items[] = "- {$name} (x{$qty})";
                }
            } elseif ($order->funnelProduct) {
                $items[] = "- {$order->funnelProduct->name} (x1)";
            }
        }

        return empty($items) ? '- No items' : implode("\n", $items);
    }

    protected function getFirstItemName(ProductOrder|FunnelOrder $order): ?string
    {
        if ($order instanceof ProductOrder) {
            if ($order->relationLoaded('items') && $order->items?->first()) {
                return $order->items->first()->product?->name ?? $order->items->first()->name;
            }

            return $order->product?->name;
        }

        // FunnelOrder
        if ($order->relationLoaded('items') && $order->items?->first()) {
            return $order->items->first()->product?->name ?? $order->items->first()->name;
        }

        return $order->funnelProduct?->name;
    }

    protected function getDiscountAmount(ProductOrder|FunnelOrder $order): float
    {
        return (float) ($order->discount_amount ?? $order->discount ?? 0);
    }

    protected function getCouponCode(ProductOrder|FunnelOrder $order): ?string
    {
        if ($order instanceof ProductOrder) {
            return $order->coupon_code ?? $order->coupon?->code ?? null;
        }

        return $order->coupon_code ?? $order->funnelCoupon?->code ?? null;
    }

    protected function formatDate(ProductOrder|FunnelOrder $order): string
    {
        $date = $order->created_at ?? now();

        if ($date instanceof Carbon) {
            return $date->format('d M Y');
        }

        return Carbon::parse($date)->format('d M Y');
    }

    protected function getShippingAddress(ProductOrder|FunnelOrder $order): ?string
    {
        $address = $order->shipping_address ?? null;

        if (is_array($address)) {
            return $this->formatAddressArray($address);
        }

        if (is_string($address)) {
            return $address;
        }

        return null;
    }

    protected function getBillingAddress(ProductOrder|FunnelOrder $order): ?string
    {
        $address = $order->billing_address ?? $order->shipping_address ?? null;

        if (is_array($address)) {
            return $this->formatAddressArray($address);
        }

        if (is_string($address)) {
            return $address;
        }

        return null;
    }

    protected function formatAddressArray(array $address): string
    {
        $parts = array_filter([
            $address['line1'] ?? $address['address_line_1'] ?? null,
            $address['line2'] ?? $address['address_line_2'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['postcode'] ?? $address['postal_code'] ?? null,
            $address['country'] ?? null,
        ]);

        return implode(', ', $parts);
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
