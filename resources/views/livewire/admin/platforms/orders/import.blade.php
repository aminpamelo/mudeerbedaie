<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ImportJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

new class extends Component {
    use WithFileUploads;

    public Platform $platform;
    public $accounts;

    // Upload form
    public $csv_file;
    public $selected_account_id = '';
    public $import_notes = '';

    // Import workflow state
    public $current_step = 1; // 1: Upload, 2: Field Mapping, 3: Product Mapping, 4: Preview, 5: Processing
    public $import_job_id = null;
    public $importing = false;
    public $import_progress = 0;
    public $import_results = null;

    // CSV data
    public $csv_headers = [];
    public $csv_data = [];
    public $sample_data = [];
    public $total_rows = 0;

    // Field mapping
    public $field_mapping = [];
    public $mapped_products = [];
    public $unmapped_skus = [];
    public $suggested_mappings = [];

    public function mount(Platform $platform)
    {
        $this->platform = $platform;
        $this->loadAccounts();
    }

    public function loadAccounts()
    {
        $this->accounts = $this->platform->accounts()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function rules()
    {
        return [
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'selected_account_id' => 'required|exists:platform_accounts,id',
            'import_notes' => 'nullable|string|max:1000',
        ];
    }

    public function updatedCsvFile()
    {
        if (!$this->csv_file) {
            return;
        }

        try {
            $this->validateOnly('csv_file');
            $this->previewCsv();
        } catch (ValidationException $e) {
            $this->preview_data = null;
            $this->show_preview = false;
        }
    }

    public function previewCsv()
    {
        if (!$this->csv_file) {
            return;
        }

        try {
            $path = $this->csv_file->store('temp');
            $content = Storage::get($path);

            // Parse CSV
            $lines = str_getcsv($content, "\n");
            if (count($lines) < 2) {
                throw new \Exception('CSV file must contain at least a header row and one data row.');
            }

            // Get headers (first row)
            $this->csv_headers = str_getcsv($lines[0]);

            // Get sample data (next 3 rows)
            $this->sample_data = [];
            for ($i = 1; $i <= min(3, count($lines) - 1); $i++) {
                $this->sample_data[] = str_getcsv($lines[$i]);
            }

            // Initialize field mapping with best guesses
            $this->initializeFieldMapping();

            $this->show_preview = true;

            // Clean up temp file
            Storage::delete($path);

        } catch (\Exception $e) {
            $this->addError('csv_file', 'Error reading CSV: ' . $e->getMessage());
            $this->show_preview = false;
        }
    }

    public function initializeFieldMapping()
    {
        $this->field_mapping = [];

        // Common field mappings based on header names
        $commonMappings = [
            'order_id' => ['order_id', 'order id', 'order number', 'order_number', 'id'],
            'order_date' => ['order_date', 'order date', 'date', 'created_at', 'created'],
            'status' => ['status', 'order_status', 'state'],
            'total_amount' => ['total', 'total_amount', 'amount', 'total_price', 'price'],
            'currency' => ['currency', 'currency_code'],
            'customer_name' => ['customer_name', 'customer name', 'buyer_name', 'buyer name'],
            'customer_email' => ['customer_email', 'customer email', 'email', 'buyer_email'],
            'shipping_address' => ['shipping_address', 'address', 'delivery_address'],
            'items_count' => ['items_count', 'items count', 'quantity', 'qty'],
            'platform_fees' => ['platform_fees', 'fees', 'commission'],
            'tracking_number' => ['tracking_number', 'tracking', 'shipment_id'],
        ];

        foreach ($this->csv_headers as $index => $header) {
            $normalizedHeader = strtolower(trim($header));

            foreach ($commonMappings as $field => $variations) {
                if (in_array($normalizedHeader, $variations)) {
                    $this->field_mapping[$field] = $index;
                    break;
                }
            }
        }
    }

    public function getRequiredFields()
    {
        return [
            'order_id' => 'Order ID',
            'order_date' => 'Order Date',
            'status' => 'Status',
            'total_amount' => 'Total Amount',
            'currency' => 'Currency',
        ];
    }

    public function getOptionalFields()
    {
        return [
            'customer_name' => 'Customer Name',
            'customer_email' => 'Customer Email',
            'shipping_address' => 'Shipping Address',
            'items_count' => 'Items Count',
            'platform_fees' => 'Platform Fees',
            'tracking_number' => 'Tracking Number',
            'notes' => 'Notes',
        ];
    }

    public function import()
    {
        $this->validate();

        // Validate required field mappings
        foreach ($this->getRequiredFields() as $field => $label) {
            if (!isset($this->field_mapping[$field]) || $this->field_mapping[$field] === '') {
                throw ValidationException::withMessages([
                    'field_mapping' => "Please map the required field: {$label}"
                ]);
            }
        }

        $this->importing = true;
        $this->import_progress = 0;

        try {
            $account = PlatformAccount::findOrFail($this->selected_account_id);

            $path = $this->csv_file->store('imports');
            $content = Storage::get($path);
            $lines = str_getcsv($content, "\n");

            // Skip header row
            $dataLines = array_slice($lines, 1);
            $totalRows = count($dataLines);

            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($dataLines as $index => $line) {
                try {
                    $row = str_getcsv($line);
                    $this->processOrderRow($row, $account);
                    $imported++;
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }

                // Update progress
                $this->import_progress = round((($index + 1) / $totalRows) * 100);
            }

            $this->import_results = [
                'total_rows' => $totalRows,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
                'file_path' => $path,
            ];

            // Reset form
            $this->resetImportForm();

            $this->dispatch('import-completed', [
                'message' => "Import completed: {$imported} orders imported, {$skipped} skipped"
            ]);

        } catch (\Exception $e) {
            $this->addError('import', 'Import failed: ' . $e->getMessage());
        } finally {
            $this->importing = false;
        }
    }

    private function processOrderRow(array $row, PlatformAccount $account)
    {
        // Extract data based on mapping
        $orderData = [];
        foreach ($this->field_mapping as $field => $columnIndex) {
            if ($columnIndex !== '' && isset($row[$columnIndex])) {
                $orderData[$field] = trim($row[$columnIndex]);
            }
        }

        // Validate required fields
        foreach (array_keys($this->getRequiredFields()) as $field) {
            if (empty($orderData[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Check if order already exists
        $existingOrder = ProductOrder::where('platform_account_id', $account->id)
            ->where('platform_order_id', $orderData['order_id'])
            ->first();

        if ($existingOrder) {
            throw new \Exception("Order {$orderData['order_id']} already exists");
        }

        // Create product order
        $productOrder = ProductOrder::create([
            'order_number' => ProductOrder::generateOrderNumber(),
            'platform_id' => $this->platform->id,
            'platform_account_id' => $account->id,
            'platform_order_id' => $orderData['order_id'],
            'platform_order_number' => $orderData['order_id'],
            'status' => $this->normalizeOrderStatus($orderData['status']),
            'order_date' => $this->parseDate($orderData['order_date']),
            'total_amount' => $this->parseAmount($orderData['total_amount']),
            'subtotal' => $this->parseAmount($orderData['total_amount']),
            'currency' => strtoupper($orderData['currency']),
            'customer_name' => $orderData['customer_name'] ?? null,
            'guest_email' => $orderData['customer_email'] ?? null,
            'shipping_address' => $orderData['shipping_address'] ?? null,
            'tracking_id' => $orderData['tracking_number'] ?? null,
            'source' => 'platform_import',
            'metadata' => [
                'imported_at' => now()->toISOString(),
                'import_notes' => $this->import_notes,
                'raw_data' => $orderData,
            ],
        ]);

        return $productOrder;
    }

    private function normalizeOrderStatus(string $status): string
    {
        $status = strtolower(trim($status));

        $statusMap = [
            'pending' => 'pending',
            'confirmed' => 'confirmed',
            'processing' => 'processing',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'completed' => 'completed',
            'paid' => 'confirmed',
            'unpaid' => 'pending',
        ];

        return $statusMap[$status] ?? 'pending';
    }

    private function parseDate(string $date): \Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            throw new \Exception("Invalid date format: {$date}");
        }
    }

    private function parseAmount(string $amount): float
    {
        // Remove currency symbols and whitespace
        $amount = preg_replace('/[^\d.,\-]/', '', $amount);
        $amount = str_replace(',', '', $amount);

        return (float) $amount;
    }

    private function parseInt(string $value): int
    {
        return (int) preg_replace('/[^\d]/', '', $value);
    }

    public function resetImportForm()
    {
        $this->csv_file = null;
        $this->selected_account_id = '';
        $this->import_notes = '';
        $this->show_preview = false;
        $this->preview_data = null;
        $this->csv_headers = [];
        $this->field_mapping = [];
        $this->sample_data = [];
    }

    public function downloadTemplate()
    {
        $headers = [
            'order_id',
            'order_date',
            'status',
            'total_amount',
            'currency',
            'customer_name',
            'customer_email',
            'shipping_address',
            'items_count',
            'platform_fees',
            'tracking_number',
            'notes'
        ];

        $sampleData = [
            ['ORD-001', '2024-01-15 10:30:00', 'completed', '29.99', 'USD', 'John Doe', 'john@example.com', '123 Main St, City, State 12345', '2', '2.99', 'TRK123456789', 'Sample order'],
            ['ORD-002', '2024-01-16 14:20:00', 'shipped', '45.50', 'USD', 'Jane Smith', 'jane@example.com', '456 Oak Ave, Town, State 67890', '1', '4.55', 'TRK987654321', 'Express delivery'],
        ];

        $csv = implode(',', $headers) . "\n";
        foreach ($sampleData as $row) {
            $csv .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        return response()->streamDownload(function() use ($csv) {
            echo $csv;
        }, "order-import-template-{$this->platform->slug}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}; ?>

<div>
    {{-- Breadcrumb Navigation --}}
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <div>
                        <flux:button variant="ghost" size="sm" :href="route('platforms.index')" wire:navigate>
                            <div class="flex items-center justify-center">
                                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                                Back to Platforms
                            </div>
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <flux:button variant="ghost" size="sm" :href="route('platforms.show', $platform)" wire:navigate class="ml-4">
                            {{ $platform->display_name }}
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <flux:button variant="ghost" size="sm" :href="route('platforms.orders.index', $platform)" wire:navigate class="ml-4">
                            Orders
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <span class="ml-4 text-sm font-medium text-zinc-500">Import Orders</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Import Orders</flux:heading>
            <flux:text class="mt-2">Import orders from {{ $platform->display_name }} using CSV file upload</flux:text>
        </div>
        <flux:button variant="outline" wire:click="downloadTemplate">
            <div class="flex items-center justify-center">
                <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                Download Template
            </div>
        </flux:button>
    </div>

    {{-- Import Results --}}
    @if($import_results)
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
        <flux:heading size="lg" class="mb-4">Import Results</flux:heading>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <flux:text size="2xl" class="font-bold text-green-600">{{ $import_results['imported'] }}</flux:text>
                <flux:text size="sm" class="text-green-600">Orders Imported</flux:text>
            </div>
            <div class="text-center p-4 bg-amber-50 rounded-lg">
                <flux:text size="2xl" class="font-bold text-amber-600">{{ $import_results['skipped'] }}</flux:text>
                <flux:text size="sm" class="text-amber-600">Orders Skipped</flux:text>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <flux:text size="2xl" class="font-bold text-blue-600">{{ $import_results['total_rows'] }}</flux:text>
                <flux:text size="sm" class="text-blue-600">Total Rows</flux:text>
            </div>
        </div>

        @if(count($import_results['errors']) > 0)
            <div class="mt-4">
                <flux:heading size="md" class="mb-2 text-red-600">Errors:</flux:heading>
                <div class="space-y-1 max-h-48 overflow-y-auto">
                    @foreach($import_results['errors'] as $error)
                        <flux:text size="sm" class="text-red-600">• {{ $error }}</flux:text>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
    @endif

    {{-- Import Form --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <form wire:submit="import" class="space-y-6">
                {{-- File Upload --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Step 1: Select CSV File</flux:heading>

                    <div class="space-y-4">
                        <div>
                            <flux:field>
                                <flux:label>CSV File *</flux:label>
                                <input type="file" wire:model="csv_file" accept=".csv,.txt" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                <flux:description>Upload a CSV file containing order data (Max: 10MB)</flux:description>
                                <flux:error name="csv_file" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Account *</flux:label>
                                <flux:select wire:model="selected_account_id">
                                    <flux:select.option value="">Select account...</flux:select.option>
                                    @foreach($accounts as $account)
                                        <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:description>Select which platform account these orders belong to</flux:description>
                                <flux:error name="selected_account_id" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Import Notes</flux:label>
                                <flux:textarea wire:model="import_notes" placeholder="Optional notes about this import batch..." rows="3" />
                                <flux:description>Optional notes to help track this import batch</flux:description>
                                <flux:error name="import_notes" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                {{-- CSV Preview and Mapping --}}
                @if($show_preview)
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Step 2: Map CSV Fields</flux:heading>

                    {{-- CSV Sample Preview --}}
                    <div class="mb-6">
                        <flux:text class="font-medium mb-2">CSV Preview:</flux:text>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        @foreach($csv_headers as $header)
                                            <th class="px-3 py-2 border text-left font-medium">{{ $header }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sample_data as $row)
                                        <tr>
                                            @foreach($row as $cell)
                                                <td class="px-3 py-2 border">{{ \Str::limit($cell, 20) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Field Mapping --}}
                    <div class="space-y-4">
                        <flux:text class="font-medium">Required Fields:</flux:text>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($this->getRequiredFields() as $field => $label)
                                <div>
                                    <flux:field>
                                        <flux:label>{{ $label }} *</flux:label>
                                        <flux:select wire:model="field_mapping.{{ $field }}">
                                            <flux:select.option value="">-- Select Column --</flux:select.option>
                                            @foreach($csv_headers as $index => $header)
                                                <flux:select.option value="{{ $index }}">{{ $header }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>
                                </div>
                            @endforeach
                        </div>

                        <flux:text class="font-medium mt-6">Optional Fields:</flux:text>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($this->getOptionalFields() as $field => $label)
                                <div>
                                    <flux:field>
                                        <flux:label>{{ $label }}</flux:label>
                                        <flux:select wire:model="field_mapping.{{ $field }}">
                                            <flux:select.option value="">-- Skip This Field --</flux:select.option>
                                            @foreach($csv_headers as $index => $header)
                                                <flux:select.option value="{{ $index }}">{{ $header }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- Import Progress --}}
                @if($importing)
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Importing Orders...</flux:heading>

                    <div class="space-y-4">
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: {{ $import_progress }}%"></div>
                        </div>
                        <flux:text class="text-center">{{ $import_progress }}% Complete</flux:text>
                    </div>
                </div>
                @endif

                {{-- Form Actions --}}
                @if($show_preview && !$importing)
                <div class="flex items-center justify-between">
                    <flux:button variant="ghost" wire:click="resetImportForm">
                        <div class="flex items-center justify-center">
                            <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                            Reset
                        </div>
                    </flux:button>

                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-up-tray" class="w-4 h-4 mr-1" wire:loading.remove />
                            <flux:icon name="loading" class="w-4 h-4 mr-1 animate-spin" wire:loading />
                            <span wire:loading.remove>Import Orders</span>
                            <span wire:loading>Importing...</span>
                        </div>
                    </flux:button>
                </div>
                @endif
            </form>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Import Guidelines --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Import Guidelines</flux:heading>

                <div class="space-y-4">
                    <div>
                        <flux:text size="sm" class="font-medium text-green-600">✓ Supported Formats</flux:text>
                        <flux:text size="sm" class="text-zinc-600">CSV files with UTF-8 encoding</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="font-medium text-blue-600">📝 Required Fields</flux:text>
                        <flux:text size="sm" class="text-zinc-600">Order ID, Date, Status, Amount, Currency</flux:text>
                    </div>

                    <div>
                        <flux:text size="sm" class="font-medium text-amber-600">⚠️ Important Notes</flux:text>
                        <ul class="text-sm text-zinc-600 space-y-1 ml-4 list-disc">
                            <li>Duplicate orders will be skipped</li>
                            <li>Invalid data rows will be skipped</li>
                            <li>Maximum file size: 10MB</li>
                            <li>Date format: YYYY-MM-DD HH:mm:ss</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Account Info --}}
            @if($accounts->count() === 0)
            <div class="bg-amber-50 rounded-lg border border-amber-200 p-6">
                <flux:heading size="lg" class="mb-2 text-amber-700">No Active Accounts</flux:heading>
                <flux:text size="sm" class="text-amber-600 mb-4">
                    You need to set up platform accounts before importing orders.
                </flux:text>
                <flux:button variant="primary" size="sm" :href="route('platforms.accounts.create', $platform)" wire:navigate>
                    <div class="flex items-center justify-center">
                        <flux:icon name="plus" class="w-4 h-4 mr-1" />
                        Add Account
                    </div>
                </flux:button>
            </div>
            @endif

            {{-- Help & Support --}}
            <div class="bg-blue-50 rounded-lg border border-blue-200 p-6">
                <flux:heading size="lg" class="mb-2 text-blue-700">Need Help?</flux:heading>
                <flux:text size="sm" class="text-blue-600 mb-4">
                    Having trouble with the import? Download our template file or check the documentation.
                </flux:text>
                <div class="space-y-2">
                    <flux:button variant="outline" size="sm" class="w-full" wire:click="downloadTemplate">
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                            Download Template
                        </div>
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
</div>