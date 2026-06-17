<?php

use App\Jobs\ExportProductOrders;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\Storage;
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

    public string $paymentStatusFilter = 'all';

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
        $this->selectedOrderIds = [];
    }

    public function updatingActiveTab(): void
    {
        $this->resetPage();
        $this->selectedOrderIds = [];
    }

    public function updatingDateFilter(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
        $this->selectedOrderIds = [];
    }

    public function updatingDateFrom(): void
    {
        $this->dateFilter = '';
        $this->resetPage();
        $this->selectedOrderIds = [];
    }

    public function updatingDateTo(): void
    {
        $this->dateFilter = '';
        $this->resetPage();
        $this->selectedOrderIds = [];
    }

    public function updatingSourceTab(): void
    {
        $this->resetPage();
        $this->selectedOrderIds = [];
    }

    public function updatingProductFilter(): void
    {
        $this->resetPage();
        $this->selectedOrderIds = [];
    }

    public function updatingPaymentStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedOrderIds = [];
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
        $this->selectedOrderIds = [];
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
            ->when($this->paymentStatusFilter !== 'all', function ($query) {
                $query->where('payment_status', $this->paymentStatusFilter);
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

    public function getPaymentStatusColor(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'paid' => 'green',
            'pending' => 'yellow',
            'failed' => 'red',
            'refunded' => 'zinc',
            default => 'zinc',
        };
    }

    public function getPaymentStatusLabel(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'paid' => 'Paid',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            default => ucfirst($paymentStatus),
        };
    }

    /**
     * @return array{label: string, icon: string, classes: string, iconClasses: string}
     */
    public function getPaymentMethodMeta(?string $method): array
    {
        return match ($method) {
            'stripe' => [
                'label' => 'Stripe',
                'icon' => 'credit-card',
                'classes' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/10 dark:bg-indigo-900/20 dark:text-indigo-300 dark:ring-indigo-400/20',
                'iconClasses' => 'text-indigo-500 dark:text-indigo-400',
            ],
            'cash' => [
                'label' => 'Cash',
                'icon' => 'banknotes',
                'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/10 dark:bg-emerald-900/20 dark:text-emerald-300 dark:ring-emerald-400/20',
                'iconClasses' => 'text-emerald-500 dark:text-emerald-400',
            ],
            'cod' => [
                'label' => 'COD',
                'icon' => 'truck',
                'classes' => 'bg-amber-50 text-amber-700 ring-amber-600/10 dark:bg-amber-900/20 dark:text-amber-300 dark:ring-amber-400/20',
                'iconClasses' => 'text-amber-500 dark:text-amber-400',
            ],
            'bank_transfer' => [
                'label' => 'Bank Transfer',
                'icon' => 'building-library',
                'classes' => 'bg-blue-50 text-blue-700 ring-blue-600/10 dark:bg-blue-900/20 dark:text-blue-300 dark:ring-blue-400/20',
                'iconClasses' => 'text-blue-500 dark:text-blue-400',
            ],
            'manual' => [
                'label' => 'Manual',
                'icon' => 'pencil-square',
                'classes' => 'bg-zinc-100 text-zinc-700 ring-zinc-600/10 dark:bg-zinc-700/40 dark:text-zinc-300 dark:ring-zinc-400/20',
                'iconClasses' => 'text-zinc-500 dark:text-zinc-400',
            ],
            default => [
                'label' => ucfirst(str_replace('_', ' ', (string) $method)),
                'icon' => 'wallet',
                'classes' => 'bg-zinc-100 text-zinc-700 ring-zinc-600/10 dark:bg-zinc-700/40 dark:text-zinc-300 dark:ring-zinc-400/20',
                'iconClasses' => 'text-zinc-500 dark:text-zinc-400',
            ],
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

    public string $exportStatus = '';

    public string $exportFilename = '';

    public function exportOrders(): void
    {
        $filename = 'orders-export-'.now()->format('Y-m-d-His').'.csv';

        ExportProductOrders::dispatch(
            userId: auth()->id(),
            filename: $filename,
            filters: [
                'search' => $this->search,
                'activeTab' => $this->activeTab,
                'sourceTab' => $this->sourceTab,
                'productFilter' => $this->productFilter,
                'paymentStatusFilter' => $this->paymentStatusFilter,
                'dateFilter' => $this->dateFilter,
                'dateFrom' => $this->dateFrom,
                'dateTo' => $this->dateTo,
                'sortBy' => $this->sortBy,
                'sortDirection' => $this->sortDirection,
            ],
        );

        $this->exportStatus = 'processing';
        $this->exportFilename = $filename;

        $this->dispatch('order-updated', message: 'Export started! The file will be ready shortly. Click "Download Export" when available.');
    }

    public function checkExportReady(): void
    {
        if ($this->exportFilename && Storage::disk('local')->exists('exports/'.$this->exportFilename)) {
            $this->exportStatus = 'ready';
        }
    }

    public function downloadExport()
    {
        if ($this->exportFilename && Storage::disk('local')->exists('exports/'.$this->exportFilename)) {
            $this->exportStatus = '';
            $path = Storage::disk('local')->path('exports/'.$this->exportFilename);

            return response()->download($path, $this->exportFilename)->deleteFileAfterSend(true);
        }

        $this->dispatch('order-updated', message: 'Export file not ready yet. Please wait a moment and try again.');
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

    // Order quick-view modal
    public bool $showOrderModal = false;

    public function openOrderModal(int $orderId): void
    {
        $this->selectedOrderId = $orderId;
        $this->showOrderModal = true;
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

    // Bulk selection (page-scoped)
    public array $selectedOrderIds = [];

    public bool $classAssignBulkMode = false;

    public array $classAssignBulkOrderIds = [];

    // Bulk student creation confirmation (Step 2)
    public bool $bulkConfirmStudents = false;

    public array $bulkStudentPlans = []; // ['ready' => [...], 'creatable' => [...], 'skipped' => [...]]

    // Create student from modal
    public string $newStudentName = '';

    public string $newStudentPhone = '';

    public function openClassAssignModal(int $orderId): void
    {
        $this->classAssignBulkMode = false;
        $this->classAssignBulkOrderIds = [];
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

    public function openBulkClassAssignModal(): void
    {
        if (empty($this->selectedOrderIds)) {
            return;
        }

        $this->classAssignBulkMode = true;
        $this->classAssignBulkOrderIds = array_values(array_unique(array_map('intval', $this->selectedOrderIds)));
        $this->classAssignOrderId = null;
        $this->classAssignSearch = '';
        $this->classAssignSelectedIds = [];
        $this->newStudentName = '';
        $this->newStudentPhone = '';
        $this->bulkConfirmStudents = false;
        $this->bulkStudentPlans = [];
        $this->showClassAssignModal = true;
    }

    public function backToClassPicker(): void
    {
        $this->bulkConfirmStudents = false;
        $this->bulkStudentPlans = [];
    }

    public function clearOrderSelection(): void
    {
        $this->selectedOrderIds = [];
    }

    public function toggleSelectAllOnPage(): void
    {
        $visibleIds = $this->getOrders()->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        $current = array_map('intval', $this->selectedOrderIds);
        $allSelected = ! empty($visibleIds) && empty(array_diff($visibleIds, $current));

        if ($allSelected) {
            $this->selectedOrderIds = array_values(array_diff($current, $visibleIds));
        } else {
            $this->selectedOrderIds = array_values(array_unique(array_merge($current, $visibleIds)));
        }
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

        // Check if a user with this phone already exists
        if ($phone) {
            $existingUser = \App\Models\User::where('phone', $phone)->first();
            if ($existingUser) {
                $this->addError('newStudentPhone', 'A user with this phone number already exists. Please use the matching student above to link them instead.');

                return;
            }

            $existingStudent = \App\Models\Student::where('phone', $phone)->first();
            if ($existingStudent) {
                $this->addError('newStudentPhone', 'A student with this phone number already exists. Please use the matching student above to link them instead.');

                return;
            }
        }

        // Create new user
        $baseEmail = $phone
            ? preg_replace('/[^0-9]/', '', $phone).'@student.local'
            : \Illuminate\Support\Str::slug($this->newStudentName).'-'.\Illuminate\Support\Str::random(4).'@student.local';

        // Ensure unique email
        while (\App\Models\User::where('email', $baseEmail)->exists()) {
            $baseEmail = \Illuminate\Support\Str::slug($this->newStudentName).'-'.\Illuminate\Support\Str::random(6).'@student.local';
        }

        try {
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
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'phone')) {
                $this->addError('newStudentPhone', 'This phone number is already registered. Please link the existing student instead.');
            } elseif (str_contains($e->getMessage(), 'email')) {
                $this->addError('newStudentPhone', 'A user with this email already exists. Please try again.');
            } else {
                $this->addError('newStudentPhone', 'A duplicate record was found. Please link the existing student instead.');
            }
        }
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
        $orderIds = $this->classAssignBulkMode
            ? $this->classAssignBulkOrderIds
            : ($this->classAssignOrderId ? [$this->classAssignOrderId] : []);

        if (empty($orderIds)) {
            return collect();
        }

        // Hide classes already assigned to EVERY selected order (intersection).
        // For single-row mode this matches the previous behaviour exactly.
        $assignmentsPerOrder = \App\Models\ClassAssignmentApproval::query()
            ->whereIn('product_order_id', $orderIds)
            ->whereIn('status', ['pending', 'approved'])
            ->get(['product_order_id', 'class_id'])
            ->groupBy('product_order_id');

        $alreadyAssignedClassIds = [];
        if ($assignmentsPerOrder->count() === count($orderIds)) {
            $perOrderClassIds = $assignmentsPerOrder->map(
                fn ($rows) => $rows->pluck('class_id')->unique()->values()->all()
            )->values()->all();
            $alreadyAssignedClassIds = array_values(array_intersect(...$perOrderClassIds));
        }

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

    /**
     * Build a plan for each bulk-selected order describing what student
     * action (if any) is needed before classes can be assigned.
     *
     * @param  array<int>  $orderIds
     * @return array{ready: array<int, array{order: ProductOrder, student: \App\Models\Student}>, creatable: array<int, array<string, mixed>>, skipped: array<int, array{order: ProductOrder, reason: string}>}
     */
    public function prepareBulkStudentPlans(array $orderIds): array
    {
        $orders = ProductOrder::whereIn('id', $orderIds)->get();
        $ready = [];
        $creatable = [];
        $skipped = [];

        foreach ($orders as $order) {
            $student = $this->resolveStudentForOrder($order);
            if ($student) {
                $ready[] = ['order' => $order, 'student' => $student];

                continue;
            }

            $name = trim((string) $order->customer_name);
            $phone = trim((string) $order->customer_phone);

            if (strlen($name) < 2) {
                $skipped[] = ['order' => $order, 'reason' => 'Missing customer name on order.'];

                continue;
            }
            if ($phone === '' || str_contains($phone, '*') || ! preg_match('/^\+?[0-9]+$/', $phone)) {
                $skipped[] = ['order' => $order, 'reason' => 'Customer phone is missing, masked, or invalid.'];

                continue;
            }

            $existingStudent = \App\Models\Student::query()
                ->where(function ($q) use ($phone) {
                    $q->where('phone', $phone)
                        ->orWhereHas('user', fn ($uq) => $uq->where('phone', $phone));
                })
                ->with('user')
                ->first();

            if ($existingStudent) {
                $creatable[] = [
                    'order' => $order,
                    'name' => $name,
                    'phone' => $phone,
                    'action' => 'link_student',
                    'student_id' => $existingStudent->id,
                    'user_id' => $existingStudent->user_id,
                    'matched_name' => $existingStudent->user?->name ?? $name,
                ];

                continue;
            }

            $existingUser = \App\Models\User::where('phone', $phone)->first();
            if ($existingUser) {
                $creatable[] = [
                    'order' => $order,
                    'name' => $name,
                    'phone' => $phone,
                    'action' => 'link_user',
                    'student_id' => null,
                    'user_id' => $existingUser->id,
                    'matched_name' => $existingUser->name,
                ];

                continue;
            }

            $creatable[] = [
                'order' => $order,
                'name' => $name,
                'phone' => $phone,
                'action' => 'create',
                'student_id' => null,
                'user_id' => null,
                'matched_name' => null,
            ];
        }

        return ['ready' => $ready, 'creatable' => $creatable, 'skipped' => $skipped];
    }

    /**
     * Execute a plan item, returning the resolved Student (or null on failure).
     *
     * @param  array<string, mixed>  $item
     */
    private function executeBulkStudentPlan(array $item): ?\App\Models\Student
    {
        /** @var ProductOrder $order */
        $order = $item['order'];
        $action = $item['action'];

        if ($action === 'link_student') {
            $student = \App\Models\Student::find($item['student_id']);
            if (! $student) {
                return null;
            }
            $order->update(['student_id' => $student->id]);

            return $student;
        }

        if ($action === 'link_user') {
            $student = \App\Models\Student::firstOrCreate(
                ['user_id' => $item['user_id']],
                ['phone' => $item['phone'], 'status' => 'active']
            );
            $order->update(['student_id' => $student->id]);

            return $student;
        }

        // action === 'create'
        $name = $item['name'];
        $phone = $item['phone'];

        $baseEmail = preg_replace('/[^0-9]/', '', $phone).'@student.local';
        while (\App\Models\User::where('email', $baseEmail)->exists()) {
            $baseEmail = \Illuminate\Support\Str::slug($name).'-'.\Illuminate\Support\Str::random(6).'@student.local';
        }

        try {
            $user = \App\Models\User::create([
                'name' => $name,
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

            $order->update(['student_id' => $student->id]);

            return $student;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Race: someone created a matching user/student between plan and execute.
            // Retry by re-resolving.
            $existing = \App\Models\Student::query()
                ->where(function ($q) use ($phone) {
                    $q->where('phone', $phone)
                        ->orWhereHas('user', fn ($uq) => $uq->where('phone', $phone));
                })
                ->first();

            if ($existing) {
                $order->update(['student_id' => $existing->id]);

                return $existing;
            }

            return null;
        }
    }

    public function submitClassAssignment(): void
    {
        if (empty($this->classAssignSelectedIds)) {
            return;
        }

        $orderIds = $this->classAssignBulkMode
            ? $this->classAssignBulkOrderIds
            : ($this->classAssignOrderId ? [$this->classAssignOrderId] : []);

        if (empty($orderIds)) {
            return;
        }

        $classCount = count($this->classAssignSelectedIds);

        // Bulk mode: gate on Step 2 confirmation when some orders need student creation.
        if ($this->classAssignBulkMode) {
            $plans = $this->prepareBulkStudentPlans($orderIds);

            if (! empty($plans['creatable']) && ! $this->bulkConfirmStudents) {
                $this->bulkStudentPlans = $plans;
                $this->bulkConfirmStudents = true;

                return;
            }

            $createdCount = 0;
            $linkedCount = 0;
            $assignedOrderCount = 0;
            $assignTargets = [];

            // Existing-student orders go straight through.
            foreach ($plans['ready'] as $row) {
                $assignTargets[] = ['order' => $row['order'], 'student' => $row['student']];
            }

            // Execute creatable plans (only if user confirmed Step 2; otherwise this list is empty above).
            foreach ($plans['creatable'] as $item) {
                $student = $this->executeBulkStudentPlan($item);
                if (! $student) {
                    $plans['skipped'][] = ['order' => $item['order'], 'reason' => 'Failed to create or link student.'];

                    continue;
                }

                if ($item['action'] === 'create') {
                    $createdCount++;
                } else {
                    $linkedCount++;
                }
                $assignTargets[] = ['order' => $item['order'], 'student' => $student];
            }

            foreach ($assignTargets as $target) {
                foreach ($this->classAssignSelectedIds as $classId) {
                    \App\Models\ClassAssignmentApproval::firstOrCreate(
                        [
                            'class_id' => $classId,
                            'student_id' => $target['student']->id,
                            'product_order_id' => $target['order']->id,
                        ],
                        [
                            'status' => 'pending',
                            'assigned_by' => auth()->id(),
                        ]
                    );
                }
                $assignedOrderCount++;
            }

            $skippedOrderCount = count($plans['skipped']);

            $messageParts = [];
            if ($createdCount > 0) {
                $messageParts[] = "Created {$createdCount} student(s)";
            }
            if ($linkedCount > 0) {
                $messageParts[] = "linked {$linkedCount}";
            }
            $messageParts[] = "assigned {$assignedOrderCount} order(s) to {$classCount} class(es)";
            $message = ucfirst(implode(', ', $messageParts)).'.';
            if ($skippedOrderCount > 0) {
                $message .= " Skipped {$skippedOrderCount} (no name/phone).";
            }

            $this->selectedOrderIds = [];
            $this->classAssignBulkMode = false;
            $this->classAssignBulkOrderIds = [];
            $this->bulkConfirmStudents = false;
            $this->bulkStudentPlans = [];
            $this->classAssignSelectedIds = [];
            $this->showClassAssignModal = false;

            $this->dispatch('order-updated', message: $message);

            return;
        }

        // Single-row mode: original behaviour.
        $orders = ProductOrder::whereIn('id', $orderIds)->get();
        $assignedOrderCount = 0;

        foreach ($orders as $order) {
            $student = $this->resolveStudentForOrder($order);
            if (! $student) {
                continue;
            }

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
            $assignedOrderCount++;
        }

        if ($assignedOrderCount === 0) {
            session()->flash('error', 'No student could be found for this order.');

            return;
        }

        $this->classAssignSelectedIds = [];
        $this->dispatch('order-updated', message: "Assigned to {$classCount} class(es).");
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
            @if($exportStatus === 'processing')
                <flux:button variant="outline" wire:click="checkExportReady" wire:poll.5s="checkExportReady" size="sm">
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-path" class="w-4 h-4 mr-1.5 animate-spin" />
                        Preparing...
                    </div>
                </flux:button>
            @elseif($exportStatus === 'ready')
                <flux:button variant="primary" wire:click="downloadExport" size="sm">
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1.5" />
                        Download Export
                    </div>
                </flux:button>
            @else
                <flux:button variant="outline" wire:click="exportOrders" size="sm">
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1.5" />
                        Export
                    </div>
                </flux:button>
            @endif
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
                    <div class="w-40">
                        <flux:select wire:model.live="paymentStatusFilter" placeholder="All Payments">
                            <option value="all">All Payments</option>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
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
            @if($search || $sourceTab !== 'all' || $productFilter || $paymentStatusFilter !== 'all' || $dateFilter || $dateFrom || $dateTo)
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
                    @if($paymentStatusFilter !== 'all')
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                            Payment: {{ $this->getPaymentStatusLabel($paymentStatusFilter) }}
                            <button wire:click="$set('paymentStatusFilter', 'all')" class="ml-0.5 text-zinc-400 hover:text-red-500 transition-colors">&times;</button>
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
                    <button wire:click="$set('search', ''); $set('sourceTab', 'all'); $set('productFilter', ''); $set('paymentStatusFilter', 'all'); $set('dateFilter', ''); $set('dateFrom', ''); $set('dateTo', '')"
                        class="text-xs text-zinc-400 hover:text-red-500 transition-colors font-medium">
                        Clear all
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Bulk Action Bar -->
    @if(count($selectedOrderIds) > 0)
        <div class="mb-3 flex items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-800 dark:bg-blue-900/20">
            <div class="flex items-center gap-2 text-sm text-blue-900 dark:text-blue-100">
                <flux:icon name="check-circle" class="w-4 h-4" />
                <span class="font-medium">{{ count($selectedOrderIds) }} order{{ count($selectedOrderIds) === 1 ? '' : 's' }} selected</span>
            </div>
            <div class="flex items-center gap-2">
                <flux:button size="sm" variant="ghost" wire:click="clearOrderSelection">Clear selection</flux:button>
                <flux:button size="sm" variant="primary" wire:click="openBulkClassAssignModal">
                    <flux:icon name="academic-cap" class="w-4 h-4 mr-1.5" />
                    Assign to Class
                </flux:button>
            </div>
        </div>
    @endif

    <!-- Orders Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead>
                    @php
                        $visibleOrderIds = $this->getOrders()->pluck('id')->map(fn ($id) => (int) $id)->toArray();
                        $selectedOnPageCount = count(array_intersect($visibleOrderIds, array_map('intval', $selectedOrderIds)));
                        $allOnPageSelected = ! empty($visibleOrderIds) && $selectedOnPageCount === count($visibleOrderIds);
                    @endphp
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-3 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 bg-zinc-50/50 dark:bg-zinc-800 w-10">
                            <input
                                type="checkbox"
                                aria-label="Select all on this page"
                                wire:click="toggleSelectAllOnPage"
                                @checked($allOnPageSelected)
                                class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500 cursor-pointer"
                            />
                        </th>
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
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider bg-zinc-50/50 dark:bg-zinc-800">Method</th>
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
                        <tr class="border-b border-zinc-100 dark:border-zinc-700/50 hover:bg-zinc-50/70 dark:hover:bg-zinc-700/30 transition-colors {{ in_array((int) $order->id, array_map('intval', $selectedOrderIds), true) ? 'bg-blue-50/40 dark:bg-blue-900/10' : '' }}" wire:key="order-{{ $order->id }}">
                            <!-- Bulk select checkbox -->
                            <td class="px-3 py-3.5 align-middle">
                                <input
                                    type="checkbox"
                                    aria-label="Select order {{ $order->order_number }}"
                                    wire:model.live="selectedOrderIds"
                                    value="{{ $order->id }}"
                                    class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                />
                            </td>

                            <!-- Order Number -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <button type="button" wire:click="openOrderModal({{ $order->id }})" class="block text-left group cursor-pointer">
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
                                </button>
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
                                    <div class="flex flex-col gap-1.5">
                                        <button
                                            wire:click="startEditingPhone({{ $order->id }}, {{ json_encode($order->customer_phone ?? '') }})"
                                            class="group flex items-center gap-1 hover:text-blue-600 transition-colors"
                                            title="Click to edit"
                                        >
                                            <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">{{ $order->getCustomerPhone() }}</flux:text>
                                            <flux:icon name="pencil" class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity text-zinc-400" />
                                        </button>
                                        @if($whatsAppUrl = $order->getWhatsAppUrl())
                                            <a
                                                href="{{ $whatsAppUrl }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label="Message customer on WhatsApp"
                                                title="Message on WhatsApp"
                                                class="inline-flex w-fit items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:hover:bg-emerald-900/40 transition-colors"
                                            >
                                                <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="currentColor" aria-hidden="true">
                                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413"/>
                                                </svg>
                                                WhatsApp
                                            </a>
                                        @endif
                                    </div>
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
                                <flux:badge size="sm" color="{{ $this->getPaymentStatusColor($order->payment_status) }}">
                                    {{ $this->getPaymentStatusLabel($order->payment_status) }}
                                </flux:badge>
                            </td>

                            <!-- Payment Method -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if($order->payment_method)
                                    @php
                                        $methodMeta = $this->getPaymentMethodMeta($order->payment_method);
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $methodMeta['classes'] }}" title="{{ $methodMeta['label'] }}">
                                        <flux:icon name="{{ $methodMeta['icon'] }}" class="w-3.5 h-3.5 {{ $methodMeta['iconClasses'] }}" />
                                        {{ $methodMeta['label'] }}
                                    </span>
                                @else
                                    <span class="text-zinc-300 dark:text-zinc-600">&mdash;</span>
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

                                    <a href="{{ route('admin.orders.receipt-pdf', $order) }}" target="_blank"
                                       title="Download receipt PDF"
                                       class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-600 hover:bg-zinc-100 dark:hover:text-zinc-300 dark:hover:bg-zinc-700 transition-colors">
                                        <flux:icon name="arrow-down-tray" class="w-4 h-4" />
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
                            <td colspan="14" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center mb-4">
                                        <flux:icon name="shopping-bag" class="w-7 h-7 text-zinc-400 dark:text-zinc-500" />
                                    </div>
                                    <flux:text class="font-medium text-zinc-600 dark:text-zinc-400">No orders found</flux:text>
                                    <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500 mt-1">Try adjusting your filters or search terms</flux:text>
                                    @if($search || $activeTab !== 'all' || $dateFilter || $dateFrom || $dateTo || $sourceTab !== 'all' || $productFilter || $paymentStatusFilter !== 'all')
                                        <flux:button variant="ghost" wire:click="$set('search', ''); $set('activeTab', 'all'); $set('dateFilter', ''); $set('dateFrom', ''); $set('dateTo', ''); $set('sourceTab', 'all'); $set('productFilter', ''); $set('paymentStatusFilter', 'all')" class="mt-3">
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

    <!-- Order Quick-View Modal -->
    <flux:modal wire:model.self="showOrderModal" class="md:w-2xl">
        @if($showOrderModal && $selectedOrderId && $quickOrder = $this->getSelectedOrder())
            <div class="space-y-5">
                <!-- Header -->
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ $quickOrder->order_number }}</flux:heading>
                            @if($quickOrder->order_type === 'package')
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">PKG</span>
                            @endif
                        </div>
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 mt-1">{{ $quickOrder->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                    <div class="flex flex-col items-end gap-1.5">
                        <flux:badge size="sm" color="{{ $this->getStatusColor($quickOrder->status) }}">{{ $this->getOrderStatuses()[$quickOrder->status] ?? $quickOrder->status }}</flux:badge>
                        <flux:badge size="sm" color="{{ $this->getPaymentStatusColor($quickOrder->payment_status) }}">{{ $this->getPaymentStatusLabel($quickOrder->payment_status) }}</flux:badge>
                    </div>
                </div>

                <!-- Customer -->
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <flux:text class="font-medium text-zinc-900 dark:text-white">{{ $quickOrder->getCustomerName() }}</flux:text>
                            <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500">{{ $quickOrder->getCustomerEmail() }}</flux:text>
                        </div>
                        @if($quickOrder->student)
                            <flux:button :href="route('students.show', $quickOrder->student)" wire:navigate variant="ghost" size="sm">View student</flux:button>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">{{ $quickOrder->getCustomerPhone() }}</flux:text>
                        @if($quickWhatsAppUrl = $quickOrder->getWhatsAppUrl())
                            <a href="{{ $quickWhatsAppUrl }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:hover:bg-emerald-900/40 transition-colors">
                                <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="currentColor" aria-hidden="true">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413"/>
                                </svg>
                                WhatsApp
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Items -->
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-100 dark:divide-zinc-700">
                    @foreach($quickOrder->items as $item)
                        <div class="flex items-center justify-between gap-3 px-4 py-3" wire:key="quick-item-{{ $item->id }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-zinc-100 dark:bg-zinc-700 text-xs font-semibold text-zinc-600 dark:text-zinc-400 shrink-0">{{ $item->quantity_ordered }}</span>
                                <flux:text class="font-medium text-zinc-900 dark:text-white truncate">{{ $item->display_name }}</flux:text>
                            </div>
                            <flux:text class="font-semibold tabular-nums shrink-0">MYR {{ number_format($item->total_price, 2) }}</flux:text>
                        </div>
                    @endforeach
                </div>

                <!-- Total -->
                <div class="flex justify-between items-center px-1">
                    <flux:text class="font-semibold">Total</flux:text>
                    <flux:text class="text-lg font-bold tabular-nums">MYR {{ number_format($quickOrder->total_amount, 2) }}</flux:text>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-2 pt-1">
                    <flux:button variant="ghost" wire:click="$set('showOrderModal', false)">Close</flux:button>
                    <flux:button :href="route('admin.orders.show', $quickOrder)" wire:navigate variant="primary">View full order</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Class Assignment Modal -->
    <flux:modal wire:model.self="showClassAssignModal" class="md:w-2xl">
        @php
            $classAssignOrder = $this->classAssignOrder;
            $bulkOrderCount = count($classAssignBulkOrderIds);
        @endphp
        @if($classAssignBulkMode && $bulkConfirmStudents)
            @php
                $plansCreatable = $bulkStudentPlans['creatable'] ?? [];
                $plansSkipped = $bulkStudentPlans['skipped'] ?? [];
                $plansReadyCount = count($bulkStudentPlans['ready'] ?? []);
                $createCount = count(array_filter($plansCreatable, fn ($p) => $p['action'] === 'create'));
                $linkCount = count($plansCreatable) - $createCount;
            @endphp
            <div class="space-y-5">
                <!-- Header -->
                <div>
                    <flux:heading size="lg">Confirm student records</flux:heading>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 mt-1">
                        {{ count($plansCreatable) }} of {{ $bulkOrderCount }} selected order{{ $bulkOrderCount === 1 ? '' : 's' }} need a student record. Review and confirm to create / link them and proceed with the class assignment.
                    </flux:text>
                </div>

                <!-- Summary chips -->
                <div class="flex flex-wrap gap-2 text-xs font-medium">
                    @if($createCount > 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Will create {{ $createCount }}
                        </span>
                    @endif
                    @if($linkCount > 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                            Will link {{ $linkCount }}
                        </span>
                    @endif
                    @if($plansReadyCount > 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-1 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
                            Already linked {{ $plansReadyCount }}
                        </span>
                    @endif
                    @if(count($plansSkipped) > 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                            <flux:icon name="exclamation-triangle" class="w-3 h-3" />
                            Skipping {{ count($plansSkipped) }}
                        </span>
                    @endif
                </div>

                <!-- Creatable list -->
                @if(!empty($plansCreatable))
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 max-h-72 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-700">
                        @foreach($plansCreatable as $plan)
                            <div class="flex items-start justify-between gap-3 px-4 py-3" wire:key="bulk-plan-{{ $plan['order']->id }}">
                                <div class="min-w-0 flex-1">
                                    <flux:text size="sm" class="font-mono text-zinc-500 dark:text-zinc-400">{{ $plan['order']->order_number }}</flux:text>
                                    <flux:text class="font-medium text-zinc-900 dark:text-white truncate">{{ $plan['name'] }}</flux:text>
                                    <flux:text size="sm" class="text-zinc-400">{{ $plan['phone'] }}</flux:text>
                                </div>
                                <div class="shrink-0 text-right">
                                    @if($plan['action'] === 'create')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                            <flux:icon name="user-plus" class="w-3 h-3" />
                                            Will create
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                            <flux:icon name="link" class="w-3 h-3" />
                                            Will link
                                        </span>
                                        @if(!empty($plan['matched_name']))
                                            <flux:text size="sm" class="text-zinc-400 mt-1 block truncate max-w-[12rem]">to {{ $plan['matched_name'] }}</flux:text>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Skipped list (collapsed style) -->
                @if(!empty($plansSkipped))
                    <details class="rounded-xl border border-amber-200 dark:border-amber-700/50 bg-amber-50 dark:bg-amber-900/20">
                        <summary class="cursor-pointer px-4 py-2.5 text-sm font-medium text-amber-800 dark:text-amber-300">
                            {{ count($plansSkipped) }} order{{ count($plansSkipped) === 1 ? '' : 's' }} will be skipped (cannot create student)
                        </summary>
                        <div class="border-t border-amber-200 dark:border-amber-700/50 max-h-40 overflow-y-auto divide-y divide-amber-100 dark:divide-amber-800/30">
                            @foreach($plansSkipped as $skip)
                                <div class="px-4 py-2 text-sm" wire:key="bulk-skip-{{ $skip['order']->id }}">
                                    <span class="font-mono text-zinc-500 dark:text-zinc-400">{{ $skip['order']->order_number }}</span>
                                    <span class="text-amber-800 dark:text-amber-300"> &middot; {{ $skip['reason'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif

                <!-- Footer buttons -->
                <div class="flex justify-between gap-3 pt-2 border-t border-zinc-100 dark:border-zinc-700">
                    <flux:button variant="ghost" wire:click="backToClassPicker">
                        <div class="flex items-center justify-center">
                            <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                            Back
                        </div>
                    </flux:button>
                    <flux:button variant="primary" wire:click="submitClassAssignment">
                        Create students &amp; assign
                    </flux:button>
                </div>
            </div>
        @elseif($classAssignBulkMode)
            <div class="space-y-5">
                <!-- Header -->
                <div>
                    <flux:heading size="lg">Bulk Class Assignment</flux:heading>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 mt-1">
                        Assign {{ $bulkOrderCount }} order{{ $bulkOrderCount === 1 ? '' : 's' }} to one or more classes. If any orders have no student linked yet, you'll be asked to confirm creating them.
                    </flux:text>
                </div>

                <!-- Search -->
                <flux:input wire:model.live.debounce.300ms="classAssignSearch" placeholder="Search classes or courses..." size="sm" />

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
                                    <div wire:click="toggleClassAssignSelection({{ $class->id }})" wire:key="bulk-class-assign-{{ $class->id }}"
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
                        <flux:text size="sm">{{ $classAssignSearch ? 'No classes found' : 'No available classes (already assigned to all selected orders).' }}</flux:text>
                    </div>
                @endif

                <!-- Submit Button -->
                @if(!empty($classAssignSelectedIds))
                    <flux:button variant="primary" wire:click="submitClassAssignment" class="w-full">
                        Assign {{ $bulkOrderCount }} order{{ $bulkOrderCount === 1 ? '' : 's' }} to {{ count($classAssignSelectedIds) }} class{{ count($classAssignSelectedIds) !== 1 ? 'es' : '' }}
                    </flux:button>
                @endif
            </div>
        @elseif($classAssignOrder)
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