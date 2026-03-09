<?php

use App\Models\ProductOrder;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    use WithPagination;

    public string $search = '';

    public string $activeTab = 'all';

    public string $sourceTab = 'all';

    public string $productFilter = '';

    public string $dateFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    // Inline phone editing
    public ?int $editingPhoneOrderId = null;

    public string $editingPhoneValue = '';

    // Inline tracking number editing
    public ?int $editingTrackingOrderId = null;

    public string $editingTrackingValue = '';

    public function startEditingPhone(int $orderId, ?string $currentPhone): void
    {
        $this->editingPhoneOrderId = $orderId;
        $this->editingPhoneValue = $currentPhone ?? '';
    }

    public function savePhone(): void
    {
        if ($this->editingPhoneOrderId) {
            $order = ProductOrder::findOrFail($this->editingPhoneOrderId);
            $order->update(['customer_phone' => $this->editingPhoneValue]);

            $this->dispatch('order-updated', message: "Phone number updated for order {$order->order_number}");
        }

        $this->cancelEditingPhone();
    }

    public function cancelEditingPhone(): void
    {
        $this->editingPhoneOrderId = null;
        $this->editingPhoneValue = '';
    }

    public function startEditingTracking(int $orderId, ?string $currentTracking): void
    {
        $this->editingTrackingOrderId = $orderId;
        $this->editingTrackingValue = $currentTracking ?? '';
    }

    public function saveTracking(): void
    {
        if ($this->editingTrackingOrderId) {
            $order = ProductOrder::findOrFail($this->editingTrackingOrderId);
            $order->update(['tracking_id' => $this->editingTrackingValue ?: null]);

            $this->dispatch('order-updated', message: "Tracking number updated for order {$order->order_number}");
        }

        $this->cancelEditingTracking();
    }

    public function cancelEditingTracking(): void
    {
        $this->editingTrackingOrderId = null;
        $this->editingTrackingValue = '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActiveTab(): void
    {
        $this->resetPage();
    }

    public function updatingDateFilter(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->dateFilter = '';
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->dateFilter = '';
        $this->resetPage();
    }

    public function updatingSourceTab(): void
    {
        $this->resetPage();
    }

    public function updatingProductFilter(): void
    {
        $this->resetPage();
    }

    public function setSortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function updateOrderStatus(int $orderId, string $status): void
    {
        $order = ProductOrder::findOrFail($orderId);

        // Call appropriate status method based on status
        match ($status) {
            'confirmed' => $order->markAsConfirmed(),
            'processing' => $order->markAsProcessing(),
            'shipped' => $order->markAsShipped(),
            'delivered' => $order->markAsDelivered(),
            'cancelled' => $order->markAsCancelled('Cancelled by admin'),
            'returned' => $order->markAsReturned(),
            default => $order->update(['status' => $status])
        };

        $this->dispatch('order-updated', message: "Order {$order->order_number} status updated to {$status}");
    }

    public function getOrders()
    {
        return ProductOrder::query()
            ->visibleInAdmin()
            ->with([
                'customer',
                'student',
                'agent',
                'items.product',
                'items.warehouse',
                'payments',
                'platform',
                'platformAccount',
                'classAssignmentApprovals.class',
            ])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_number', 'like', '%'.$this->search.'%')
                        ->orWhere('platform_order_id', 'like', '%'.$this->search.'%')
                        ->orWhere('platform_order_number', 'like', '%'.$this->search.'%')
                        ->orWhere('customer_name', 'like', '%'.$this->search.'%')
                        ->orWhere('guest_email', 'like', '%'.$this->search.'%')
                        ->orWhereHas('customer', function ($customerQuery) {
                            $customerQuery->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('email', 'like', '%'.$this->search.'%');
                        })
                        ->orWhereRaw("JSON_EXTRACT(metadata, '$.package_name') LIKE ?", ['%'.$this->search.'%']);
                });
            })
            ->when($this->activeTab !== 'all', function ($query) {
                $query->where('status', $this->activeTab);
            })
            ->when($this->sourceTab !== 'all', function ($query) {
                match ($this->sourceTab) {
                    'platform' => $query->whereNotNull('platform_id'),
                    'agent_company' => $query->whereNull('platform_id')->where(function ($q) {
                        $q->whereNotIn('source', ['funnel', 'pos'])
                          ->orWhereNull('source');
                    }),
                    'funnel' => $query->where('source', 'funnel'),
                    'pos' => $query->where('source', 'pos'),
                    default => $query
                };
            })
            ->when($this->productFilter, function ($query) {
                if (str_starts_with($this->productFilter, 'package:')) {
                    // Filter by specific package ID in order items
                    $packageId = str_replace('package:', '', $this->productFilter);
                    $query->whereHas('items', function ($itemQuery) use ($packageId) {
                        $itemQuery->where('package_id', $packageId);
                    });
                } else {
                    // Filter by product ID
                    $query->whereHas('items', function ($itemQuery) {
                        $itemQuery->where('product_id', $this->productFilter);
                    });
                }
            })
            ->when($this->dateFilter, function ($query) {
                match ($this->dateFilter) {
                    'today' => $query->whereDate('created_at', today()),
                    'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                    'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                    'year' => $query->whereYear('created_at', now()->year),
                    default => $query
                };
            })
            ->when($this->dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);
    }

    public function getOrderStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'partially_shipped' => 'Partially Shipped',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'returned' => 'Returned',
        ];
    }

    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'pending' => 'yellow',
            'confirmed' => 'blue',
            'processing' => 'purple',
            'partially_shipped' => 'orange',
            'shipped' => 'cyan',
            'delivered' => 'green',
            'cancelled', 'refunded', 'returned' => 'red',
            default => 'gray'
        };
    }

    public function getOrderSource(ProductOrder $order): array
    {
        if ($order->platform_id) {
            return [
                'type' => 'platform',
                'label' => $order->platform?->name ?? 'Platform',
                'color' => 'purple',
                'icon' => 'globe-alt',
            ];
        }

        if ($order->agent_id) {
            return [
                'type' => 'agent',
                'label' => 'Agent',
                'color' => 'blue',
                'icon' => 'user-group',
            ];
        }

        if ($order->source === 'funnel') {
            return [
                'type' => 'funnel',
                'label' => 'Sales Funnel',
                'color' => 'green',
                'icon' => 'funnel',
            ];
        }

        if ($order->source === 'pos') {
            return [
                'type' => 'pos',
                'label' => 'POS',
                'color' => 'orange',
                'icon' => 'calculator',
            ];
        }

        return [
            'type' => 'company',
            'label' => 'Company',
            'color' => 'cyan',
            'icon' => 'building-office',
        ];
    }

    public function getProductsAndPackages(): array
    {
        $items = [];

        // Get only products that have been ordered
        $productIds = \App\Models\ProductOrderItem::query()
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');

        $products = \App\Models\Product::query()
            ->whereIn('id', $productIds)
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        foreach ($products as $product) {
            $items[] = [
                'value' => $product->id,
                'label' => $product->name.($product->sku ? " ({$product->sku})" : ''),
                'type' => 'product',
            ];
        }

        // Get only packages that have been ordered
        $packageIds = \App\Models\ProductOrderItem::query()
            ->whereNotNull('package_id')
            ->distinct()
            ->pluck('package_id');

        $packages = \App\Models\Package::query()
            ->whereIn('id', $packageIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($packages as $package) {
            $items[] = [
                'value' => 'package:'.$package->id,
                'label' => $package->name.' (Package)',
                'type' => 'package',
            ];
        }

        return $items;
    }

    public function getStatusCount(string $status): int
    {
        $query = ProductOrder::query()->visibleInAdmin();

        // Apply source filter based on current sourceTab
        if ($this->sourceTab !== 'all') {
            match ($this->sourceTab) {
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

        // Apply status filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query->count();
    }

    public function getActionNeededStats(): array
    {
        $counts = ProductOrder::visibleInAdmin()->selectRaw("
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_confirmation,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as ready_to_ship
        ")->first();

        $unpaidOrders = ProductOrder::visibleInAdmin()->whereHas('payments', function ($query) {
            $query->where('status', '!=', 'paid');
        })->whereNotIn('status', ['cancelled', 'refunded'])->count();

        return [
            'pending_confirmation' => $counts->pending_confirmation ?? 0,
            'unpaid_orders' => $unpaidOrders,
            'processing' => $counts->processing ?? 0,
            'ready_to_ship' => $counts->ready_to_ship ?? 0,
        ];
    }

    public function exportOrders()
    {
        return response()->streamDownload(function () {
            $orders = ProductOrder::query()
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
                ])
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('order_number', 'like', '%'.$this->search.'%')
                            ->orWhere('platform_order_id', 'like', '%'.$this->search.'%')
                            ->orWhere('platform_order_number', 'like', '%'.$this->search.'%')
                            ->orWhere('customer_name', 'like', '%'.$this->search.'%')
                            ->orWhere('guest_email', 'like', '%'.$this->search.'%')
                            ->orWhereHas('customer', function ($customerQuery) {
                                $customerQuery->where('name', 'like', '%'.$this->search.'%')
                                    ->orWhere('email', 'like', '%'.$this->search.'%');
                            })
                            ->orWhereRaw("JSON_EXTRACT(metadata, '$.package_name') LIKE ?", ['%'.$this->search.'%']);
                    });
                })
                ->when($this->activeTab !== 'all', function ($query) {
                    $query->where('status', $this->activeTab);
                })
                ->when($this->sourceTab !== 'all', function ($query) {
                    match ($this->sourceTab) {
                        'platform' => $query->whereNotNull('platform_id'),
                        'agent_company' => $query->whereNull('platform_id')->where(function ($q) {
                            $q->whereNotIn('source', ['funnel', 'pos'])
                              ->orWhereNull('source');
                        }),
                        'funnel' => $query->where('source', 'funnel'),
                        'pos' => $query->where('source', 'pos'),
                        default => $query
                    };
                })
                ->when($this->productFilter, function ($query) {
                    if (str_starts_with($this->productFilter, 'package:')) {
                        $packageId = str_replace('package:', '', $this->productFilter);
                        $query->whereHas('items', function ($itemQuery) use ($packageId) {
                            $itemQuery->where('package_id', $packageId);
                        });
                    } else {
                        $query->whereHas('items', function ($itemQuery) {
                            $itemQuery->where('product_id', $this->productFilter);
                        });
                    }
                })
                ->when($this->dateFilter, function ($query) {
                    match ($this->dateFilter) {
                        'today' => $query->whereDate('created_at', today()),
                        'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                        'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                        'year' => $query->whereYear('created_at', now()->year),
                        default => $query
                    };
                })
                ->when($this->dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
                ->when($this->dateTo, fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
                ->orderBy($this->sortBy, $this->sortDirection)
                ->get();

            $handle = fopen('php://output', 'w');

            // CSV headers
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

            foreach ($orders as $order) {
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
            }

            fclose($handle);
        }, 'orders-export-'.now()->format('Y-m-d-His').'.csv');
    }

    public function getSourceCounts(): array
    {
        $counts = ProductOrder::visibleInAdmin()->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN platform_id IS NOT NULL THEN 1 ELSE 0 END) as platform,
            SUM(CASE WHEN source = 'funnel' THEN 1 ELSE 0 END) as funnel,
            SUM(CASE WHEN source = 'pos' THEN 1 ELSE 0 END) as pos,
            SUM(CASE WHEN platform_id IS NULL AND (source IS NULL OR source NOT IN ('funnel', 'pos')) THEN 1 ELSE 0 END) as agent_company
        ")->first();

        return [
            'all' => $counts->total ?? 0,
            'platform' => $counts->platform ?? 0,
            'agent_company' => $counts->agent_company ?? 0,
            'funnel' => $counts->funnel ?? 0,
            'pos' => $counts->pos ?? 0,
        ];
    }

    // Items modal
    public bool $showItemsModal = false;

    public ?int $selectedOrderId = null;

    public function openItemsModal(int $orderId): void
    {
        $this->selectedOrderId = $orderId;
        $this->showItemsModal = true;
    }

    public function getSelectedOrder(): ?ProductOrder
    {
        if (! $this->selectedOrderId) {
            return null;
        }

        return ProductOrder::with(['items.product', 'items.package', 'payments'])->find($this->selectedOrderId);
    }

    // Class Assignment Modal
    public bool $showClassAssignModal = false;
    public ?int $classAssignOrderId = null;
    public string $classAssignSearch = '';
    public array $classAssignSelectedIds = [];

    // Create student from modal
    public string $newStudentName = '';
    public string $newStudentPhone = '';

    public function openClassAssignModal(int $orderId): void
    {
        $this->classAssignOrderId = $orderId;
        $this->classAssignSearch = '';
        $this->classAssignSelectedIds = [];
        $this->newStudentName = '';
        $this->newStudentPhone = '';

        // Always pre-fill from order data so admin can see current values
        $order = ProductOrder::find($orderId);
        if ($order) {
            $this->newStudentName = $order->customer_name ?? '';
            $this->newStudentPhone = $order->customer_phone ?? '';
        }

        $this->showClassAssignModal = true;
    }

    public function getMatchingStudentsProperty()
    {
        $phone = trim($this->newStudentPhone);
        if (strlen($phone) < 5 || str_contains($phone, '*')) {
            return collect();
        }

        return \App\Models\Student::query()
            ->whereHas('user', fn ($q) => $q->where('phone', $phone))
            ->orWhere('phone', $phone)
            ->with('user')
            ->limit(5)
            ->get();
    }

    public function linkExistingStudent(int $studentId): void
    {
        $order = ProductOrder::find($this->classAssignOrderId);
        if (! $order) {
            return;
        }

        $student = \App\Models\Student::with('user')->find($studentId);
        if (! $student) {
            return;
        }

        $order->update(['student_id' => $student->id]);
        $this->newStudentName = '';
        $this->newStudentPhone = '';
        $this->dispatch('order-updated', message: "Student '{$student->user->name}' linked to order.");
    }

    public function createStudentForOrder(): void
    {
        $this->validate([
            'newStudentName' => 'required|string|min:2|max:255',
            'newStudentPhone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]+$/'],
        ], [
            'newStudentName.required' => 'Student name is required.',
            'newStudentName.min' => 'Student name must be at least 2 characters.',
            'newStudentPhone.required' => 'A valid phone number is required.',
            'newStudentPhone.regex' => 'Phone number must only contain digits. Remove any masked characters (*).',
        ]);

        $order = ProductOrder::find($this->classAssignOrderId);
        if (! $order) {
            return;
        }

        $phone = $this->newStudentPhone ?: null;

        // Create new user
        $baseEmail = $phone
            ? preg_replace('/[^0-9]/', '', $phone) . '@student.local'
            : \Illuminate\Support\Str::slug($this->newStudentName) . '-' . \Illuminate\Support\Str::random(4) . '@student.local';

        // Ensure unique email
        while (\App\Models\User::where('email', $baseEmail)->exists()) {
            $baseEmail = \Illuminate\Support\Str::slug($this->newStudentName) . '-' . \Illuminate\Support\Str::random(6) . '@student.local';
        }

        $user = \App\Models\User::create([
            'name' => $this->newStudentName,
            'email' => $baseEmail,
            'password' => bcrypt(\Illuminate\Support\Str::random(16)),
            'role' => 'student',
            'phone' => $phone,
        ]);

        $student = \App\Models\Student::create([
            'user_id' => $user->id,
            'phone' => $phone,
            'status' => 'active',
        ]);

        // Link student to order
        $order->update(['student_id' => $student->id]);

        $this->newStudentName = '';
        $this->newStudentPhone = '';
        $this->dispatch('order-updated', message: "Student '{$user->name}' created and linked to order.");
    }

    public function getClassAssignOrderProperty(): ?ProductOrder
    {
        if (! $this->classAssignOrderId) {
            return null;
        }

        return ProductOrder::with(['classAssignmentApprovals.class.course', 'classAssignmentApprovals.assignedByUser'])
            ->find($this->classAssignOrderId);
    }

    public function getClassAssignHasStudentProperty(): bool
    {
        if (! $this->classAssignOrderId) {
            return false;
        }

        $order = ProductOrder::find($this->classAssignOrderId);
        if (! $order) {
            return false;
        }

        return $this->resolveStudentForOrder($order) !== null;
    }

    public function getClassAssignAvailableProperty()
    {
        if (! $this->classAssignOrderId) {
            return collect();
        }

        $order = ProductOrder::find($this->classAssignOrderId);
        if (! $order) {
            return collect();
        }

        $alreadyAssignedClassIds = $order->classAssignmentApprovals()
            ->whereIn('status', ['pending', 'approved'])
            ->pluck('class_id')
            ->toArray();

        $query = \App\Models\ClassModel::query()
            ->where('status', 'active')
            ->whereNotIn('id', $alreadyAssignedClassIds)
            ->with('course');

        if ($this->classAssignSearch) {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->classAssignSearch}%")
                  ->orWhereHas('course', fn ($cq) => $cq->where('name', 'like', "%{$this->classAssignSearch}%"));
            });
        }

        return $query->get()->groupBy(fn ($class) => $class->course?->name ?? 'No Course');
    }

    public function toggleClassAssignSelection(int $classId): void
    {
        if (in_array($classId, $this->classAssignSelectedIds)) {
            $this->classAssignSelectedIds = array_values(array_diff($this->classAssignSelectedIds, [$classId]));
        } else {
            $this->classAssignSelectedIds[] = $classId;
        }
    }

    public function resolveStudentForOrder(ProductOrder $order): ?\App\Models\Student
    {
        if ($order->student_id) {
            return $order->student;
        }

        if ($order->customer_id) {
            return \App\Models\Student::where('user_id', $order->customer_id)->first();
        }

        return null;
    }

    public function submitClassAssignment(): void
    {
        if (empty($this->classAssignSelectedIds) || ! $this->classAssignOrderId) {
            return;
        }

        $order = ProductOrder::find($this->classAssignOrderId);
        if (! $order) {
            return;
        }

        $student = $this->resolveStudentForOrder($order);
        if (! $student) {
            session()->flash('error', 'No student could be found for this order.');
            return;
        }

        $count = count($this->classAssignSelectedIds);

        foreach ($this->classAssignSelectedIds as $classId) {
            \App\Models\ClassAssignmentApproval::firstOrCreate(
                [
                    'class_id' => $classId,
                    'student_id' => $student->id,
                    'product_order_id' => $order->id,
                ],
                [
                    'status' => 'pending',
                    'assigned_by' => auth()->id(),
                ]
            );
        }

        $this->classAssignSelectedIds = [];
        $this->dispatch('order-updated', message: "Assigned to {$count} class(es).");
    }

    public function removeClassAssignment(int $approvalId): void
    {
        $approval = \App\Models\ClassAssignmentApproval::find($approvalId);
        if ($approval && $approval->product_order_id === $this->classAssignOrderId) {
            $approval->delete();
        }
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Orders & Package Sales</flux:heading>
            <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Manage customer orders including product purchases and package sales</flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button variant="outline" wire:click="exportOrders" size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1.5" />
                    Export
                </div>
            </flux:button>
            <flux:button variant="primary" :href="route('admin.orders.create')" wire:navigate>
                <div class="flex items-center justify-center">
                    <flux:icon name="plus" class="w-4 h-4 mr-1.5" />
                    Create Order
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Action Needed Alert -->
    @php
        $actionStats = $this->getActionNeededStats();
        $totalActionNeeded = array_sum($actionStats);
    @endphp

    @if($totalActionNeeded > 0)
        <div class="mb-5 rounded-xl border border-amber-200 dark:border-amber-800/50 bg-amber-50/50 dark:bg-amber-900/10 px-4 py-3">
            <div class="flex items-center gap-4 flex-wrap">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <flux:icon name="exclamation-triangle" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                    </div>
                    <flux:text size="sm" class="font-semibold text-amber-800 dark:text-amber-300">Action Needed</flux:text>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    @if($actionStats['pending_confirmation'] > 0)
                        <button wire:click="$set('activeTab', 'pending')"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium transition-all
                                   bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:hover:bg-amber-900/50 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            {{ $actionStats['pending_confirmation'] }} Pending
                        </button>
                    @endif
                    @if($actionStats['unpaid_orders'] > 0)
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium
                                    bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                            {{ $actionStats['unpaid_orders'] }} Unpaid
                        </div>
                    @endif
                    @if($actionStats['processing'] > 0)
                        <button wire:click="$set('activeTab', 'processing')"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium transition-all
                                   bg-purple-100 text-purple-800 hover:bg-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:hover:bg-purple-900/50 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                            {{ $actionStats['processing'] }} Processing
                        </button>
                    @endif
                    @if($actionStats['ready_to_ship'] > 0)
                        <button wire:click="$set('activeTab', 'confirmed')"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium transition-all
                                   bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                            {{ $actionStats['ready_to_ship'] }} Ready to Ship
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Unified Navigation & Filters Card -->
    @php
        $sourceCounts = $this->getSourceCounts();
    @endphp
    <div class="mb-5 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <!-- Source Tabs - Compact pill style -->
        <div class="px-5 pt-4 pb-3">
            <div class="flex items-center gap-2 flex-wrap">
                <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500 font-medium mr-1">Source:</flux:text>
                @php
                    $sourceTabs = [
                        'all' => ['label' => 'All', 'icon' => 'squares-2x2', 'count' => $sourceCounts['all'], 'color' => 'zinc'],
                        'platform' => ['label' => 'Platform', 'icon' => 'globe-alt', 'count' => $sourceCounts['platform'], 'color' => 'purple'],
                        'agent_company' => ['label' => 'Agent & Co', 'icon' => 'building-office', 'count' => $sourceCounts['agent_company'], 'color' => 'blue'],
                        'funnel' => ['label' => 'Funnel', 'icon' => 'funnel', 'count' => $sourceCounts['funnel'], 'color' => 'green'],
                        'pos' => ['label' => 'POS', 'icon' => 'calculator', 'count' => $sourceCounts['pos'], 'color' => 'orange'],
                    ];
                @endphp
                @foreach($sourceTabs as $key => $tab)
                    @php
                        $sourceActiveStyles = match($tab['color']) {
                            'purple' => 'bg-purple-600 text-white dark:bg-purple-500 shadow-sm',
                            'blue' => 'bg-blue-600 text-white dark:bg-blue-500 shadow-sm',
                            'green' => 'bg-green-600 text-white dark:bg-green-500 shadow-sm',
                            'orange' => 'bg-orange-500 text-white dark:bg-orange-500 shadow-sm',
                            default => 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-sm',
                        };
                        $sourceInactiveStyles = match($tab['color']) {
                            'purple' => 'bg-purple-50 text-purple-700 hover:bg-purple-100 dark:bg-purple-900/20 dark:text-purple-400 dark:hover:bg-purple-900/30',
                            'blue' => 'bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:hover:bg-blue-900/30',
                            'green' => 'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-900/20 dark:text-green-400 dark:hover:bg-green-900/30',
                            'orange' => 'bg-orange-50 text-orange-700 hover:bg-orange-100 dark:bg-orange-900/20 dark:text-orange-400 dark:hover:bg-orange-900/30',
                            default => 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-600',
                        };
                        $sourceCountStyles = match($tab['color']) {
                            'purple' => $sourceTab === $key ? 'text-purple-200' : 'text-purple-400 dark:text-purple-500',
                            'blue' => $sourceTab === $key ? 'text-blue-200' : 'text-blue-400 dark:text-blue-500',
                            'green' => $sourceTab === $key ? 'text-green-200' : 'text-green-400 dark:text-green-500',
                            'orange' => $sourceTab === $key ? 'text-orange-200' : 'text-orange-400 dark:text-orange-500',
                            default => $sourceTab === $key ? 'text-zinc-300 dark:text-zinc-600' : 'text-zinc-400 dark:text-zinc-500',
                        };
                    @endphp
                    <button wire:click="$set('sourceTab', '{{ $key }}')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all
                               {{ $sourceTab === $key ? $sourceActiveStyles : $sourceInactiveStyles }}">
                        <flux:icon name="{{ $tab['icon'] }}" class="w-3.5 h-3.5" />
                        {{ $tab['label'] }}
                        <span class="tabular-nums {{ $sourceCountStyles }}">{{ $tab['count'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="border-t border-zinc-100 dark:border-zinc-700/50"></div>

        <!-- Status Tabs -->
        <div class="px-5">
            <nav class="flex gap-1 overflow-x-auto -mb-px" aria-label="Status Tabs">
                @php
                    $statusTabs = [
                        'all' => ['label' => 'All', 'color' => 'zinc'],
                        'pending' => ['label' => 'Pending', 'color' => 'amber'],
                        'confirmed' => ['label' => 'Confirmed', 'color' => 'blue'],
                        'processing' => ['label' => 'Processing', 'color' => 'purple'],
                        'shipped' => ['label' => 'Shipped', 'color' => 'cyan'],
                        'delivered' => ['label' => 'Delivered', 'color' => 'emerald'],
                        'cancelled' => ['label' => 'Cancelled', 'color' => 'red'],
                        'returned' => ['label' => 'Returned', 'color' => 'rose'],
                    ];
                @endphp
                @foreach($statusTabs as $key => $tab)
                    @php
                        $badgeColors = match($tab['color']) {
                            'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                            'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                            'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
                            'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
                            'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                            'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                            'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
                            default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400',
                        };
                        $activeBadgeColors = match($tab['color']) {
                            'amber' => 'bg-amber-500 text-white dark:bg-amber-500',
                            'blue' => 'bg-blue-500 text-white dark:bg-blue-500',
                            'purple' => 'bg-purple-500 text-white dark:bg-purple-500',
                            'cyan' => 'bg-cyan-500 text-white dark:bg-cyan-500',
                            'emerald' => 'bg-emerald-500 text-white dark:bg-emerald-500',
                            'red' => 'bg-red-500 text-white dark:bg-red-500',
                            'rose' => 'bg-rose-500 text-white dark:bg-rose-500',
                            default => 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900',
                        };
                        $activeTextColor = match($tab['color']) {
                            'amber' => 'text-amber-700 dark:text-amber-400',
                            'blue' => 'text-blue-700 dark:text-blue-400',
                            'purple' => 'text-purple-700 dark:text-purple-400',
                            'cyan' => 'text-cyan-700 dark:text-cyan-400',
                            'emerald' => 'text-emerald-700 dark:text-emerald-400',
                            'red' => 'text-red-700 dark:text-red-400',
                            'rose' => 'text-rose-700 dark:text-rose-400',
                            default => 'text-zinc-900 dark:text-white',
                        };
                        $activeUnderline = match($tab['color']) {
                            'amber' => 'bg-amber-500',
                            'blue' => 'bg-blue-500',
                            'purple' => 'bg-purple-500',
                            'cyan' => 'bg-cyan-500',
                            'emerald' => 'bg-emerald-500',
                            'red' => 'bg-red-500',
                            'rose' => 'bg-rose-500',
                            default => 'bg-zinc-900 dark:bg-white',
                        };
                    @endphp
                    <button wire:click="$set('activeTab', '{{ $key }}')"
                        class="relative py-3 px-3 text-sm font-medium transition-colors whitespace-nowrap
                               {{ $activeTab === $key
                                   ? $activeTextColor
                                   : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                        <span class="flex items-center gap-1.5">
                            {{ $tab['label'] }}
                            <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-xs font-semibold tabular-nums
                                {{ $activeTab === $key ? $activeBadgeColors : $badgeColors }}">
                                {{ $this->getStatusCount($key) }}
                            </span>
                        </span>
                        @if($activeTab === $key)
                            <span class="absolute bottom-0 inset-x-0 h-0.5 {{ $activeUnderline }} rounded-full"></span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>

        <div class="border-t border-zinc-200 dark:border-zinc-700"></div>

        <!-- Search & Filters -->
        <div class="p-4">
            <div class="flex flex-col md:flex-row gap-3">
                <div class="flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search orders, customers, emails..."
                        icon="magnifying-glass"
                    />
                </div>
                <div class="flex gap-3">
                    <div class="w-48">
                        <flux:select wire:model.live="productFilter" placeholder="All Products">
                            <option value="">All Products</option>
                            @foreach($this->getProductsAndPackages() as $item)
                                <option value="{{ $item['value'] }}">{{ $item['label'] }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="w-36">
                        <flux:select wire:model.live="dateFilter" placeholder="All Time">
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="year">This Year</option>
                        </flux:select>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <input type="date" wire:model.live="dateFrom"
                            class="w-36 px-2.5 py-1.5 text-sm border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                        <span class="text-zinc-400 text-xs">to</span>
                        <input type="date" wire:model.live="dateTo"
                            class="w-36 px-2.5 py-1.5 text-sm border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                    </div>
                    <flux:button variant="ghost" wire:click="$refresh" size="sm" class="shrink-0">
                        <flux:icon name="arrow-path" class="w-4 h-4" />
                    </flux:button>
                </div>
            </div>

            <!-- Active Filter Tags -->
            @if($search || $sourceTab !== 'all' || $productFilter || $dateFilter || $dateFrom || $dateTo)
                <div class="flex items-center gap-2 mt-3 flex-wrap">
                    <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500">Filters:</flux:text>
                    @if($search)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                            "{{ Str::limit($search, 20) }}"
                            <button wire:click="$set('search', '')" class="ml-0.5 text-zinc-400 hover:text-red-500 transition-colors">&times;</button>
                        </span>
                    @endif
                    @if($sourceTab !== 'all')
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                            {{ match($sourceTab) { 'platform' => 'Platform', 'agent_company' => 'Agent & Co', 'funnel' => 'Funnel', 'pos' => 'POS', default => $sourceTab } }}
                            <button wire:click="$set('sourceTab', 'all')" class="ml-0.5 text-zinc-400 hover:text-red-500 transition-colors">&times;</button>
                        </span>
                    @endif
                    @if($productFilter)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                            Product/Package
                            <button wire:click="$set('productFilter', '')" class="ml-0.5 text-zinc-400 hover:text-red-500 transition-colors">&times;</button>
                        </span>
                    @endif
                    @if($dateFilter)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                            {{ ucfirst($dateFilter) }}
                            <button wire:click="$set('dateFilter', '')" class="ml-0.5 text-zinc-400 hover:text-red-500 transition-colors">&times;</button>
                        </span>
                    @endif
                    @if($dateFrom || $dateTo)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                            {{ $dateFrom ?: '...' }} — {{ $dateTo ?: '...' }}
                            <button wire:click="$set('dateFrom', ''); $set('dateTo', '')" class="ml-0.5 text-zinc-400 hover:text-red-500 transition-colors">&times;</button>
                        </span>
                    @endif
                    <button wire:click="$set('search', ''); $set('sourceTab', 'all'); $set('productFilter', ''); $set('dateFilter', ''); $set('dateFrom', ''); $set('dateTo', '')"
                        class="text-xs text-zinc-400 hover:text-red-500 transition-colors font-medium">
                        Clear all
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">
                            <button wire:click="setSortBy('order_number')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors">
                                Order
                                @if($sortBy === 'order_number')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5" />
                                @endif
                            </button>
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">Source</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">
                            <button wire:click="setSortBy('customer_name')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors">
                                Customer
                                @if($sortBy === 'customer_name')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5" />
                                @endif
                            </button>
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">Phone</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">Items</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">
                            <button wire:click="setSortBy('total_amount')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors">
                                Total
                                @if($sortBy === 'total_amount')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5" />
                                @endif
                            </button>
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">
                            <button wire:click="setSortBy('status')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors">
                                Status
                                @if($sortBy === 'status')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5" />
                                @endif
                            </button>
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">Class</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">Payment</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">Notes</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">Tracking</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">
                            <button wire:click="setSortBy('created_at')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors">
                                Date
                                @if($sortBy === 'created_at')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5" />
                                @endif
                            </button>
                        </th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800"></th>
                    </tr>
                </thead>
                <tbody>
                    @php $orders = $this->getOrders(); @endphp
                    @forelse($orders as $order)
                        <tr class="border-b border-zinc-100 dark:border-zinc-700/50 hover:bg-zinc-50/70 dark:hover:bg-zinc-700/30 transition-colors" wire:key="order-{{ $order->id }}">
                            <!-- Order Number -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="block group">
                                    <div class="flex items-center gap-2">
                                        <flux:text class="font-semibold text-zinc-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $order->order_number }}</flux:text>
                                        @if($order->order_type === 'package')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">PKG</span>
                                        @endif
                                    </div>
                                    @if($order->order_type === 'package' && isset($order->metadata['package_name']))
                                        <flux:text size="sm" class="text-purple-600 dark:text-purple-400">{{ Str::limit($order->metadata['package_name'], 25) }}</flux:text>
                                    @elseif($order->customer_notes)
                                        <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500">{{ Str::limit($order->customer_notes, 25) }}</flux:text>
                                    @endif
                                </a>
                            </td>

                            <!-- Source -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @php
                                    $source = $this->getOrderSource($order);
                                @endphp
                                @if($order->platform_id && $order->platformAccount)
                                    <a href="{{ route('platforms.accounts.show', ['platform' => $order->platform, 'account' => $order->platformAccount]) }}?tab=orders" class="block group">
                                        <flux:badge size="sm" color="{{ $source['color'] }}" class="group-hover:opacity-80 transition-opacity">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="{{ $source['icon'] }}" class="w-3 h-3 mr-1" />
                                                {{ $source['label'] }}
                                            </div>
                                        </flux:badge>
                                        <flux:text size="xs" class="text-zinc-400 mt-0.5 group-hover:text-blue-600 transition-colors">{{ $order->platformAccount->name }}</flux:text>
                                    </a>
                                @elseif($order->agent_id && $order->agent)
                                    <a href="{{ route('agents.show', $order->agent) }}" class="block group">
                                        <flux:badge size="sm" color="{{ $source['color'] }}" class="group-hover:opacity-80 transition-opacity">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="{{ $source['icon'] }}" class="w-3 h-3 mr-1" />
                                                {{ $source['label'] }}
                                            </div>
                                        </flux:badge>
                                        <flux:text size="xs" class="text-zinc-400 mt-0.5 group-hover:text-blue-600 transition-colors">{{ $order->agent->name }}</flux:text>
                                    </a>
                                @elseif($order->source === 'funnel')
                                    <div>
                                        <flux:badge size="sm" color="{{ $source['color'] }}">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="{{ $source['icon'] }}" class="w-3 h-3 mr-1" />
                                                {{ $source['label'] }}
                                            </div>
                                        </flux:badge>
                                        @if($order->source_reference)
                                            <flux:text size="xs" class="text-zinc-400 mt-0.5">{{ $order->source_reference }}</flux:text>
                                        @endif
                                    </div>
                                @elseif($order->source === 'pos')
                                    <div>
                                        <flux:badge size="sm" color="{{ $source['color'] }}">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="{{ $source['icon'] }}" class="w-3 h-3 mr-1" />
                                                {{ $source['label'] }}
                                            </div>
                                        </flux:badge>
                                        @if($order->metadata['salesperson_name'] ?? null)
                                            <flux:text size="xs" class="text-zinc-400 mt-0.5">{{ $order->metadata['salesperson_name'] }}</flux:text>
                                        @endif
                                    </div>
                                @else
                                    <flux:badge size="sm" color="{{ $source['color'] }}">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="{{ $source['icon'] }}" class="w-3 h-3 mr-1" />
                                            {{ $source['label'] }}
                                        </div>
                                    </flux:badge>
                                @endif
                            </td>

                            <!-- Customer -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if($order->student)
                                    <a href="{{ route('students.show', $order->student) }}" class="block group">
                                        <flux:text class="font-medium text-zinc-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $order->getCustomerName() }}</flux:text>
                                        <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500">{{ $order->getCustomerEmail() }}</flux:text>
                                    </a>
                                @else
                                    <div>
                                        <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $order->getCustomerName() }}</flux:text>
                                        <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500">{{ $order->getCustomerEmail() }}</flux:text>
                                    </div>
                                @endif
                            </td>

                            <!-- Phone -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if($editingPhoneOrderId === $order->id)
                                    <div class="flex items-center gap-1">
                                        <input
                                            type="text"
                                            wire:model="editingPhoneValue"
                                            wire:keydown.enter="savePhone"
                                            wire:keydown.escape="cancelEditingPhone"
                                            class="w-32 px-2 py-1 text-sm border border-zinc-300 dark:border-zinc-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-700 dark:text-white"
                                            placeholder="Phone number"
                                            autofocus
                                        />
                                        <button wire:click="savePhone" class="p-1 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-md transition-colors">
                                            <flux:icon name="check" class="w-3.5 h-3.5" />
                                        </button>
                                        <button wire:click="cancelEditingPhone" class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors">
                                            <flux:icon name="x-mark" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                @else
                                    <button
                                        wire:click="startEditingPhone({{ $order->id }}, {{ json_encode($order->customer_phone ?? '') }})"
                                        class="group flex items-center gap-1 hover:text-blue-600 transition-colors"
                                        title="Click to edit"
                                    >
                                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">{{ $order->getCustomerPhone() }}</flux:text>
                                        <flux:icon name="pencil" class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity text-zinc-400" />
                                    </button>
                                @endif
                            </td>

                            <!-- Items -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <button
                                    wire:click="openItemsModal({{ $order->id }})"
                                    class="group text-left cursor-pointer"
                                    title="Click to view items"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-zinc-100 dark:bg-zinc-700 text-xs font-semibold text-zinc-600 dark:text-zinc-400 group-hover:bg-blue-100 group-hover:text-blue-600 dark:group-hover:bg-blue-900/30 dark:group-hover:text-blue-400 transition-colors">
                                            {{ $order->items->count() }}
                                        </span>
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                            item{{ $order->items->count() !== 1 ? 's' : '' }}
                                        </flux:text>
                                    </div>
                                </button>
                            </td>

                            <!-- Total -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <flux:text class="font-semibold text-zinc-900 dark:text-white tabular-nums">MYR {{ number_format($order->total_amount, 2) }}</flux:text>
                                @if($order->discount_amount > 0)
                                    <flux:text size="sm" class="text-emerald-600 dark:text-emerald-400 tabular-nums">-{{ number_format($order->discount_amount, 2) }}</flux:text>
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <flux:badge size="sm" color="{{ $this->getStatusColor($order->status) }}">
                                    {{ $this->getOrderStatuses()[$order->status] ?? $order->status }}
                                </flux:badge>
                            </td>

                            <!-- Class Assignment -->
                            <td class="px-5 py-3.5">
                                <button wire:click="openClassAssignModal({{ $order->id }})" class="group text-left w-full cursor-pointer">
                                    @if($order->classAssignmentApprovals->isNotEmpty())
                                        <div class="flex flex-col gap-1">
                                            @foreach($order->classAssignmentApprovals->take(2) as $assignment)
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ match($assignment->status) { 'approved' => 'bg-emerald-500', 'rejected' => 'bg-red-500', default => 'bg-amber-500' } }}"></span>
                                                    <span class="text-xs text-zinc-700 dark:text-zinc-300 group-hover:text-blue-600 dark:group-hover:text-blue-400 truncate max-w-[120px] transition-colors" title="{{ $assignment->class->title }}">
                                                        {{ Str::limit($assignment->class->title, 18) }}
                                                    </span>
                                                </div>
                                            @endforeach
                                            @if($order->classAssignmentApprovals->count() > 2)
                                                <span class="text-xs text-zinc-400 dark:text-zinc-500">+{{ $order->classAssignmentApprovals->count() - 2 }} more</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500 group-hover:text-blue-500 transition-colors">
                                            <flux:icon name="plus-circle" class="w-4 h-4 inline" /> Assign
                                        </span>
                                    @endif
                                </button>
                            </td>

                            <!-- Payment Status -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if($order->isPaid())
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Paid
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                        Unpaid
                                    </span>
                                @endif
                            </td>

                            <!-- Notes -->
                            <td class="px-5 py-3.5">
                                @php
                                    $notes = $order->internal_notes ?: $order->customer_notes;
                                @endphp
                                @if($notes)
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 max-w-[180px] truncate" title="{{ $notes }}">
                                        {{ Str::limit($notes, 35) }}
                                    </flux:text>
                                @else
                                    <span class="text-zinc-300 dark:text-zinc-600">&mdash;</span>
                                @endif
                            </td>

                            <!-- Tracking Number -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if($editingTrackingOrderId === $order->id)
                                    <div class="flex items-center gap-1">
                                        <input
                                            type="text"
                                            wire:model="editingTrackingValue"
                                            wire:keydown.enter="saveTracking"
                                            wire:keydown.escape="cancelEditingTracking"
                                            class="w-32 px-2 py-1 text-sm border border-zinc-300 dark:border-zinc-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-700 dark:text-white"
                                            placeholder="Tracking number"
                                            autofocus
                                        />
                                        <button wire:click="saveTracking" class="p-1 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-md transition-colors">
                                            <flux:icon name="check" class="w-3.5 h-3.5" />
                                        </button>
                                        <button wire:click="cancelEditingTracking" class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors">
                                            <flux:icon name="x-mark" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                @else
                                    <button
                                        wire:click="startEditingTracking({{ $order->id }}, {{ json_encode($order->tracking_id ?? '') }})"
                                        class="group flex items-center gap-1 hover:text-blue-600 transition-colors"
                                        title="Click to edit"
                                    >
                                        @if($order->tracking_id)
                                            <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400 font-mono">{{ $order->tracking_id }}</flux:text>
                                        @else
                                            <flux:text size="sm" class="text-zinc-300 dark:text-zinc-600 italic">Add tracking</flux:text>
                                        @endif
                                        <flux:icon name="pencil" class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity text-zinc-400" />
                                    </button>
                                @endif
                            </td>

                            <!-- Date -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <flux:text size="sm" class="text-zinc-700 dark:text-zinc-300">{{ $order->created_at->format('M j, Y') }}</flux:text>
                                <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500">{{ $order->created_at->format('g:i A') }}</flux:text>
                            </td>

                            <!-- Actions -->
                            <td class="px-5 py-3.5 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.orders.show', $order) }}" wire:navigate
                                       class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-600 hover:bg-zinc-100 dark:hover:text-zinc-300 dark:hover:bg-zinc-700 transition-colors">
                                        <flux:icon name="eye" class="w-4 h-4" />
                                    </a>

                                    @if($order->canBeCancelled() || $order->status === 'delivered')
                                        <flux:dropdown>
                                            <flux:button variant="ghost" size="sm">
                                                <flux:icon name="ellipsis-horizontal" class="w-4 h-4" />
                                            </flux:button>

                                            <flux:menu>
                                                @if($order->status === 'pending')
                                                    <flux:menu.item wire:click="updateOrderStatus({{ $order->id }}, 'confirmed')">
                                                        <flux:icon name="check" class="w-4 h-4 mr-2" />
                                                        Mark as Confirmed
                                                    </flux:menu.item>
                                                @endif

                                                @if(in_array($order->status, ['confirmed', 'pending']))
                                                    <flux:menu.item wire:click="updateOrderStatus({{ $order->id }}, 'processing')">
                                                        <flux:icon name="cog" class="w-4 h-4 mr-2" />
                                                        Mark as Processing
                                                    </flux:menu.item>
                                                @endif

                                                @if(in_array($order->status, ['processing', 'confirmed']))
                                                    <flux:menu.item wire:click="updateOrderStatus({{ $order->id }}, 'shipped')">
                                                        <flux:icon name="truck" class="w-4 h-4 mr-2" />
                                                        Mark as Shipped
                                                    </flux:menu.item>
                                                @endif

                                                @if($order->status === 'shipped')
                                                    <flux:menu.item wire:click="updateOrderStatus({{ $order->id }}, 'delivered')">
                                                        <flux:icon name="check-circle" class="w-4 h-4 mr-2" />
                                                        Mark as Delivered
                                                    </flux:menu.item>
                                                @endif

                                                @if($order->status === 'delivered')
                                                    <flux:menu.item wire:click="updateOrderStatus({{ $order->id }}, 'returned')">
                                                        <flux:icon name="arrow-uturn-left" class="w-4 h-4 mr-2" />
                                                        Mark as Returned
                                                    </flux:menu.item>
                                                @endif

                                                <flux:menu.separator />

                                                <flux:menu.item wire:click="updateOrderStatus({{ $order->id }}, 'cancelled')" class="text-red-600">
                                                    <flux:icon name="x-circle" class="w-4 h-4 mr-2" />
                                                    Cancel Order
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center mb-4">
                                        <flux:icon name="shopping-bag" class="w-7 h-7 text-zinc-400 dark:text-zinc-500" />
                                    </div>
                                    <flux:text class="font-medium text-zinc-600 dark:text-zinc-400">No orders found</flux:text>
                                    <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500 mt-1">Try adjusting your filters or search terms</flux:text>
                                    @if($search || $activeTab !== 'all' || $dateFilter || $dateFrom || $dateTo || $sourceTab !== 'all' || $productFilter)
                                        <flux:button variant="ghost" wire:click="$set('search', ''); $set('activeTab', 'all'); $set('dateFilter', ''); $set('dateFrom', ''); $set('dateTo', ''); $set('sourceTab', 'all'); $set('productFilter', '')" class="mt-3">
                                            Clear all filters
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($orders->hasPages())
            <div class="px-5 py-3.5 border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

    <!-- Items Modal -->
    <flux:modal wire:model.self="showItemsModal" class="md:w-2xl">
        @if($selectedOrderId && $selectedOrder = $this->getSelectedOrder())
            <div class="space-y-5">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">Order Items</flux:heading>
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 mt-1">{{ $selectedOrder->order_number }} &middot; {{ $selectedOrder->items->count() }} item{{ $selectedOrder->items->count() !== 1 ? 's' : '' }}</flux:text>
                    </div>
                    <flux:badge size="sm" color="{{ $selectedOrder->isPaid() ? 'green' : 'red' }}">{{ $selectedOrder->isPaid() ? 'Paid' : 'Unpaid' }}</flux:badge>
                </div>

                <!-- Items List -->
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-zinc-50 dark:bg-zinc-700/50 text-left">
                                <th class="px-4 py-2.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Product</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-center">Qty</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-right">Price</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                            @foreach($selectedOrder->items as $item)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-700/30 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="shrink-0 w-9 h-9 rounded-lg bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center">
                                                @if($item->isPackage())
                                                    <flux:icon name="cube" class="w-4 h-4 text-purple-500" />
                                                @else
                                                    <flux:icon name="shopping-bag" class="w-4 h-4 text-blue-500" />
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $item->display_name }}</flux:text>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    @if($item->sku)
                                                        <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500 font-mono">{{ $item->sku }}</flux:text>
                                                    @endif
                                                    @if($item->isPackage())
                                                        <flux:badge size="sm" color="purple">Package</flux:badge>
                                                    @endif
                                                    @if($item->variant_name)
                                                        <flux:badge size="sm" color="zinc">{{ $item->variant_name }}</flux:badge>
                                                    @endif
                                                </div>
                                                @if($item->warehouse)
                                                    <div class="flex items-center gap-1 mt-0.5">
                                                        <flux:icon name="building-storefront" class="w-3 h-3 text-zinc-400" />
                                                        <flux:text size="sm" class="text-zinc-400">{{ $item->warehouse->name }}</flux:text>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-zinc-100 dark:bg-zinc-700 text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ $item->quantity_ordered }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 tabular-nums">MYR {{ number_format($item->unit_price, 2) }}</flux:text>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <flux:text class="font-semibold tabular-nums">MYR {{ number_format($item->total_price, 2) }}</flux:text>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="space-y-2 px-1">
                    <div class="flex justify-between">
                        <flux:text size="sm" class="text-zinc-500">Subtotal</flux:text>
                        <flux:text size="sm" class="tabular-nums">MYR {{ number_format($selectedOrder->subtotal, 2) }}</flux:text>
                    </div>
                    @if($selectedOrder->shipping_cost > 0)
                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-zinc-500">Shipping</flux:text>
                            <flux:text size="sm" class="tabular-nums">MYR {{ number_format($selectedOrder->shipping_cost, 2) }}</flux:text>
                        </div>
                    @endif
                    @if($selectedOrder->total_discount > 0)
                        <div class="flex justify-between">
                            <flux:text size="sm" class="text-emerald-600">Discount</flux:text>
                            <flux:text size="sm" class="text-emerald-600 tabular-nums">-MYR {{ number_format($selectedOrder->total_discount, 2) }}</flux:text>
                        </div>
                    @endif
                    <div class="flex justify-between pt-2.5 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:text class="font-semibold">Total</flux:text>
                        <flux:text class="text-lg font-bold tabular-nums">MYR {{ number_format($selectedOrder->total_amount, 2) }}</flux:text>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Class Assignment Modal -->
    <flux:modal wire:model.self="showClassAssignModal" class="md:w-2xl">
        @php
            $classAssignOrder = $this->classAssignOrder;
        @endphp
        @if($classAssignOrder)
            <div class="space-y-5">
                <!-- Header -->
                <div>
                    <flux:heading size="lg">Class Assignment</flux:heading>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 mt-1">
                        {{ $classAssignOrder->order_number }} &middot; {{ $classAssignOrder->getCustomerName() }}
                    </flux:text>
                </div>

                <!-- Existing Assignments -->
                @if($classAssignOrder->classAssignmentApprovals->isNotEmpty())
                    <div>
                        <flux:text size="sm" class="font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Assigned Classes</flux:text>
                        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-100 dark:divide-zinc-700">
                            @foreach($classAssignOrder->classAssignmentApprovals as $approval)
                                <div class="flex items-center justify-between px-4 py-2.5">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-lg {{ match($approval->status) { 'approved' => 'bg-emerald-100 dark:bg-emerald-900/30', 'rejected' => 'bg-red-100 dark:bg-red-900/30', default => 'bg-amber-100 dark:bg-amber-900/30' } }} flex items-center justify-center shrink-0">
                                            <flux:icon name="{{ match($approval->status) { 'approved' => 'check-circle', 'rejected' => 'x-circle', default => 'clock' } }}"
                                                class="w-4 h-4 {{ match($approval->status) { 'approved' => 'text-emerald-600 dark:text-emerald-400', 'rejected' => 'text-red-600 dark:text-red-400', default => 'text-amber-600 dark:text-amber-400' } }}" />
                                        </div>
                                        <div class="min-w-0">
                                            <flux:text class="font-medium text-zinc-900 dark:text-white truncate">{{ $approval->class->title }}</flux:text>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                @if($approval->class->course)
                                                    <flux:text size="sm" class="text-zinc-400">{{ $approval->class->course->name }}</flux:text>
                                                @endif
                                                <flux:badge size="sm" color="{{ match($approval->status) { 'approved' => 'green', 'rejected' => 'red', default => 'yellow' } }}">
                                                    {{ ucfirst($approval->status) }}
                                                </flux:badge>
                                            </div>
                                        </div>
                                    </div>
                                    @if($approval->status === 'pending')
                                        <button wire:click="removeClassAssignment({{ $approval->id }})" class="p-1.5 rounded-lg text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Remove">
                                            <flux:icon name="x-mark" class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Assign New Classes -->
                <div>
                    <flux:text size="sm" class="font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Assign New Classes</flux:text>

                    @if(!$this->classAssignHasStudent)
                        <!-- No Student - Create Student Form -->
                        <div class="rounded-xl border border-amber-200 dark:border-amber-700/50 bg-amber-50 dark:bg-amber-900/20 p-4">
                            <div class="flex gap-3 mb-4">
                                <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center shrink-0">
                                    <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div>
                                    <flux:text size="sm" class="font-semibold text-amber-800 dark:text-amber-300">No student linked to this order</flux:text>
                                    <flux:text size="sm" class="text-amber-700 dark:text-amber-400 mt-1">
                                        Create a student account to link to this order and assign classes.
                                    </flux:text>
                                </div>
                            </div>

                            @php
                                $hasMaskedPhone = str_contains($newStudentPhone, '*');
                                $matchingStudents = $this->matchingStudents;
                            @endphp

                            <div class="space-y-3">
                                <div>
                                    <flux:input wire:model="newStudentName" label="Student Name" placeholder="Enter student name" size="sm" />
                                    @error('newStudentName') <flux:text size="sm" class="text-red-600 dark:text-red-400 mt-1">{{ $message }}</flux:text> @enderror
                                </div>

                                <div>
                                    <flux:input wire:model.live.debounce.500ms="newStudentPhone" label="Phone Number" placeholder="+60123456789" size="sm" />
                                    @if($hasMaskedPhone)
                                        <flux:text size="sm" class="text-red-600 dark:text-red-400 mt-1">Phone contains masked data (*). Enter the full phone number to search or create.</flux:text>
                                    @endif
                                    @error('newStudentPhone') <flux:text size="sm" class="text-red-600 dark:text-red-400 mt-1">{{ $message }}</flux:text> @enderror
                                </div>

                                {{-- Matching existing students by phone --}}
                                @if($matchingStudents->isNotEmpty())
                                    <div>
                                        <flux:text size="sm" class="font-semibold text-zinc-600 dark:text-zinc-400 mb-1.5">Existing student found — link to this order?</flux:text>
                                        <div class="rounded-xl border border-blue-200 dark:border-blue-800/50 divide-y divide-blue-100 dark:divide-blue-800/30 overflow-hidden">
                                            @foreach($matchingStudents as $match)
                                                <button wire:click="linkExistingStudent({{ $match->id }})" wire:key="match-student-{{ $match->id }}"
                                                    class="w-full flex items-center justify-between px-3 py-2.5 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors text-left">
                                                    <div class="flex items-center gap-2.5 min-w-0">
                                                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center shrink-0">
                                                            <flux:icon name="user" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                                        </div>
                                                        <div class="min-w-0">
                                                            <flux:text size="sm" class="font-medium text-zinc-900 dark:text-white">{{ $match->user->name ?? 'Unknown' }}</flux:text>
                                                            <flux:text size="sm" class="text-zinc-400">{{ $match->phone ?? $match->user->phone ?? 'No phone' }} &middot; {{ $match->student_id }}</flux:text>
                                                        </div>
                                                    </div>
                                                    <flux:icon name="link" class="w-4 h-4 text-blue-500 shrink-0" />
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="relative">
                                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-zinc-200 dark:border-zinc-700"></div></div>
                                        <div class="relative flex justify-center"><span class="bg-amber-50 dark:bg-amber-900/20 px-3 text-xs text-zinc-400 dark:text-zinc-500">or create new</span></div>
                                    </div>
                                @endif

                                <flux:button variant="primary" wire:click="createStudentForOrder" class="w-full" size="sm" :disabled="$hasMaskedPhone">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="user-plus" class="w-4 h-4 mr-1.5" />
                                        Create Student & Link to Order
                                    </div>
                                </flux:button>
                            </div>
                        </div>
                    @else
                        <!-- Search -->
                        <flux:input wire:model.live.debounce.300ms="classAssignSearch" placeholder="Search classes or courses..." size="sm" class="mb-3" />

                        <!-- Available Classes -->
                        @php
                            $availableClasses = $this->classAssignAvailable;
                        @endphp
                        @if($availableClasses->isNotEmpty())
                            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 max-h-60 overflow-y-auto">
                                @foreach($availableClasses as $courseName => $classes)
                                    <div>
                                        <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-700/50 sticky top-0">
                                            <flux:text size="sm" class="font-semibold text-zinc-500 dark:text-zinc-400">{{ $courseName }}</flux:text>
                                        </div>
                                        @foreach($classes as $class)
                                            <div wire:click="toggleClassAssignSelection({{ $class->id }})" wire:key="class-assign-{{ $class->id }}"
                                                class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors border-t border-zinc-100 dark:border-zinc-700">
                                                <div class="w-5 h-5 rounded border-2 flex items-center justify-center shrink-0
                                                    {{ in_array($class->id, $classAssignSelectedIds) ? 'bg-blue-500 border-blue-500' : 'border-zinc-300 dark:border-zinc-600' }}">
                                                    @if(in_array($class->id, $classAssignSelectedIds))
                                                        <flux:icon name="check" class="w-3 h-3 text-white" />
                                                    @endif
                                                </div>
                                                <div class="min-w-0">
                                                    <flux:text size="sm" class="font-medium text-zinc-900 dark:text-white">{{ $class->title }}</flux:text>
                                                    <flux:text size="sm" class="text-zinc-400">{{ $class->schedule ?? 'No schedule' }}</flux:text>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6 text-zinc-400 dark:text-zinc-500">
                                <flux:icon name="academic-cap" class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                <flux:text size="sm">{{ $classAssignSearch ? 'No classes found' : 'All classes already assigned' }}</flux:text>
                            </div>
                        @endif

                        <!-- Submit Button -->
                        @if(!empty($classAssignSelectedIds))
                            <div class="mt-3">
                                <flux:button variant="primary" wire:click="submitClassAssignment" class="w-full">
                                    Assign to {{ count($classAssignSelectedIds) }} Class{{ count($classAssignSelectedIds) !== 1 ? 'es' : '' }}
                                </flux:button>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Toast Notification -->
    <div
        x-data="{ show: false, message: '' }"
        x-on:order-updated.window="message = $event.detail.message; show = true; setTimeout(() => show = false, 3000)"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        class="fixed bottom-4 right-4 z-50"
        style="display: none;"
    >
        <div class="bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 px-5 py-3 rounded-xl shadow-lg flex items-center gap-3">
            <flux:icon name="check-circle" class="w-5 h-5 text-emerald-400 dark:text-emerald-600" />
            <span x-text="message" class="text-sm font-medium"></span>
        </div>
    </div>
</div>