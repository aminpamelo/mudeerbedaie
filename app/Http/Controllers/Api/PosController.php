<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePosSaleRequest;
use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Package;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    /**
     * Search/list products with variants and stock info.
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::query()
            ->where('status', 'active')
            ->with(['variants' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }, 'primaryImage', 'category', 'stockLevels']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('name')->paginate(24);

        return response()->json($products);
    }

    /**
     * Search/list packages.
     */
    public function packages(Request $request): JsonResponse
    {
        $query = Package::query()
            ->where('status', 'active')
            ->with(['items', 'products']);

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $packages = $query->orderBy('name')->paginate(24);

        return response()->json($packages);
    }

    /**
     * Search/list courses.
     */
    public function courses(Request $request): JsonResponse
    {
        $query = Course::query()
            ->where('status', 'active')
            ->with('classes');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $courses = $query->orderBy('name')->paginate(24);

        return response()->json($courses);
    }

    /**
     * Get available classes for a course.
     */
    public function courseClasses(Course $course): JsonResponse
    {
        $classes = $course->classes()
            ->where('status', 'active')
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'status', 'max_students']);

        return response()->json(['data' => $classes]);
    }

    /**
     * Search existing customers (students/users).
     */
    public function customers(Request $request): JsonResponse
    {
        $search = $request->get('search', '');

        if (strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $customers = User::query()
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->select('id', 'name', 'email', 'phone', 'role')
            ->limit(20)
            ->get();

        return response()->json(['data' => $customers]);
    }

    /**
     * Create a new POS sale as a ProductOrder.
     */
    public function createSale(StorePosSaleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request) {
            $subtotal = 0;
            $itemsData = [];

            // Resolve items and calculate subtotal
            foreach ($validated['items'] as $item) {
                $modelClass = match ($item['itemable_type']) {
                    'product' => Product::class,
                    'package' => Package::class,
                    'course' => Course::class,
                };

                $model = $modelClass::findOrFail($item['itemable_id']);
                $totalPrice = $item['quantity'] * $item['unit_price'];
                $subtotal += $totalPrice;

                $variantName = null;
                $sku = $model->sku ?? null;
                $productId = null;
                $productVariantId = null;
                $packageId = null;

                if ($item['itemable_type'] === 'product') {
                    $productId = $model->id;
                    if (! empty($item['product_variant_id'])) {
                        $variant = $model->variants()->find($item['product_variant_id']);
                        if ($variant) {
                            $variantName = $variant->name;
                            $sku = $variant->sku;
                            $productVariantId = $variant->id;
                        }
                    }
                } elseif ($item['itemable_type'] === 'package') {
                    $packageId = $model->id;
                }

                $itemsData[] = [
                    'itemable_type' => $modelClass,
                    'itemable_id' => $item['itemable_id'],
                    'product_id' => $productId,
                    'product_variant_id' => $productVariantId,
                    'package_id' => $packageId,
                    'product_name' => $model->name,
                    'variant_name' => $variantName,
                    'sku' => $sku ?? '',
                    'quantity_ordered' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $totalPrice,
                    'item_metadata' => $item['itemable_type'] === 'course' && ! empty($item['class_id'])
                        ? ['class_id' => $item['class_id'], 'class_title' => ClassModel::find($item['class_id'])?->title]
                        : null,
                ];
            }

            // Calculate discount
            $discountAmount = 0;
            if (! empty($validated['discount_amount']) && $validated['discount_amount'] > 0) {
                if (($validated['discount_type'] ?? null) === 'percentage') {
                    $discountAmount = round($subtotal * ($validated['discount_amount'] / 100), 2);
                } else {
                    $discountAmount = $validated['discount_amount'];
                }
            }

            $shippingCost = $validated['shipping_cost'] ?? 0;
            $totalAmount = max(0, $subtotal - $discountAmount + $shippingCost);

            // Build shipping address from customer address
            $shippingAddress = null;
            $customerAddress = $validated['customer_address'] ?? null;
            if ($customerAddress) {
                $shippingAddress = ['full_address' => $customerAddress];
            }

            // Create ProductOrder with source = 'pos'
            $order = ProductOrder::create([
                'order_number' => ProductOrder::generateOrderNumber(),
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'guest_email' => $validated['customer_email'] ?? null,
                'shipping_address' => $shippingAddress,
                'source' => 'pos',
                'source_reference' => 'salesperson:'.$request->user()->id,
                'status' => 'pending',
                'order_type' => 'product',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'shipping_cost' => $shippingCost,
                'total_amount' => $totalAmount,
                'payment_method' => $validated['payment_method'],
                'order_date' => now(),
                'paid_time' => ($validated['payment_status'] === 'paid') ? now() : null,
                'internal_notes' => $validated['notes'] ?? null,
                'metadata' => [
                    'pos_sale' => true,
                    'salesperson_id' => $request->user()->id,
                    'salesperson_name' => $request->user()->name,
                    'payment_status' => $validated['payment_status'],
                    'payment_reference' => $validated['payment_reference'] ?? null,
                    'discount_type' => $validated['discount_type'] ?? null,
                    'discount_input' => $validated['discount_amount'] ?? null,
                ],
            ]);

            // Create order items and deduct stock
            foreach ($itemsData as $itemData) {
                $orderItem = $order->items()->create($itemData);
                $orderItem->deductStock();
            }

            // Create payment record so it appears on the order detail page
            $order->payments()->create([
                'payment_method' => $validated['payment_method'],
                'amount' => $totalAmount,
                'currency' => $order->currency ?? 'MYR',
                'status' => $validated['payment_status'] === 'paid' ? 'completed' : 'pending',
                'reference_number' => $validated['payment_reference'] ?? null,
                'paid_at' => $validated['payment_status'] === 'paid' ? now() : null,
                'metadata' => [
                    'pos_sale' => true,
                    'salesperson_id' => $request->user()->id,
                ],
            ]);

            $order->load('items', 'customer');

            // Return response mapped to match POS frontend expectations
            return response()->json([
                'message' => 'Sale created successfully.',
                'data' => [
                    'id' => $order->id,
                    'sale_number' => $order->order_number,
                    'order_number' => $order->order_number,
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'customer' => $order->customer,
                    'salesperson_id' => $request->user()->id,
                    'subtotal' => number_format($order->subtotal, 2, '.', ''),
                    'discount_amount' => number_format($order->discount_amount, 2, '.', ''),
                    'shipping_cost' => number_format($order->shipping_cost, 2, '.', ''),
                    'total_amount' => number_format($order->total_amount, 2, '.', ''),
                    'payment_method' => $order->payment_method,
                    'payment_reference' => $order->metadata['payment_reference'] ?? null,
                    'payment_status' => $order->metadata['payment_status'] ?? 'pending',
                    'items' => $order->items,
                ],
            ], 201);
        });
    }

    /**
     * List POS sales history (from product_orders with source=pos).
     */
    public function salesHistory(Request $request): JsonResponse
    {
        $query = ProductOrder::query()
            ->where('source', 'pos')
            ->with(['items', 'customer'])
            ->latest('order_date');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->get('status')) {
            if ($status === 'paid') {
                $query->whereNotNull('paid_time');
            } elseif ($status === 'pending') {
                $query->whereNull('paid_time')->where('status', '!=', 'cancelled');
            } elseif ($status === 'cancelled') {
                $query->where('status', 'cancelled');
            }
        }

        if ($paymentMethod = $request->get('payment_method')) {
            $query->where('payment_method', $paymentMethod);
        }

        if ($period = $request->get('period')) {
            match ($period) {
                'today' => $query->whereDate('order_date', today()),
                'this_week' => $query->whereBetween('order_date', [now()->startOfWeek(), now()->endOfWeek()]),
                'this_month' => $query->whereBetween('order_date', [now()->startOfMonth(), now()->endOfMonth()]),
                default => null,
            };
        }

        if ($request->get('today')) {
            $query->whereDate('order_date', today());
        }

        if ($salespersonId = $request->get('salesperson_id')) {
            $query->whereJsonContains('metadata->salesperson_id', (int) $salespersonId);
        }

        $sales = $query->paginate($request->get('per_page', 20));

        return response()->json($sales);
    }

    /**
     * Get sale details.
     */
    public function saleDetail(ProductOrder $sale): JsonResponse
    {
        $sale->load(['items.itemable', 'customer']);

        return response()->json(['data' => $sale]);
    }

    /**
     * Update the status of a POS sale.
     */
    public function updateSaleStatus(Request $request, ProductOrder $sale): JsonResponse
    {
        if ($sale->source !== 'pos') {
            return response()->json(['message' => 'Only POS sales can be updated here.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:paid,pending,cancelled',
        ]);

        $newStatus = $validated['status'];

        if ($newStatus === 'paid') {
            $sale->update([
                'paid_time' => now(),
                'status' => 'confirmed',
            ]);
            $sale->payments()->update([
                'status' => 'completed',
                'paid_at' => now(),
            ]);
        } elseif ($newStatus === 'pending') {
            $sale->update([
                'paid_time' => null,
                'status' => 'pending',
            ]);
            $sale->payments()->update([
                'status' => 'pending',
                'paid_at' => null,
            ]);
        } elseif ($newStatus === 'cancelled') {
            $sale->markAsCancelled('Cancelled from POS');
            $sale->payments()->update(['status' => 'cancelled']);
        }

        $sale->load(['items', 'customer']);

        return response()->json([
            'message' => 'Sale status updated successfully.',
            'data' => $sale,
        ]);
    }

    /**
     * Delete a POS sale.
     */
    public function deleteSale(ProductOrder $sale): JsonResponse
    {
        if ($sale->source !== 'pos') {
            return response()->json(['message' => 'Only POS sales can be deleted here.'], 403);
        }

        DB::transaction(function () use ($sale) {
            $sale->payments()->delete();
            $sale->items()->delete();
            $sale->delete();
        });

        return response()->json(['message' => 'Sale deleted successfully.']);
    }

    /**
     * Dashboard quick stats for POS sales.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $todaySales = ProductOrder::query()
            ->where('source', 'pos')
            ->whereDate('order_date', today())
            ->whereNotNull('paid_time');

        $totalSalesToday = $todaySales->count();
        $totalRevenue = $todaySales->sum('total_amount');

        $mySalesToday = ProductOrder::query()
            ->where('source', 'pos')
            ->whereDate('order_date', today())
            ->whereNotNull('paid_time')
            ->whereJsonContains('metadata->salesperson_id', $request->user()->id);

        $myCount = $mySalesToday->count();
        $myRevenue = $mySalesToday->sum('total_amount');

        return response()->json([
            'data' => [
                'today_sales_count' => $totalSalesToday,
                'today_revenue' => number_format($totalRevenue, 2),
                'my_sales_count' => $myCount,
                'my_revenue' => number_format($myRevenue, 2),
            ],
        ]);
    }

    /**
     * Monthly report: revenue, sales count, items sold per month for a given year.
     */
    public function reportMonthly(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->year);

        $orderStats = ProductOrder::query()
            ->where('source', 'pos')
            ->whereNotNull('paid_time')
            ->whereRaw("CAST(strftime('%Y', order_date) AS INTEGER) = ?", [$year])
            ->selectRaw("
                CAST(strftime('%m', order_date) AS INTEGER) as month_number,
                COUNT(*) as sales_count,
                COALESCE(SUM(total_amount), 0) as revenue
            ")
            ->groupByRaw("strftime('%m', order_date)")
            ->get()
            ->keyBy('month_number');

        $itemStats = DB::table('product_order_items')
            ->join('product_orders', 'product_orders.id', '=', 'product_order_items.order_id')
            ->where('product_orders.source', 'pos')
            ->whereNotNull('product_orders.paid_time')
            ->whereRaw("CAST(strftime('%Y', product_orders.order_date) AS INTEGER) = ?", [$year])
            ->selectRaw("
                CAST(strftime('%m', product_orders.order_date) AS INTEGER) as month_number,
                COALESCE(SUM(product_order_items.quantity_ordered), 0) as items_sold
            ")
            ->groupByRaw("strftime('%m', product_orders.order_date)")
            ->get()
            ->keyBy('month_number');

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $months = [];
        $totalRevenue = 0;
        $totalSales = 0;
        $totalItems = 0;

        for ($m = 1; $m <= 12; $m++) {
            $salesCount = (int) ($orderStats[$m]->sales_count ?? 0);
            $revenue = round((float) ($orderStats[$m]->revenue ?? 0), 2);
            $itemsSold = (int) ($itemStats[$m]->items_sold ?? 0);

            $totalRevenue += $revenue;
            $totalSales += $salesCount;
            $totalItems += $itemsSold;

            $months[] = [
                'month' => $m,
                'month_name' => $monthNames[$m - 1],
                'sales_count' => $salesCount,
                'revenue' => $revenue,
                'items_sold' => $itemsSold,
            ];
        }

        return response()->json([
            'data' => [
                'year' => $year,
                'totals' => [
                    'revenue' => round($totalRevenue, 2),
                    'sales_count' => $totalSales,
                    'items_sold' => $totalItems,
                ],
                'months' => $months,
            ],
        ]);
    }

    /**
     * Daily report: revenue and sales per day for a given month, or day detail with item breakdown.
     */
    public function reportDaily(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $day = $request->get('day');

        if ($day) {
            return $this->reportDayDetail($year, $month, (int) $day);
        }

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();
        $daysInMonth = $startOfMonth->daysInMonth;

        $dailyStats = ProductOrder::query()
            ->where('source', 'pos')
            ->whereNotNull('paid_time')
            ->whereBetween('order_date', [$startOfMonth, $endOfMonth])
            ->selectRaw("
                CAST(strftime('%d', order_date) AS INTEGER) as day_number,
                COUNT(*) as sales_count,
                COALESCE(SUM(total_amount), 0) as revenue
            ")
            ->groupByRaw("strftime('%d', order_date)")
            ->get()
            ->keyBy('day_number');

        $totalRevenue = 0;
        $totalSales = 0;
        $days = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $salesCount = (int) ($dailyStats[$d]->sales_count ?? 0);
            $revenue = round((float) ($dailyStats[$d]->revenue ?? 0), 2);

            $totalRevenue += $revenue;
            $totalSales += $salesCount;

            $days[] = [
                'day' => $d,
                'date' => Carbon::create($year, $month, $d)->toDateString(),
                'day_name' => Carbon::create($year, $month, $d)->format('D'),
                'sales_count' => $salesCount,
                'revenue' => $revenue,
            ];
        }

        return response()->json([
            'data' => [
                'year' => $year,
                'month' => $month,
                'month_name' => $startOfMonth->format('F'),
                'totals' => [
                    'revenue' => round($totalRevenue, 2),
                    'sales_count' => $totalSales,
                ],
                'days' => $days,
            ],
        ]);
    }

    /**
     * Detail for a specific day: item breakdown and individual orders.
     */
    private function reportDayDetail(int $year, int $month, int $day): JsonResponse
    {
        $date = Carbon::create($year, $month, $day);

        $orders = ProductOrder::query()
            ->where('source', 'pos')
            ->whereNotNull('paid_time')
            ->whereDate('order_date', $date)
            ->with('items')
            ->latest('order_date')
            ->get();

        $itemSummary = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $key = $item->product_name.'|'.($item->variant_name ?? '');
                if (! isset($itemSummary[$key])) {
                    $itemSummary[$key] = [
                        'product_name' => $item->product_name,
                        'variant_name' => $item->variant_name,
                        'quantity' => 0,
                        'total_amount' => 0,
                    ];
                }
                $itemSummary[$key]['quantity'] += $item->quantity_ordered;
                $itemSummary[$key]['total_amount'] += (float) $item->total_price;
            }
        }

        // Round totals
        foreach ($itemSummary as &$summary) {
            $summary['total_amount'] = round($summary['total_amount'], 2);
        }

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'sales_count' => $orders->count(),
                'revenue' => round((float) $orders->sum('total_amount'), 2),
                'items' => array_values($itemSummary),
                'orders' => $orders->map(fn (ProductOrder $o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'total_amount' => round((float) $o->total_amount, 2),
                    'payment_method' => $o->payment_method,
                    'order_date' => $o->order_date->toDateTimeString(),
                    'customer_name' => $o->getCustomerName(),
                ])->values(),
            ],
        ]);
    }
}
