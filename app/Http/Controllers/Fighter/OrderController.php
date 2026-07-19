<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fighter\UpdateOrderRequest;
use App\Models\ClassModel;
use App\Models\Course;
use App\Models\FunnelOrder;
use App\Models\Package;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Services\Fighter\FighterProvisioner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Feed of every order attributed to the fighter — both funnel orders and
     * manually-created (POS) orders, unified by the fighter's own sales-source
     * segment.
     *
     * `?view=trash` lists the fighter's soft-deleted orders (the bin) so they
     * can be restored.
     */
    public function index(Request $request): Response
    {
        $trash = $request->query('view') === 'trash';

        $page = $this->ownedOrders($request)
            ->when($trash, fn ($q) => $q->onlyTrashed())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Orders', [
            'view' => $trash ? 'trash' : 'active',
            'trashCount' => $this->ownedOrders($request)->onlyTrashed()->count(),
            'orders' => [
                'data' => $page->getCollection()->map(fn (ProductOrder $o): array => $this->listRow($o))->values(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }

    /**
     * The manual "create order" form. Ensures the fighter has a sales-source
     * segment so the created order is attributed to them.
     */
    public function create(Request $request): Response
    {
        $segment = app(FighterProvisioner::class)->ensureSalesSource($request->user());

        return Inertia::render('OrderCreate', [
            'segment' => ['id' => $segment->id, 'name' => $segment->name],
            'currency' => 'MYR',
        ]);
    }

    /**
     * Full detail for one of the fighter's own orders (for the View/Edit modal).
     */
    public function show(Request $request, ProductOrder $order): JsonResponse
    {
        $this->assertOwned($request, $order);

        $order->load(['items', 'customer']);

        return response()->json(['data' => $this->detail($order)]);
    }

    /**
     * Edit one of the fighter's own orders: items, customer, payment, shipping,
     * receipt and notes. Works for both manual and funnel orders the fighter
     * owns. The segment is never changed — it stays the fighter's own.
     */
    public function update(UpdateOrderRequest $request, ProductOrder $order): JsonResponse
    {
        $this->assertOwned($request, $order);

        $validated = $request->validated();

        $newReceiptPath = null;
        $receiptChange = null;
        if ($request->hasFile('receipt_attachment')) {
            $newReceiptPath = $request->file('receipt_attachment')->store('pos/receipts', 'public');
            $receiptChange = 'replaced';
        } elseif ($request->boolean('remove_receipt_attachment')) {
            $receiptChange = 'removed';
        }

        return DB::transaction(function () use ($validated, $order, $request, $newReceiptPath, $receiptChange) {
            // Some (seeded/legacy) funnel orders carry a total but no line items.
            // Only touch items + recompute financials when items are actually
            // sent; otherwise preserve the stored totals and just update the
            // customer/payment/notes fields.
            $hasItems = ! empty($validated['items'] ?? []);

            $subtotal = (float) $order->subtotal;
            $shippingCost = (float) $order->shipping_cost;
            $totalAmount = (float) $order->total_amount;

            if ($hasItems) {
                $existingItems = $order->items()->get()->keyBy('id');
                $modelClassMap = [
                    'product' => Product::class,
                    'package' => Package::class,
                    'course' => Course::class,
                ];

                $subtotal = 0;
                $touchedExistingIds = [];
                $itemUpserts = [];

                foreach ($validated['items'] as $row) {
                    $modelClass = $modelClassMap[$row['itemable_type']];
                    $model = $modelClass::findOrFail($row['itemable_id']);
                    $totalPrice = $row['quantity'] * $row['unit_price'];
                    $subtotal += $totalPrice;

                    $variantName = null;
                    $sku = $model->sku ?? null;
                    $productId = null;
                    $productVariantId = null;
                    $packageId = null;

                    if ($row['itemable_type'] === 'product') {
                        $productId = $model->id;
                        if (! empty($row['product_variant_id'])) {
                            $variant = $model->variants()->find($row['product_variant_id']);
                            if ($variant) {
                                $variantName = $variant->name;
                                $sku = $variant->sku;
                                $productVariantId = $variant->id;
                            }
                        }
                    } elseif ($row['itemable_type'] === 'package') {
                        $packageId = $model->id;
                    }

                    $data = [
                        'itemable_type' => $modelClass,
                        'itemable_id' => $row['itemable_id'],
                        'product_id' => $productId,
                        'product_variant_id' => $productVariantId,
                        'package_id' => $packageId,
                        'product_name' => $model->name,
                        'variant_name' => $variantName,
                        'sku' => $sku ?? '',
                        'quantity_ordered' => $row['quantity'],
                        'unit_price' => $row['unit_price'],
                        'total_price' => $totalPrice,
                        'item_metadata' => $row['itemable_type'] === 'course' && ! empty($row['class_id'])
                            ? ['class_id' => $row['class_id'], 'class_title' => ClassModel::find($row['class_id'])?->title]
                            : null,
                    ];

                    $existing = isset($row['id']) ? $existingItems->get($row['id']) : null;
                    if ($existing) {
                        $touchedExistingIds[] = $existing->id;
                    }
                    $itemUpserts[] = ['existing' => $existing, 'data' => $data];
                }

                foreach ($existingItems as $id => $oldItem) {
                    if (! in_array($id, $touchedExistingIds, true)) {
                        $oldItem->restoreStock();
                        $oldItem->delete();
                    }
                }

                foreach ($itemUpserts as $upsert) {
                    $existing = $upsert['existing'];
                    $data = $upsert['data'];

                    if ($existing) {
                        $oldQty = (int) $existing->quantity_ordered;
                        $newQty = (int) $data['quantity_ordered'];
                        $existing->update($data);

                        if ($newQty > $oldQty) {
                            $stockHelper = $existing->replicate(['quantity_ordered']);
                            $stockHelper->id = $existing->id;
                            $stockHelper->order_id = $existing->order_id;
                            $stockHelper->quantity_ordered = $newQty - $oldQty;
                            $stockHelper->setRelations($existing->getRelations());
                            $stockHelper->deductStock();
                        } elseif ($newQty < $oldQty) {
                            $existing->restoreStock($oldQty - $newQty);
                        }
                    } else {
                        $newItem = $order->items()->create($data);
                        $newItem->deductStock();
                    }
                }

                $shippingCost = $validated['shipping_cost'] ?? 0;
                $totalAmount = max(0, $subtotal + $shippingCost);
            }

            $customerAddress = $validated['customer_address'] ?? null;
            $addressParts = array_filter([
                'address' => $customerAddress,
                'city' => $validated['customer_city'] ?? null,
                'state' => $validated['customer_state'] ?? null,
                'postcode' => $validated['customer_postcode'] ?? null,
            ], fn ($v) => filled($v));

            $shippingAddress = $order->shipping_address;
            if (! empty($addressParts)) {
                $shippingAddress = $addressParts + [
                    'full_address' => implode(', ', array_filter([
                        $customerAddress,
                        $validated['customer_city'] ?? null,
                        trim(($validated['customer_postcode'] ?? '').' '.($validated['customer_state'] ?? '')) ?: null,
                    ], fn ($v) => filled($v))),
                ];
            }

            $metadata = $order->metadata ?? [];
            $metadata['payment_reference'] = $validated['payment_reference'] ?? null;
            $metadata['payment_status'] = $validated['payment_status'];

            if (in_array($receiptChange, ['replaced', 'removed'], true) && $order->receipt_attachment) {
                Storage::disk('public')->delete($order->receipt_attachment);
            }

            $updatePayload = [
                'customer_name' => $validated['customer_name'] ?? $order->customer_name,
                'customer_phone' => $validated['customer_phone'] ?? $order->customer_phone,
                'guest_email' => $validated['customer_email'] ?? $order->guest_email,
                'shipping_address' => $shippingAddress,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_status'],
                'paid_time' => $validated['payment_status'] === 'paid' ? ($order->paid_time ?? now()) : null,
                'internal_notes' => $validated['notes'] ?? $order->internal_notes,
                'metadata' => $metadata,
            ];

            // Only rewrite the money when items were edited; otherwise keep the
            // order's stored totals intact.
            if ($hasItems) {
                $updatePayload['subtotal'] = $subtotal;
                $updatePayload['shipping_cost'] = $shippingCost;
                $updatePayload['total_amount'] = $totalAmount;
            }

            if ($receiptChange === 'replaced') {
                $updatePayload['receipt_attachment'] = $newReceiptPath;
            } elseif ($receiptChange === 'removed') {
                $updatePayload['receipt_attachment'] = null;
            }

            $order->update($updatePayload);

            $payment = $order->payments()->first();
            if ($payment) {
                $payment->update([
                    'payment_method' => $validated['payment_method'],
                    'amount' => $totalAmount,
                    'status' => $validated['payment_status'] === 'paid' ? 'completed' : 'pending',
                    'reference_number' => $validated['payment_reference'] ?? null,
                    'paid_at' => $validated['payment_status'] === 'paid' ? ($payment->paid_at ?? now()) : null,
                ]);
            }

            if ($hasItems) {
                $funnelOrder = FunnelOrder::where('product_order_id', $order->id)->first();
                if ($funnelOrder) {
                    $funnelOrder->update(['funnel_revenue' => $totalAmount]);
                }
            }

            $order->addSystemNote('Order edited by fighter '.$request->user()->name);
            if ($receiptChange === 'replaced') {
                $order->addSystemNote('Receipt replaced by '.$request->user()->name);
            } elseif ($receiptChange === 'removed') {
                $order->addSystemNote('Receipt removed by '.$request->user()->name);
            }

            $order->load(['items', 'customer']);

            return response()->json([
                'message' => 'Order updated.',
                'data' => $this->detail($order),
            ]);
        });
    }

    /**
     * Soft-delete (trash) one of the fighter's own orders. Restorable from the
     * bin. Because ProductOrder uses SoftDeletes globally, the order also leaves
     * the team/admin fulfilment views until it is restored.
     */
    public function destroy(Request $request, ProductOrder $order): JsonResponse
    {
        $this->assertOwned($request, $order);

        $order->addSystemNote('Order moved to bin by fighter '.$request->user()->name);
        $order->delete();

        return response()->json(['message' => 'Order moved to bin.']);
    }

    /**
     * Restore a trashed order from the bin.
     */
    public function restore(Request $request, int $order): JsonResponse
    {
        $model = $this->ownedOrders($request)
            ->onlyTrashed()
            ->findOrFail($order);

        $model->restore();
        $model->addSystemNote('Order restored from bin by fighter '.$request->user()->name);

        return response()->json(['message' => 'Order restored.']);
    }

    /**
     * Base query scoped to the acting fighter's segment. Non-fighters (an admin
     * peeking at the portal) have no segment, so they see/act on nothing here.
     *
     * @return Builder<ProductOrder>
     */
    private function ownedOrders(Request $request)
    {
        $segmentId = $request->user()->sales_source_id;

        return ProductOrder::query()->when(
            $segmentId,
            fn ($q) => $q->where('sales_source_id', $segmentId),
            fn ($q) => $q->whereRaw('1 = 0'),
        );
    }

    private function assertOwned(Request $request, ProductOrder $order): void
    {
        $segmentId = $request->user()->sales_source_id;

        abort_if(! $segmentId || (int) $order->sales_source_id !== (int) $segmentId, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(ProductOrder $o): array
    {
        return [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'payment_method' => $o->payment_method,
            'total' => (float) $o->total_amount,
            'currency' => $o->currency,
            'source' => $o->source,
            'source_label' => $this->sourceLabel($o->source),
            'receipt_url' => $o->receipt_attachment_url,
            'tracking_id' => $o->tracking_id ?: null,
            'shipping_provider' => $o->shipping_provider_label,
            'tracking_url' => $o->tracking_url,
            'created_at' => optional($o->created_at)->toIso8601String(),
            'deleted_at' => optional($o->deleted_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(ProductOrder $o): array
    {
        $address = is_array($o->shipping_address) ? $o->shipping_address : [];
        $metadata = $o->metadata ?? [];

        return [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'payment_method' => $o->payment_method ?? 'cash',
            'payment_reference' => $metadata['payment_reference'] ?? $o->reference_number,
            'source' => $o->source,
            'source_label' => $this->sourceLabel($o->source),
            'subtotal' => (float) $o->subtotal,
            'shipping_cost' => (float) $o->shipping_cost,
            'total' => (float) $o->total_amount,
            'currency' => $o->currency,
            'tracking_id' => $o->tracking_id ?: null,
            'shipping_provider' => $o->shipping_provider_label,
            'tracking_url' => $o->tracking_url,
            'receipt_url' => $o->receipt_attachment_url,
            'notes' => $o->internal_notes,
            'created_at' => optional($o->created_at)->toIso8601String(),
            'customer' => [
                'name' => $o->customer_name ?: $o->customer?->name,
                'phone' => $o->customer_phone ?: $o->customer?->phone,
                'email' => $this->cleanEmail($o->guest_email ?: $o->customer?->email),
                'address' => $address['address'] ?? null,
                'postcode' => $address['postcode'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
            ],
            'items' => $o->items->map(fn ($item): array => [
                'id' => $item->id,
                'itemable_type' => $this->itemableTypeKey($item->itemable_type),
                'itemable_id' => $item->itemable_id ?? $item->product_id ?? $item->package_id,
                'product_variant_id' => $item->product_variant_id,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'quantity' => (int) $item->quantity_ordered,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
            ])->values(),
        ];
    }

    /**
     * Funnel checkouts store a "No email provided" placeholder when the buyer
     * left it blank — treat that as no email so the edit form starts empty.
     */
    private function cleanEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '' || strcasecmp($email, 'No email provided') === 0) {
            return null;
        }

        return $email;
    }

    private function itemableTypeKey(?string $class): string
    {
        return match ($class) {
            Package::class => 'package',
            Course::class => 'course',
            default => 'product',
        };
    }

    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            'funnel' => 'Funnel',
            'pos' => 'Manual',
            null => '—',
            default => ucfirst($source),
        };
    }
}
