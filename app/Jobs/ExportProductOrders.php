<?php

namespace App\Jobs;

use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ExportProductOrders implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    /** @var array<int, int>|null */
    protected ?array $fighterSourceIdsCache = null;

    public function __construct(
        public int $userId,
        public string $filename,
        public array $filters = [],
    ) {}

    public function handle(): void
    {
        $path = 'exports/'.$this->filename;
        Storage::disk('local')->makeDirectory('exports');
        $handle = fopen(Storage::disk('local')->path($path), 'w');

        fputcsv($handle, [
            'Order Number',
            'Date',
            'Source',
            'Status',
            'Payment Status',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Items',
            'Quantities',
            'Unit Prices',
            'Subtotal',
            'Discount',
            'Shipping Cost',
            'Tax',
            'Total Amount',
            'Currency',
            'Payment Method',
            'Tracking Number',
            'Shipping Provider',
            'Shipping Address',
            'Platform',
            'Platform Order ID',
            'Agent',
            'Coupon Code',
            'Customer Notes',
            'Internal Notes',
        ]);

        $query = ProductOrder::query()
            ->visibleInAdmin()
            ->with([
                'customer',
                'student',
                'agent',
                'items.product',
                'items.package',
                'payments',
                'platform',
                'platformAccount',
            ]);

        $this->applyFilters($query);

        $query->orderBy(
            $this->filters['sortBy'] ?? 'created_at',
            $this->filters['sortDirection'] ?? 'desc'
        );

        $query->lazy(500)->each(function (ProductOrder $order) use ($handle) {
            $source = $this->getOrderSource($order);
            $itemNames = $order->items->map(fn ($item) => $item->product_name ?? $item->product?->name ?? 'N/A')->implode('; ');
            $quantities = $order->items->map(fn ($item) => $item->quantity_ordered)->implode('; ');
            $unitPrices = $order->items->map(fn ($item) => number_format($item->unit_price, 2))->implode('; ');

            $shippingAddress = $this->formatShippingAddress($order->shipping_address);

            fputcsv($handle, [
                $order->order_number,
                $order->created_at?->format('Y-m-d H:i:s'),
                $source['label'],
                ucfirst($order->status),
                $order->isPaid() ? 'Paid' : ($order->status === 'cancelled' ? 'Cancelled' : 'Pending'),
                $order->getCustomerName(),
                $order->getCustomerEmail(),
                $order->getCustomerPhone(),
                $itemNames,
                $quantities,
                $unitPrices,
                number_format($order->subtotal, 2),
                number_format($order->total_discount, 2),
                number_format($order->shipping_cost, 2),
                number_format($order->tax_amount, 2),
                number_format($order->total_amount, 2),
                $order->currency ?? 'MYR',
                $order->payment_method_label,
                $order->tracking_id,
                $order->shipping_provider,
                $shippingAddress,
                $order->platform?->name,
                $order->platform_order_id,
                $order->agent?->name ?? '',
                $order->coupon_code,
                $order->customer_notes,
                $order->internal_notes,
            ]);
        });

        fclose($handle);
    }

    protected function applyFilters($query): void
    {
        $filters = $this->filters;

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('platform_order_id', 'like', '%'.$search.'%')
                    ->orWhere('platform_order_number', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('guest_email', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        if (! empty($filters['activeTab']) && $filters['activeTab'] !== 'all') {
            $query->where('status', $filters['activeTab']);
        }

        if (! empty($filters['paymentStatusFilter']) && $filters['paymentStatusFilter'] !== 'all') {
            $query->where('payment_status', $filters['paymentStatusFilter']);
        }

        if (! empty($filters['sourceTab']) && $filters['sourceTab'] !== 'all') {
            match ($filters['sourceTab']) {
                'platform' => $query->whereNotNull('platform_id'),
                'storefront' => $query->where('source', 'storefront'),
                'agent_company' => $query->whereNull('platform_id')->where(function ($q) {
                    $q->whereNotIn('source', ['funnel', 'pos', 'storefront'])
                        ->orWhereNull('source');
                }),
                'funnel' => $this->excludeFighterSources($query->where('source', 'funnel')),
                'pos' => $this->excludeFighterSources($query->where('source', 'pos')),
                'fighter' => $query->whereIn('sales_source_id', $this->fighterSalesSourceIds()),
                default => $query
            };
        }

        if (! empty($filters['productFilter'])) {
            if (str_starts_with($filters['productFilter'], 'package:')) {
                $packageId = str_replace('package:', '', $filters['productFilter']);
                $query->whereHas('items', function ($itemQuery) use ($packageId) {
                    $itemQuery->where('package_id', $packageId);
                });
            } else {
                $query->whereHas('items', function ($itemQuery) use ($filters) {
                    $itemQuery->where('product_id', $filters['productFilter']);
                });
            }
        }

        if (! empty($filters['dateFilter'])) {
            match ($filters['dateFilter']) {
                'today' => $query->whereDate('created_at', today()),
                'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                'year' => $query->whereYear('created_at', now()->year),
                default => $query
            };
        }

        if (! empty($filters['dateFrom'])) {
            $query->whereDate('created_at', '>=', $filters['dateFrom']);
        }

        if (! empty($filters['dateTo'])) {
            $query->whereDate('created_at', '<=', $filters['dateTo']);
        }
    }

    /**
     * Sales source ids owned by fighter users. Together these make up the
     * "Fighter" source, mirroring the order-list Volt component.
     *
     * @return array<int, int>
     */
    protected function fighterSalesSourceIds(): array
    {
        return $this->fighterSourceIdsCache ??= User::query()
            ->withTrashed()
            ->where('role', 'fighter')
            ->whereNotNull('sales_source_id')
            ->pluck('sales_source_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Exclude fighter-owned sales sources from a query so fighter orders
     * (source='pos'/'funnel' + fighter sales_source_id) are not exported
     * under the POS/Funnel tabs. Keeps rows with a null sales_source_id so
     * the SQL `NOT IN` + NULL pitfall doesn't drop non-fighter orders.
     */
    protected function excludeFighterSources(Builder $query): Builder
    {
        $ids = $this->fighterSalesSourceIds();

        if (empty($ids)) {
            return $query;
        }

        return $query->where(function ($q) use ($ids) {
            $q->whereNotIn('sales_source_id', $ids)
                ->orWhereNull('sales_source_id');
        });
    }

    /**
     * Build a single-line shipping address from the order's address payload.
     *
     * Different order sources store address parts under different keys
     * (funnel: address_line_1 / postal_code; TikTok: address_line1 / postal_code
     * with city & state nested in district_info), so every component is resolved
     * from a list of known aliases to avoid dropping fields in the export.
     */
    protected function formatShippingAddress(mixed $address): string
    {
        if (is_string($address)) {
            $decoded = json_decode($address, true);
            $address = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($address)) {
            return '';
        }

        if (! empty($address['full_address'])) {
            return $address['full_address'];
        }

        $pick = function (array $keys) use ($address) {
            foreach ($keys as $key) {
                if (filled($address[$key] ?? null)) {
                    return $address[$key];
                }
            }

            return null;
        };

        $line1 = $pick(['address_line_1', 'address_line1', 'address', 'street', 'line1']);
        $line2 = $pick(['address_line_2', 'address_line2', 'line2']);
        $city = $pick(['city', 'district']);
        $state = $pick(['state']);
        $postcode = $pick(['postal_code', 'postcode', 'zip', 'postal']);
        $country = $pick(['country']);

        // TikTok Shop nests the real state/district inside district_info when the
        // flat city/state fields are null.
        if (is_array($address['district_info'] ?? null)) {
            foreach ($address['district_info'] as $level) {
                $levelName = strtolower($level['address_level_name'] ?? '');
                $value = $level['address_name'] ?? null;

                if (! filled($value)) {
                    continue;
                }

                if (! $state && $levelName === 'state') {
                    $state = $value;
                }

                if (! $city && $levelName === 'district') {
                    $city = $value;
                }
            }
        }

        return implode(', ', array_filter(
            [$line1, $line2, $city, $state, $postcode, $country],
            fn ($value) => filled($value)
        ));
    }

    protected function getOrderSource(ProductOrder $order): array
    {
        if ($order->platform_id) {
            return ['label' => $order->platform?->name ?? 'Platform'];
        }

        if ($order->agent_id) {
            return ['label' => 'Agent'];
        }

        if ($order->sales_source_id && in_array((int) $order->sales_source_id, $this->fighterSalesSourceIds(), true)) {
            return ['label' => 'Fighter'];
        }

        if ($order->source === 'funnel') {
            return ['label' => 'Sales Funnel'];
        }

        if ($order->source === 'pos') {
            return ['label' => 'POS'];
        }

        return ['label' => 'Company'];
    }
}
