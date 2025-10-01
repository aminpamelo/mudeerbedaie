<?php

use App\Models\ImportJob;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Services\TikTokOrderProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
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

    public $product_mappings = [];

    public $preview_data = [];

    public function mount()
    {
        // Load TikTok platform specifically since this is a TikTok import component
        $this->platform = Platform::where('slug', 'tiktok-shop')->firstOrFail();
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
            'csv_file' => 'required|file|mimes:csv,txt|max:20480', // 20MB max for TikTok files
            'selected_account_id' => 'required|exists:platform_accounts,id',
            'import_notes' => 'nullable|string|max:1000',
        ];
    }

    // Step 1: File Upload and Initial Processing
    public function uploadAndProcess()
    {
        $this->validate();

        try {
            // Store the CSV file
            $path = $this->csv_file->store('imports/tiktok');
            $fileHash = hash_file('md5', $this->csv_file->getRealPath());
            $fileSize = $this->csv_file->getSize();

            // Create import job
            $importJob = ImportJob::createFromUpload([
                'platform_id' => $this->platform->id,
                'platform_account_id' => $this->selected_account_id,
                'user_id' => auth()->id(),
                'file_name' => $this->csv_file->getClientOriginalName(),
                'file_path' => $path,
                'file_hash' => $fileHash,
                'file_size' => $fileSize,
                'import_type' => 'tiktok_orders',
            ]);

            $this->import_job_id = $importJob->id;

            // Parse CSV file
            $this->parseCsvFile($path);

            // Initialize TikTok field mapping
            $this->initializeTikTokFieldMapping();

            $this->current_step = 2;

        } catch (\Exception $e) {
            $this->addError('csv_file', 'Error processing CSV: '.$e->getMessage());
        }
    }

    private function parseCsvFile($path)
    {
        $content = Storage::get($path);
        $lines = str_getcsv($content, "\n");

        if (count($lines) < 2) {
            throw new \Exception('CSV file must contain at least a header row and one data row.');
        }

        // TikTok CSV format: Row 1 is headers, Row 2+ is data
        $this->csv_headers = str_getcsv($lines[0]);

        // Store all data for processing (skip first row which is headers)
        $this->csv_data = [];
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            // Skip empty rows
            if (! empty(array_filter($row))) {
                $this->csv_data[] = $row;
            }
        }

        $this->total_rows = count($this->csv_data);

        // Get sample data (first 3 rows)
        $this->sample_data = array_slice($this->csv_data, 0, 3);

        // Update import job with row count
        $importJob = ImportJob::find($this->import_job_id);
        $importJob->update(['total_rows' => $this->total_rows]);
    }

    private function initializeTikTokFieldMapping()
    {
        $this->field_mapping = [];

        // TikTok-specific field mappings (matching exact CSV column names from row 2)
        $tikTokMappings = [
            // Order identifiers
            'buyer_username' => ['buyer username'],
            'order_id' => ['order id'],
            'tracking_id' => ['tracking id'],
            'package_id' => ['package id'],

            // Product information
            'product_name' => ['product name'],
            'variation' => ['variation'],
            'quantity' => ['quantity'],
            'returned_quantity' => ['sku quantity of return'],
            'product_category' => ['product category'],

            // Pricing
            'unit_price' => ['sku unit original price'],
            'subtotal_before_discount' => ['sku subtotal before discount'],
            'sku_platform_discount' => ['sku platform discount'],
            'sku_seller_discount' => ['sku seller discount'],
            'subtotal_after_discount' => ['sku subtotal after discount'],

            // Shipping
            'shipping_fee_after_discount' => ['shipping fee after discount'],
            'original_shipping_fee' => ['original shipping fee'],
            'shipping_fee_seller_discount' => ['shipping fee seller discount'],
            'shipping_fee_platform_discount' => ['shipping fee platform discount'],

            // Order details
            'order_amount' => ['order amount'],
            'order_refund_amount' => ['order refund amount'],
            'taxes' => ['taxes'],
            'payment_platform_discount' => ['payment platform discount'],

            // Timestamps
            'created_time' => ['created time'],
            'paid_time' => ['paid time'],
            'rts_time' => ['rts time'],
            'shipped_time' => ['shipped time'],
            'delivered_time' => ['delivered time'],
            'cancelled_time' => ['cancelled time'],

            // Customer info
            'recipient' => ['recipient'],
            'phone' => ['phone #'],
            'country' => ['country'],
            'state' => ['state'],
            'post_town' => ['post town'],
            'zipcode' => ['zipcode'],
            'detail_address' => ['detail address'],
            'additional_address' => ['additional address information'],

            // Fulfillment
            'fulfillment_type' => ['fulfillment type'],
            'warehouse_name' => ['warehouse name'],
            'delivery_option' => ['delivery option'],
            'shipping_provider' => ['shipping provider name'],
            'payment_method' => ['payment method'],
            'weight' => ['weight(kg)'],

            // Additional
            'buyer_message' => ['buyer message'],
            'seller_note' => ['seller note'],
            'cancel_by' => ['cancel by'],
            'cancel_reason' => ['cancel reason'],
            'checked_status' => ['checked status'],
            'checked_marked_by' => ['checked marked by'],
        ];

        foreach ($this->csv_headers as $index => $header) {
            $normalizedHeader = strtolower(trim($header));

            foreach ($tikTokMappings as $field => $variations) {
                if (in_array($normalizedHeader, $variations)) {
                    $this->field_mapping[$field] = $index;
                    break;
                }
            }
        }
    }

    // Step 2: Confirm Field Mapping
    public function confirmFieldMapping()
    {
        // Validate required fields are mapped
        $requiredFields = ['order_id', 'product_name', 'quantity', 'created_time'];

        foreach ($requiredFields as $field) {
            if (! isset($this->field_mapping[$field]) || $this->field_mapping[$field] === '') {
                throw ValidationException::withMessages([
                    'field_mapping' => "Please map the required field: {$field}",
                ]);
            }
        }

        // Analyze products and SKUs for mapping
        $this->analyzeProductsForMapping();

        $this->current_step = 3;
    }

    private function analyzeProductsForMapping()
    {
        $productNames = [];
        $variations = [];

        // Extract unique products and variations from CSV
        foreach ($this->csv_data as $row) {
            if (isset($this->field_mapping['product_name']) && isset($row[$this->field_mapping['product_name']])) {
                $productName = trim($row[$this->field_mapping['product_name']]);
                $variation = isset($this->field_mapping['variation']) && isset($row[$this->field_mapping['variation']])
                    ? trim($row[$this->field_mapping['variation']])
                    : null;

                $productNames[] = $productName;
                if ($variation) {
                    $variations[] = $variation;
                }
            }
        }

        $uniqueProducts = array_unique($productNames);
        $uniqueVariations = array_unique($variations);

        // Find existing mappings
        $existingMappings = PlatformSkuMapping::where('platform_id', $this->platform->id)
            ->where('platform_account_id', $this->selected_account_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('platform_sku');

        // Suggest mappings for products
        $this->suggested_mappings = [];
        $this->unmapped_skus = [];

        foreach ($uniqueProducts as $productName) {
            // Try to find matching products by name similarity
            $suggestedProduct = Product::where('name', 'like', "%{$productName}%")
                ->where('status', 'active')
                ->first();

            if ($suggestedProduct) {
                $this->suggested_mappings[$productName] = [
                    'product_id' => $suggestedProduct->id,
                    'product_name' => $suggestedProduct->name,
                    'confidence' => $this->calculateNameSimilarity($productName, $suggestedProduct->name),
                ];
            } else {
                $this->unmapped_skus[] = $productName;
            }
        }
    }

    private function calculateNameSimilarity($name1, $name2)
    {
        similar_text(strtolower($name1), strtolower($name2), $percent);

        return round($percent);
    }

    // Step 3: Product Mapping
    public function confirmProductMapping()
    {
        // Auto-skip any unmapped products that user didn't explicitly map
        foreach ($this->unmapped_skus as $sku) {
            if (! isset($this->product_mappings[$sku])) {
                // Automatically skip unmapped products
                $this->product_mappings[$sku] = [
                    'skipped' => true,
                    'product_id' => null,
                    'product_name' => $sku,
                    'confidence' => 0,
                ];
            }
        }

        // Convert suggested mappings to confirmed mappings
        foreach ($this->suggested_mappings as $productName => $suggestion) {
            if (! isset($this->product_mappings[$productName])) {
                $this->product_mappings[$productName] = $suggestion;
            }
        }

        $this->current_step = 4;
    }

    public function mapProduct($productName, $productId)
    {
        $product = Product::with('variants')->find($productId);
        if ($product) {
            $this->product_mappings[$productName] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'confidence' => 100,
            ];

            // Remove from unmapped list
            $this->unmapped_skus = array_diff($this->unmapped_skus, [$productName]);
        }
    }

    public function unmapProduct($productName)
    {
        unset($this->product_mappings[$productName]);
        if (! in_array($productName, $this->unmapped_skus)) {
            $this->unmapped_skus[] = $productName;
        }
    }

    public function skipProduct($productName)
    {
        // Mark product as skipped by adding it to product_mappings with skip flag
        $this->product_mappings[$productName] = [
            'skipped' => true,
            'product_id' => null,
            'product_name' => $productName,
            'confidence' => 0,
        ];

        // Remove from unmapped list
        $this->unmapped_skus = array_diff($this->unmapped_skus, [$productName]);
    }

    public function createProductFromSku($productName)
    {
        // Create a new product with the given name
        try {
            $product = Product::create([
                'name' => $productName,
                'slug' => \Str::slug($productName),
                'sku' => 'TIKTOK-'.\Str::slug($productName),
                'type' => 'simple',
                'status' => 'active',
                'base_price' => 0,
                'cost_price' => 0,
                'track_quantity' => true,
                'min_quantity' => 0,
                'description' => 'Product imported from TikTok',
            ]);

            // Map the newly created product
            $this->product_mappings[$productName] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'confidence' => 100,
            ];

            // Remove from unmapped list
            $this->unmapped_skus = array_diff($this->unmapped_skus, [$productName]);

            $this->dispatch('product-created', [
                'message' => "Product '{$productName}' created successfully!",
            ]);

        } catch (\Exception $e) {
            $this->dispatch('product-creation-failed', [
                'message' => "Failed to create product: {$e->getMessage()}",
            ]);
        }
    }

    // Step 4: Preview and Validate
    public function showPreview()
    {
        // Generate preview data with field and product mappings applied
        $this->generatePreviewData();
        $this->current_step = 4;
    }

    private function generatePreviewData()
    {
        $this->preview_data = [];

        // Take first 5 rows for preview
        $previewRows = array_slice($this->csv_data, 0, 5);

        foreach ($previewRows as $index => $row) {
            $previewItem = [
                'row_number' => $index + 2, // +2 because array is 0-indexed and row 1 is headers (header is row 1, data starts at row 2)
                'raw_data' => $row,
                'mapped_data' => $this->mapRowData($row),
                'validation_errors' => [],
                'warnings' => [],
            ];

            // Validate mapped data
            $previewItem['validation_errors'] = $this->validateMappedRow($previewItem['mapped_data']);
            $previewItem['warnings'] = $this->getRowWarnings($previewItem['mapped_data']);

            $this->preview_data[] = $previewItem;
        }
    }

    private function mapRowData($row)
    {
        $mapped = [];

        // Map basic order fields
        foreach ($this->field_mapping as $systemField => $csvColumn) {
            if ($csvColumn && isset($row[$csvColumn])) {
                $mapped[$systemField] = $row[$csvColumn];
            }
        }

        // Apply product mapping if exists
        if (isset($mapped['product_name'])) {
            $productName = trim($mapped['product_name']);
            if (isset($this->product_mappings[$productName])) {
                $mapping = $this->product_mappings[$productName];
                $mapped['internal_product_id'] = $mapping['product_id'];
                $mapped['internal_product_name'] = $mapping['product_name'];

                if (isset($mapping['variant_id'])) {
                    $mapped['internal_variant_id'] = $mapping['variant_id'];
                    $mapped['internal_variant_name'] = $mapping['variant_name'];
                }
            }
        }

        return $mapped;
    }

    private function validateMappedRow($mappedData)
    {
        $errors = [];

        // Required fields validation
        $requiredFields = [
            'order_id' => 'Order ID',
            'product_name' => 'Product Name',
            'quantity' => 'Quantity',
            'created_time' => 'Order Date',
        ];

        foreach ($requiredFields as $field => $label) {
            if (empty($mappedData[$field])) {
                $errors[] = "{$label} is required";
            }
        }

        // Data type validation
        if (isset($mappedData['quantity']) && ! is_numeric($mappedData['quantity'])) {
            $errors[] = 'Quantity must be a number';
        }

        if (isset($mappedData['order_amount']) && ! is_numeric($mappedData['order_amount'])) {
            $errors[] = 'Order amount must be a number';
        }

        // Product mapping validation
        if (isset($mappedData['product_name']) && ! isset($mappedData['internal_product_id'])) {
            $errors[] = "Product '{$mappedData['product_name']}' is not mapped to an internal product";
        }

        return $errors;
    }

    private function getRowWarnings($mappedData)
    {
        $warnings = [];

        // Check for missing optional but important fields
        if (empty($mappedData['customer_name'])) {
            $warnings[] = 'Customer name is missing';
        }

        if (empty($mappedData['customer_phone'])) {
            $warnings[] = 'Customer phone is missing';
        }

        if (empty($mappedData['shipping_cost'])) {
            $warnings[] = 'Shipping cost is missing';
        }

        // Check for potential data quality issues
        if (isset($mappedData['quantity']) && $mappedData['quantity'] > 100) {
            $warnings[] = "Large quantity ({$mappedData['quantity']}) - please verify";
        }

        return $warnings;
    }

    // Step 5: Execute Import
    public function executeImport()
    {
        $this->importing = true;
        $this->current_step = 5;

        try {
            $importJob = ImportJob::find($this->import_job_id);
            $importJob->markAsStarted();

            $account = PlatformAccount::findOrFail($this->selected_account_id);

            // Reload CSV data from stored file since Livewire state may not persist large arrays
            $csvData = $this->reloadCsvDataFromFile($importJob->file_path);

            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($csvData as $index => $row) {
                try {
                    DB::transaction(function () use ($row, $account, &$imported) {
                        $this->processOrderRow($row, $account);
                        $imported++;
                    });
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = 'Row '.($index + 2).': '.$e->getMessage();
                    $importJob->addError($e->getMessage(), $index + 2);
                }

                // Update progress
                $this->import_progress = round((($index + 1) / $this->total_rows) * 100);
                $importJob->updateProgress($index + 1);
            }

            // Update import job results
            $importJob->update([
                'successful_rows' => $imported,
                'failed_rows' => $skipped,
            ]);

            $importJob->markAsCompleted();

            $this->import_results = [
                'total_rows' => $this->total_rows,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ];

            $this->dispatch('import-completed', [
                'message' => "TikTok import completed: {$imported} orders imported to Orders & Package Sales, {$skipped} skipped",
            ]);

        } catch (\Exception $e) {
            ImportJob::find($this->import_job_id)->markAsFailed($e->getMessage());
            $this->addError('import', 'Import failed: '.$e->getMessage());
        } finally {
            $this->importing = false;
        }
    }

    private function processOrderRow(array $row, PlatformAccount $account)
    {
        // Extract order data based on field mapping
        $orderData = $this->extractOrderData($row);

        // Check for duplicate order
        $existingOrder = ProductOrder::where('platform_account_id', $account->id)
            ->where('platform_order_id', $orderData['order_id'])
            ->first();

        if ($existingOrder) {
            throw new \Exception("Order {$orderData['order_id']} already exists");
        }

        // Use TikTokOrderProcessor to create unified product order
        $processor = new TikTokOrderProcessor(
            $this->platform,
            $account,
            $this->field_mapping,
            $this->product_mappings
        );

        $result = $processor->processOrderRow($orderData);

        return $result['product_order'];
    }

    private function extractOrderData(array $row): array
    {
        $data = [];

        foreach ($this->field_mapping as $field => $columnIndex) {
            if ($columnIndex !== '' && isset($row[$columnIndex])) {
                $data[$field] = trim($row[$columnIndex]);
            }
        }

        return $data;
    }

    // Helper methods
    public function getTikTokFields()
    {
        return [
            'Order Information' => [
                'buyer_username' => 'Buyer Username',
                'order_id' => 'Order ID *',
                'tracking_id' => 'Tracking ID',
                'package_id' => 'Package ID',
            ],
            'Product Information' => [
                'product_name' => 'Product Name *',
                'variation' => 'Variation',
                'quantity' => 'Quantity *',
                'returned_quantity' => 'Returned Quantity',
                'product_category' => 'Product Category',
            ],
            'Pricing' => [
                'unit_price' => 'Unit Price',
                'subtotal_before_discount' => 'Subtotal Before Discount',
                'sku_platform_discount' => 'Platform Discount',
                'sku_seller_discount' => 'Seller Discount',
                'subtotal_after_discount' => 'Subtotal After Discount',
                'order_amount' => 'Order Amount',
            ],
            'Timestamps' => [
                'created_time' => 'Created Time *',
                'paid_time' => 'Paid Time',
                'rts_time' => 'RTS Time',
                'shipped_time' => 'Shipped Time',
                'delivered_time' => 'Delivered Time',
            ],
            'Customer Information' => [
                'recipient' => 'Recipient',
                'phone' => 'Phone',
                'country' => 'Country',
                'state' => 'State',
                'post_town' => 'City',
                'zipcode' => 'ZIP Code',
                'detail_address' => 'Address',
            ],
            'Fulfillment' => [
                'fulfillment_type' => 'Fulfillment Type',
                'warehouse_name' => 'Warehouse',
                'shipping_provider' => 'Shipping Provider',
                'payment_method' => 'Payment Method',
            ],
        ];
    }

    private function reloadCsvDataFromFile(string $filePath): array
    {
        $fullPath = Storage::path($filePath);

        if (! file_exists($fullPath)) {
            throw new \Exception("CSV file not found at: {$filePath}");
        }

        $fileContent = file_get_contents($fullPath);
        $lines = explode("\n", $fileContent);

        // Skip first row (header row) and parse data rows
        $csvData = [];
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            // Skip empty rows
            if (! empty(array_filter($row))) {
                $csvData[] = $row;
            }
        }

        return $csvData;
    }

    public function resetImport()
    {
        $this->current_step = 1;
        $this->csv_file = null;
        $this->csv_headers = [];
        $this->csv_data = [];
        $this->field_mapping = [];
        $this->import_job_id = null;
        $this->import_results = null;
    }

    public function downloadTikTokTemplate()
    {
        $tikTokHeaders = [
            'Buyer Username', 'Order ID', 'Tracking ID', 'Product Name', 'Variation',
            'Quantity', 'Sku Quantity of return', 'SKU Unit Original Price',
            'SKU Subtotal Before Discount', 'SKU Platform Discount', 'SKU Seller Discount',
            'SKU Subtotal After Discount', 'Shipping Fee After Discount', 'Original Shipping Fee',
            'Shipping Fee Seller Discount', 'Shipping Fee Platform Discount', 'Payment platform discount',
            'Taxes', 'Order Amount', 'Order Refund Amount', 'Created Time', 'Paid Time',
            'RTS Time', 'Shipped Time', 'Delivered Time', 'Cancelled Time', 'Cancel By',
            'Cancel Reason', 'Fulfillment Type', 'Warehouse Name', 'Delivery Option',
            'Shipping Provider Name', 'Buyer Message', 'Recipient', 'Phone #', 'Zipcode',
            'Country', 'State', 'Post Town', 'Detail Address', 'Additional address information',
            'Payment Method', 'Weight(kg)', 'Product Category', 'Package ID', 'Seller Note',
            'Checked Status', 'Checked Marked by',
        ];

        $csv = implode(',', $tikTokHeaders)."\n";

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'tiktok-order-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}; ?>

<div>
    {{-- Progress Steps --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            @foreach(['Upload CSV', 'Map Fields', 'Map Products', 'Preview', 'Import'] as $index => $stepName)
                <div class="flex items-center {{ $index < count(['Upload CSV', 'Map Fields', 'Map Products', 'Preview', 'Import']) - 1 ? 'flex-1' : '' }}">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full
                        {{ $current_step > $index + 1 ? 'bg-green-500 text-white' : ($current_step == $index + 1 ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600') }}">
                        {{ $current_step > $index + 1 ? '✓' : $index + 1 }}
                    </div>
                    <span class="ml-2 text-sm font-medium">{{ $stepName }}</span>
                    @if($index < count(['Upload CSV', 'Map Fields', 'Map Products', 'Preview', 'Import']) - 1)
                        <div class="flex-1 h-0.5 mx-4 {{ $current_step > $index + 1 ? 'bg-green-500' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Import TikTok Orders</flux:heading>
            <flux:text class="mt-2">Import orders from TikTok using CSV export files</flux:text>
        </div>
        <flux:button variant="outline" wire:click="downloadTikTokTemplate">
            <div class="flex items-center justify-center">
                <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                Download TikTok Template
            </div>
        </flux:button>
    </div>

    {{-- Step 1: File Upload --}}
    @if($current_step === 1)
    <div class="bg-white rounded-lg border p-6">
        <flux:heading size="lg" class="mb-4">Step 1: Upload TikTok CSV File</flux:heading>

        <form wire:submit="uploadAndProcess" class="space-y-4">
            <div>
                <flux:field>
                    <flux:label>TikTok CSV File *</flux:label>
                    <input type="file" wire:model="csv_file" accept=".csv,.txt"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                    <flux:description>Upload the CSV file exported from TikTok Seller Center (Max: 20MB)</flux:description>
                    <flux:error name="csv_file" />
                </flux:field>
            </div>

            <div>
                <flux:field>
                    <flux:label>Account *</flux:label>
                    <flux:select wire:model="selected_account_id">
                        <flux:select.option value="">Select TikTok account...</flux:select.option>
                        @foreach($accounts as $account)
                            <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>Select which TikTok account these orders belong to</flux:description>
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

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-up-tray" class="w-4 h-4 mr-1" wire:loading.remove />
                        <flux:icon name="loading" class="w-4 h-4 mr-1 animate-spin" wire:loading />
                        <span wire:loading.remove>Process CSV File</span>
                        <span wire:loading>Processing...</span>
                    </div>
                </flux:button>
            </div>
        </form>
    </div>
    @endif

    {{-- Step 2: Field Mapping --}}
    @if($current_step === 2)
    <div class="bg-white rounded-lg border p-6">
        <flux:heading size="lg" class="mb-4">Step 2: Map CSV Fields</flux:heading>
        <flux:text class="mb-6">Found {{ count($csv_headers) }} columns and {{ $total_rows }} data rows. Map the TikTok CSV columns to system fields.</flux:text>

        {{-- CSV Preview --}}
        <div class="mb-6">
            <flux:text class="font-medium mb-2">CSV Preview (first 3 rows):</flux:text>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border">
                    <thead>
                        <tr class="bg-gray-50">
                            @foreach($csv_headers as $header)
                                <th class="px-3 py-2 border text-left font-medium">{{ \Str::limit($header, 20) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sample_data as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td class="px-3 py-2 border">{{ \Str::limit($cell, 15) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Field Mapping Interface --}}
        <div class="space-y-6">
            @foreach($this->getTikTokFields() as $section => $fields)
                <div>
                    <flux:text class="font-medium text-lg mb-3">{{ $section }}</flux:text>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($fields as $field => $label)
                            <div>
                                <flux:field>
                                    <flux:label>{{ $label }}</flux:label>
                                    <flux:select wire:model="field_mapping.{{ $field }}">
                                        <flux:select.option value="">-- Select CSV Column --</flux:select.option>
                                        @foreach($csv_headers as $index => $header)
                                            <flux:select.option value="{{ $index }}">{{ $header }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </flux:field>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-between mt-6">
            <flux:button variant="ghost" wire:click="resetImport">
                Reset Import
            </flux:button>
            <flux:button wire:click="confirmFieldMapping" variant="primary">
                Continue to Product Mapping
            </flux:button>
        </div>
    </div>
    @endif

    {{-- Step 3: Product Mapping --}}
    @if($current_step === 3)
    <div class="bg-white rounded-lg border p-6">
        <flux:heading size="lg" class="mb-4">Step 3: Map Products & SKUs</flux:heading>
        <flux:text class="mb-6">Map TikTok products to your system products for accurate inventory tracking.</flux:text>

        @if(count($suggested_mappings) > 0)
            <div class="mb-6">
                <flux:text class="font-medium mb-3">Suggested Product Mappings:</flux:text>
                <div class="space-y-3">
                    @foreach($suggested_mappings as $tikTokProduct => $suggestion)
                        <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                            <div>
                                <flux:text class="font-medium">{{ $tikTokProduct }}</flux:text>
                                <flux:text size="sm" class="text-green-600">→ {{ $suggestion['product_name'] }} ({{ $suggestion['confidence'] }}% match)</flux:text>
                            </div>
                            <flux:button size="sm" variant="outline">
                                Confirm Mapping
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(count($unmapped_skus) > 0)
            <div class="mb-6">
                <flux:text class="font-medium mb-3 text-amber-600">Unmapped Products ({{ count($unmapped_skus) }}):</flux:text>
                <div class="space-y-2">
                    @foreach($unmapped_skus as $sku)
                        <div class="flex items-center justify-between p-3 bg-amber-50 rounded-lg">
                            <flux:text>{{ $sku }}</flux:text>
                            <div class="flex gap-2">
                                <flux:button size="sm" variant="outline" wire:click="createProductFromSku('{{ $sku }}')">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="plus" class="w-4 h-4 mr-1" />
                                        Create New Product
                                    </div>
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="skipProduct('{{ $sku }}')">
                                    Skip
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex justify-between">
            <flux:button variant="ghost" wire:click="current_step = 2">
                Back to Field Mapping
            </flux:button>
            <flux:button wire:click="confirmProductMapping" variant="primary">
                Continue to Preview
            </flux:button>
        </div>
    </div>
    @endif

    {{-- Step 4: Preview --}}
    @if($current_step === 4)
    <div class="bg-white rounded-lg border p-6">
        <flux:heading size="lg" class="mb-4">Step 4: Import Preview</flux:heading>
        <flux:text class="mb-6">Review the first 5 orders to validate field mapping and product assignments before processing all {{ $total_rows }} orders.</flux:text>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="p-4 bg-blue-50 rounded-lg text-center">
                <flux:text size="2xl" class="font-bold text-blue-600">{{ $total_rows }}</flux:text>
                <flux:text size="sm" class="text-blue-600">Total Orders</flux:text>
            </div>
            <div class="p-4 bg-green-50 rounded-lg text-center">
                <flux:text size="2xl" class="font-bold text-green-600">{{ count($product_mappings) }}</flux:text>
                <flux:text size="sm" class="text-green-600">Mapped Products</flux:text>
            </div>
            <div class="p-4 bg-amber-50 rounded-lg text-center">
                <flux:text size="2xl" class="font-bold text-amber-600">{{ count($unmapped_skus) }}</flux:text>
                <flux:text size="sm" class="text-amber-600">Unmapped Products</flux:text>
            </div>
            <div class="p-4 bg-purple-50 rounded-lg text-center">
                <flux:text size="2xl" class="font-bold text-purple-600">
                    {{ collect($preview_data)->sum(fn($item) => count($item['validation_errors'])) }}
                </flux:text>
                <flux:text size="sm" class="text-purple-600">Validation Errors</flux:text>
            </div>
        </div>

        <!-- Preview Data Table -->
        @if(count($preview_data) > 0)
        <div class="mb-8">
            <flux:heading size="md" class="mb-4">Sample Orders Preview</flux:heading>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 border border-zinc-200 rounded-lg">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Row</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Order ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Mapped To</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Qty</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-zinc-200">
                        @foreach($preview_data as $item)
                        <tr class="{{ count($item['validation_errors']) > 0 ? 'bg-red-50' : (count($item['warnings']) > 0 ? 'bg-yellow-50' : '') }}">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-900">
                                {{ $item['row_number'] }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-900">
                                {{ $item['mapped_data']['order_id'] ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-900">
                                <div class="max-w-xs truncate">{{ $item['mapped_data']['product_name'] ?? 'N/A' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-900">
                                @if(isset($item['mapped_data']['internal_product_name']))
                                    <div class="text-green-600 font-medium">{{ $item['mapped_data']['internal_product_name'] }}</div>
                                    @if(isset($item['mapped_data']['internal_variant_name']))
                                        <div class="text-xs text-zinc-500">{{ $item['mapped_data']['internal_variant_name'] }}</div>
                                    @endif
                                @else
                                    <span class="text-red-500 font-medium">Not Mapped</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-900">
                                {{ $item['mapped_data']['customer_name'] ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-900">
                                {{ $item['mapped_data']['quantity'] ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-900">
                                {{ isset($item['mapped_data']['order_amount']) ? 'RM ' . number_format($item['mapped_data']['order_amount'], 2) : 'N/A' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                @if(count($item['validation_errors']) > 0)
                                    <flux:badge variant="red">
                                        {{ count($item['validation_errors']) }} Error{{ count($item['validation_errors']) > 1 ? 's' : '' }}
                                    </flux:badge>
                                @elseif(count($item['warnings']) > 0)
                                    <flux:badge variant="yellow">
                                        {{ count($item['warnings']) }} Warning{{ count($item['warnings']) > 1 ? 's' : '' }}
                                    </flux:badge>
                                @else
                                    <flux:badge variant="green">Valid</flux:badge>
                                @endif
                            </td>
                        </tr>

                        <!-- Error/Warning Details -->
                        @if(count($item['validation_errors']) > 0 || count($item['warnings']) > 0)
                        <tr class="{{ count($item['validation_errors']) > 0 ? 'bg-red-50' : 'bg-yellow-50' }}">
                            <td colspan="8" class="px-4 py-2">
                                @if(count($item['validation_errors']) > 0)
                                    <div class="mb-2">
                                        <flux:text size="sm" class="font-medium text-red-800">Validation Errors:</flux:text>
                                        <ul class="list-disc list-inside text-sm text-red-700 ml-2">
                                            @foreach($item['validation_errors'] as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if(count($item['warnings']) > 0)
                                    <div>
                                        <flux:text size="sm" class="font-medium text-yellow-800">Warnings:</flux:text>
                                        <ul class="list-disc list-inside text-sm text-yellow-700 ml-2">
                                            @foreach($item['warnings'] as $warning)
                                                <li>{{ $warning }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Field Mapping Summary -->
        <div class="mb-6">
            <flux:heading size="md" class="mb-4">Field Mapping Summary</flux:heading>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($field_mapping as $systemField => $csvColumn)
                    @if($csvColumn)
                    <div class="flex justify-between p-3 bg-zinc-50 rounded-lg">
                        <span class="font-medium text-zinc-900">{{ ucwords(str_replace('_', ' ', $systemField)) }}</span>
                        <span class="text-zinc-600">→ {{ $csvColumn }}</span>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Validation Summary -->
        @php
            $totalErrors = collect($preview_data)->sum(fn($item) => count($item['validation_errors']));
            $totalWarnings = collect($preview_data)->sum(fn($item) => count($item['warnings']));
        @endphp

        @if($totalErrors > 0)
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center mb-2">
                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 mr-2" />
                <flux:heading size="sm" class="text-red-800">Import Blocked - Validation Errors Found</flux:heading>
            </div>
            <flux:text size="sm" class="text-red-700">
                {{ $totalErrors }} validation error{{ $totalErrors > 1 ? 's' : '' }} found in the preview data.
                Please resolve these issues before proceeding with the import.
            </flux:text>
        </div>
        @elseif($totalWarnings > 0)
        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-center mb-2">
                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-yellow-500 mr-2" />
                <flux:heading size="sm" class="text-yellow-800">Warnings Found</flux:heading>
            </div>
            <flux:text size="sm" class="text-yellow-700">
                {{ $totalWarnings }} warning{{ $totalWarnings > 1 ? 's' : '' }} found in the preview data.
                You can proceed with the import, but please review the warnings above.
            </flux:text>
        </div>
        @else
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center mb-2">
                <flux:icon name="check-circle" class="w-5 h-5 text-green-500 mr-2" />
                <flux:heading size="sm" class="text-green-800">Ready to Import</flux:heading>
            </div>
            <flux:text size="sm" class="text-green-700">
                All preview data validation passed. Ready to import {{ $total_rows }} orders.
            </flux:text>
        </div>
        @endif

        <!-- Action Buttons -->
        <div class="flex justify-between">
            <flux:button variant="ghost" wire:click="current_step = 3">
                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                Back to Product Mapping
            </flux:button>

            <div class="flex space-x-2">
                <flux:button variant="outline" wire:click="showPreview">
                    <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                    Refresh Preview
                </flux:button>

                <flux:button
                    wire:click="executeImport"
                    variant="primary"
                    :disabled="$totalErrors > 0"
                >
                    <div class="flex items-center justify-center">
                        <flux:icon name="rocket-launch" class="w-4 h-4 mr-1" />
                        Start Import ({{ $total_rows }} orders)
                    </div>
                </flux:button>
            </div>
        </div>
    </div>
    @endif

    {{-- Step 5: Processing --}}
    @if($current_step === 5)
    <div class="bg-white rounded-lg border p-6">
        <flux:heading size="lg" class="mb-4">
            @if($importing)
                Processing TikTok Orders...
            @else
                Import Complete
            @endif
        </flux:heading>

        @if($importing)
            <div class="space-y-4">
                <div class="w-full bg-gray-200 rounded-full h-4">
                    <div class="bg-blue-600 h-4 rounded-full transition-all duration-300" style="width: {{ $import_progress }}%"></div>
                </div>
                <flux:text class="text-center">{{ $import_progress }}% Complete</flux:text>
            </div>
        @endif

        @if($import_results)
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

            <div class="flex justify-center">
                <flux:button wire:click="resetImport" variant="primary">
                    Import Another File
                </flux:button>
            </div>
        @endif
    </div>
    @endif
</div>