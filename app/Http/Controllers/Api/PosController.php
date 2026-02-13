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

            $totalAmount = max(0, $subtotal - $discountAmount);

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
}
