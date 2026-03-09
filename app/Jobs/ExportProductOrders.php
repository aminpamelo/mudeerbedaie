<?php

namespace App\Jobs;

use App\Models\ProductOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ExportProductOrders implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public int $userId,
        public string $filename,
        public array $filters = [],
    ) {}

    public function handle(): void
    {
        $path = 'exports/'.$this->filename;
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

            $shippingAddress = '';
            if (is_array($order->shipping_address)) {
                $addr = $order->shipping_address;
                if (! empty($addr['full_address'])) {
                    $shippingAddress = $addr['full_address'];
                } else {
                    $shippingAddress = implode(', ', array_filter([
                        $addr['address'] ?? $addr['address_line1'] ?? '',
                        $addr['city'] ?? '',
                        $addr['state'] ?? '',
                        $addr['postcode'] ?? $addr['zip'] ?? '',
                        $addr['country'] ?? '',
                    ]));
                }
            }

            fputcsv($handle, [
                $order->order_number,
                $order->created_at?->format('Y-m-d H:i:s'),
                $source['label'],
                ucfirst($order->status),
                $order->isPaid() ? 'Paid' : 'Unpaid',
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

        if (! empty($filters['sourceTab']) && $filters['sourceTab'] !== 'all') {
            match ($filters['sourceTab']) {
                'platform' => $query->whereNotNull('platform_id'),
                'agent_company' => $query->whereNull('platform_id')->where(function ($q) {
                    $q->whereNotIn('source', ['funnel', 'pos'])
                        ->orWhereNull('source');
                }),
                'funnel' => $query->where('source', 'funnel'),
                'pos' => $query->where('source', 'pos'),
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

    protected function getOrderSource(ProductOrder $order): array
    {
        if ($order->platform_id) {
            return ['label' => $order->platform?->name ?? 'Platform'];
        }

        if ($order->agent_id) {
            return ['label' => 'Agent'];
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
