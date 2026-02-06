<?php

use App\Models\Package;
use App\Models\PendingPlatformProduct;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Platform $platform;
    public PlatformAccount $account;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'filter')]
    public string $statusFilter = 'pending';

    #[Url(as: 'confidence')]
    public string $confidenceFilter = '';

    // Linking modal (product)
    public bool $showLinkModal = false;
    public ?int $linkingProductId = null;
    public ?PendingPlatformProduct $linkingProduct = null;
    public string $productSearch = '';
    public ?int $selectedProductId = null;
    public ?int $selectedVariantId = null;

    // Package linking modal
    public bool $showPackageLinkModal = false;
    public ?PendingPlatformProduct $packageLinkingProduct = null;
    public string $packageSearch = '';
    public ?int $selectedPackageId = null;

    // Variant mapping modal
    public bool $showVariantLinkModal = false;
    public ?PendingPlatformProduct $variantLinkingProduct = null;
    public string $variantProductSearch = '';
    public string $variantPackageSearch = '';
    public ?string $activeVariantSku = null;
    public string $variantLinkType = 'product'; // 'product' or 'package'
    public array $variantMappings = [];
    public array $originalVariantMappingSkus = []; // SKUs that had mappings when modal opened

    // Stats
    public array $stats = [];

    public function mount(Platform $platform, PlatformAccount $account): void
    {
        $this->platform = $platform;
        $this->account = $account;

        if ($this->account->platform_id !== $this->platform->id) {
            abort(404);
        }

        $this->loadStats();
    }

    public function loadStats(): void
    {
        $this->stats = PendingPlatformProduct::getStatsForAccount($this->account->id);
    }

    public function getPendingProductsProperty()
    {
        return PendingPlatformProduct::forAccount($this->account->id)
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('platform_sku', 'like', "%{$this->search}%")
                        ->orWhere('platform_product_id', 'like', "%{$this->search}%");
                });
            })
            ->when($this->confidenceFilter === 'high', fn ($q) => $q->highConfidence(90))
            ->when($this->confidenceFilter === 'medium', fn ($q) => $q->whereBetween('match_confidence', [70, 89.99]))
            ->when($this->confidenceFilter === 'low', fn ($q) => $q->where('match_confidence', '<', 70)->whereNotNull('match_confidence'))
            ->when($this->confidenceFilter === 'none', fn ($q) => $q->whereNull('suggested_product_id')->whereNull('suggested_package_id'))
            ->with(['suggestedProduct', 'suggestedVariant', 'suggestedPackage'])
            ->orderByDesc('match_confidence')
            ->orderBy('name')
            ->paginate(15);
    }

    public function getSearchResultsProperty()
    {
        if (strlen($this->productSearch) < 2) {
            return collect();
        }

        return Product::where('status', 'active')
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->productSearch}%")
                    ->orWhere('sku', 'like', "%{$this->productSearch}%");
            })
            ->with('variants')
            ->limit(20)
            ->get();
    }

    public function getPackageSearchResultsProperty()
    {
        if (strlen($this->packageSearch) < 2) {
            return collect();
        }

        return Package::where('status', 'active')
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->packageSearch}%")
                    ->orWhere('slug', 'like', "%{$this->packageSearch}%");
            })
            ->with('items.itemable')
            ->limit(20)
            ->get();
    }

    public function getVariantProductSearchResultsProperty()
    {
        if (strlen($this->variantProductSearch) < 2) {
            return collect();
        }

        return Product::where('status', 'active')
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->variantProductSearch}%")
                    ->orWhere('sku', 'like', "%{$this->variantProductSearch}%");
            })
            ->with('variants')
            ->limit(20)
            ->get();
    }

    public function getVariantPackageSearchResultsProperty()
    {
        if (strlen($this->variantPackageSearch) < 2) {
            return collect();
        }

        return Package::where('status', 'active')
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->variantPackageSearch}%")
                    ->orWhere('slug', 'like', "%{$this->variantPackageSearch}%");
            })
            ->limit(20)
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedConfidenceFilter(): void
    {
        $this->resetPage();
    }

    // --- Product Link Modal ---

    public function openLinkModal(int $pendingProductId): void
    {
        $this->linkingProductId = $pendingProductId;
        $this->linkingProduct = PendingPlatformProduct::with(['suggestedProduct', 'suggestedVariant'])->find($pendingProductId);
        $this->productSearch = '';
        $this->selectedProductId = $this->linkingProduct?->suggested_product_id;
        $this->selectedVariantId = $this->linkingProduct?->suggested_variant_id;
        $this->showLinkModal = true;
    }

    public function closeLinkModal(): void
    {
        $this->showLinkModal = false;
        $this->linkingProductId = null;
        $this->linkingProduct = null;
        $this->productSearch = '';
        $this->selectedProductId = null;
        $this->selectedVariantId = null;
    }

    public function selectProduct(int $productId, ?int $variantId = null): void
    {
        $this->selectedProductId = $productId;
        $this->selectedVariantId = $variantId;
    }

    public function confirmLink(): void
    {
        if (! $this->linkingProduct || ! $this->selectedProductId) {
            return;
        }

        $product = Product::find($this->selectedProductId);
        $variant = $this->selectedVariantId ? ProductVariant::find($this->selectedVariantId) : null;

        if (! $product) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Product not found.',
            ]);
            return;
        }

        $this->linkingProduct->linkToProduct($product, $variant, auth()->id());

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Linked \"{$this->linkingProduct->name}\" to \"{$product->name}\"",
        ]);

        $this->closeLinkModal();
        $this->loadStats();
    }

    // --- Package Link Modal ---

    public function openPackageLinkModal(int $pendingProductId): void
    {
        $this->packageLinkingProduct = PendingPlatformProduct::with(['suggestedPackage'])->find($pendingProductId);
        $this->packageSearch = '';
        $this->selectedPackageId = $this->packageLinkingProduct?->suggested_package_id;
        $this->showPackageLinkModal = true;
    }

    public function closePackageLinkModal(): void
    {
        $this->showPackageLinkModal = false;
        $this->packageLinkingProduct = null;
        $this->packageSearch = '';
        $this->selectedPackageId = null;
    }

    public function selectPackage(int $packageId): void
    {
        $this->selectedPackageId = $packageId;
    }

    public function confirmPackageLink(): void
    {
        if (! $this->packageLinkingProduct || ! $this->selectedPackageId) {
            return;
        }

        $package = Package::find($this->selectedPackageId);

        if (! $package) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Package not found.',
            ]);
            return;
        }

        $this->packageLinkingProduct->linkToPackage($package, auth()->id());

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Linked \"{$this->packageLinkingProduct->name}\" to package \"{$package->name}\"",
        ]);

        $this->closePackageLinkModal();
        $this->loadStats();
    }

    // --- Variant Mapping Modal ---

    public function openVariantLinkModal(int $pendingProductId): void
    {
        $this->variantLinkingProduct = PendingPlatformProduct::find($pendingProductId);
        $this->variantMappings = [];
        $this->activeVariantSku = null;
        $this->variantProductSearch = '';
        $this->variantPackageSearch = '';
        $this->variantLinkType = 'product';

        // Load existing mappings for each variant
        if ($this->variantLinkingProduct && $this->variantLinkingProduct->hasVariants()) {
            foreach ($this->variantLinkingProduct->variants as $variant) {
                $sku = $variant['sku'] ?? null;
                if (! $sku) {
                    continue;
                }

                $existingMapping = PlatformSkuMapping::findMapping(
                    $this->variantLinkingProduct->platform_id,
                    $this->variantLinkingProduct->platform_account_id,
                    $sku
                );

                if ($existingMapping) {
                    if ($existingMapping->isPackageMapping()) {
                        $this->variantMappings[$sku] = [
                            'type' => 'package',
                            'package_id' => $existingMapping->package_id,
                            'package_name' => $existingMapping->package?->name,
                        ];
                    } else {
                        $this->variantMappings[$sku] = [
                            'type' => 'product',
                            'product_id' => $existingMapping->product_id,
                            'variant_id' => $existingMapping->product_variant_id,
                            'product_name' => $existingMapping->product?->name,
                            'variant_name' => $existingMapping->productVariant?->name,
                        ];
                    }
                }
            }
        }

        // Track which SKUs had mappings originally (for detecting removals on save)
        $this->originalVariantMappingSkus = array_keys($this->variantMappings);

        $this->showVariantLinkModal = true;
    }

    public function closeVariantLinkModal(): void
    {
        $this->showVariantLinkModal = false;
        $this->variantLinkingProduct = null;
        $this->variantMappings = [];
        $this->activeVariantSku = null;
        $this->variantProductSearch = '';
        $this->variantPackageSearch = '';
    }

    public function setActiveVariant(string $sku): void
    {
        $this->activeVariantSku = $sku;
        $this->variantProductSearch = '';
        $this->variantPackageSearch = '';
        $this->variantLinkType = 'product';
    }

    public function setVariantProductMapping(string $sku, int $productId, ?int $variantId = null): void
    {
        $product = Product::find($productId);

        $this->variantMappings[$sku] = [
            'type' => 'product',
            'product_id' => $productId,
            'variant_id' => $variantId,
            'product_name' => $product?->name,
            'variant_name' => $variantId ? ProductVariant::find($variantId)?->name : null,
        ];

        $this->activeVariantSku = null;
        $this->variantProductSearch = '';
    }

    public function setVariantPackageMapping(string $sku, int $packageId): void
    {
        $package = Package::find($packageId);

        $this->variantMappings[$sku] = [
            'type' => 'package',
            'package_id' => $packageId,
            'package_name' => $package?->name,
        ];

        $this->activeVariantSku = null;
        $this->variantPackageSearch = '';
    }

    public function removeVariantMapping(string $sku): void
    {
        unset($this->variantMappings[$sku]);
    }

    public function confirmVariantLinks(): void
    {
        if (! $this->variantLinkingProduct) {
            return;
        }

        $count = 0;

        // Deactivate mappings that were removed
        $removedSkus = array_diff($this->originalVariantMappingSkus, array_keys($this->variantMappings));
        if (! empty($removedSkus)) {
            PlatformSkuMapping::where('platform_account_id', $this->variantLinkingProduct->platform_account_id)
                ->whereIn('platform_sku', $removedSkus)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        // Create/update mappings
        foreach ($this->variantMappings as $sku => $mapping) {
            if ($mapping['type'] === 'package') {
                $package = Package::find($mapping['package_id']);
                if ($package) {
                    $this->variantLinkingProduct->linkVariantSku($sku, null, null, $package, auth()->id());
                    $count++;
                }
            } else {
                $product = Product::find($mapping['product_id'] ?? null);
                $variant = isset($mapping['variant_id']) ? ProductVariant::find($mapping['variant_id']) : null;
                if ($product) {
                    $this->variantLinkingProduct->linkVariantSku($sku, $product, $variant, null, auth()->id());
                    $count++;
                }
            }
        }

        $removedCount = count($removedSkus);
        $message = "Mapped {$count} variant SKU(s) successfully.";
        if ($removedCount > 0) {
            $message .= " Removed {$removedCount} mapping(s).";
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message,
        ]);

        $this->closeVariantLinkModal();
        $this->loadStats();
    }

    // --- Suggestions ---

    public function acceptSuggestion(int $pendingProductId): void
    {
        $pending = PendingPlatformProduct::with(['suggestedProduct', 'suggestedVariant', 'suggestedPackage'])->find($pendingProductId);

        if (! $pending) {
            return;
        }

        // Handle package suggestion
        if ($pending->hasPackageSuggestion()) {
            $pending->linkToPackage($pending->suggestedPackage, auth()->id());

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Linked \"{$pending->name}\" to package \"{$pending->suggestedPackage->name}\"",
            ]);

            $this->loadStats();
            return;
        }

        // Handle product suggestion
        if (! $pending->suggestedProduct) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'No suggestion available for this product.',
            ]);
            return;
        }

        $pending->linkToProduct($pending->suggestedProduct, $pending->suggestedVariant, auth()->id());

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Linked \"{$pending->name}\" to \"{$pending->suggestedProduct->name}\"",
        ]);

        $this->loadStats();
    }

    public function createAsNew(int $pendingProductId): void
    {
        $pending = PendingPlatformProduct::find($pendingProductId);

        if (! $pending) {
            return;
        }

        $product = $pending->createAsNewProduct(auth()->id());

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Created new product \"{$product->name}\" from TikTok data.",
        ]);

        $this->loadStats();
    }

    public function ignoreProduct(int $pendingProductId): void
    {
        $pending = PendingPlatformProduct::find($pendingProductId);

        if (! $pending) {
            return;
        }

        $pending->ignore(auth()->id());

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => "Ignored \"{$pending->name}\".",
        ]);

        $this->loadStats();
    }

    public function unlinkProduct(int $pendingProductId): void
    {
        $pending = PendingPlatformProduct::find($pendingProductId);

        if (! $pending) {
            return;
        }

        // Deactivate any associated SKU mappings
        PlatformSkuMapping::where('platform_account_id', $pending->platform_account_id)
            ->where(function ($q) use ($pending) {
                $q->where('platform_sku', $pending->platform_sku ?? $pending->platform_product_id);
            })
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Also deactivate variant mappings if applicable
        if ($pending->hasVariants()) {
            $variantSkus = collect($pending->variants)->pluck('sku')->filter()->toArray();
            if (! empty($variantSkus)) {
                PlatformSkuMapping::where('platform_account_id', $pending->platform_account_id)
                    ->whereIn('platform_sku', $variantSkus)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        }

        $pending->resetToPending();

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => "Unlinked \"{$pending->name}\". It is now pending review again.",
        ]);

        $this->loadStats();
    }

    public function acceptAllHighConfidence(): void
    {
        $highConfidence = PendingPlatformProduct::forAccount($this->account->id)
            ->pending()
            ->highConfidence(90)
            ->with(['suggestedProduct', 'suggestedVariant', 'suggestedPackage'])
            ->get();

        $count = 0;
        foreach ($highConfidence as $pending) {
            if ($pending->hasPackageSuggestion()) {
                $pending->linkToPackage($pending->suggestedPackage, auth()->id());
                $count++;
            } elseif ($pending->suggestedProduct) {
                $pending->linkToProduct($pending->suggestedProduct, $pending->suggestedVariant, auth()->id());
                $count++;
            }
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Accepted {$count} high-confidence matches.",
        ]);

        $this->loadStats();
    }

    public function getConfidenceBadgeColor(?float $confidence): string
    {
        if ($confidence === null || $confidence < 70) {
            return 'red';
        }
        if ($confidence >= 90) {
            return 'green';
        }
        return 'amber';
    }
}; ?>

<div>
    {{-- Breadcrumb Navigation --}}
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <flux:button variant="ghost" size="sm" :href="route('platforms.accounts.show', [$platform, $account])" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                            {{ $account->name }}
                        </div>
                    </flux:button>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <span class="ml-4 text-sm font-medium text-zinc-500">Pending Products</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Pending Products</flux:heading>
            <flux:text class="mt-2">Review and link TikTok products to your internal catalog</flux:text>
        </div>
        @if($stats['high_confidence'] > 0)
            <flux:button
                variant="primary"
                wire:click="acceptAllHighConfidence"
                wire:confirm="This will link {{ $stats['high_confidence'] }} high-confidence matches automatically. Continue?"
            >
                <div class="flex items-center justify-center">
                    <flux:icon name="check-circle" class="w-4 h-4 mr-2" />
                    Accept All High Confidence ({{ $stats['high_confidence'] }})
                </div>
            </flux:button>
        @endif
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="text-2xl font-bold text-amber-600">{{ $stats['pending'] ?? 0 }}</div>
            <div class="text-sm text-zinc-500">Pending Review</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="text-2xl font-bold text-green-600">{{ $stats['linked'] ?? 0 }}</div>
            <div class="text-sm text-zinc-500">Linked</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="text-2xl font-bold text-blue-600">{{ $stats['created'] ?? 0 }}</div>
            <div class="text-sm text-zinc-500">Created</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="text-2xl font-bold text-zinc-400">{{ $stats['ignored'] ?? 0 }}</div>
            <div class="text-sm text-zinc-500">Ignored</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="text-2xl font-bold text-purple-600">{{ $stats['with_suggestions'] ?? 0 }}</div>
            <div class="text-sm text-zinc-500">With Suggestions</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="text-2xl font-bold text-green-600">{{ $stats['high_confidence'] ?? 0 }}</div>
            <div class="text-sm text-zinc-500">High Confidence</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4 mb-6">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <flux:input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by name, SKU, or product ID..."
                />
            </div>
            <flux:select wire:model.live="statusFilter" class="w-full md:w-40">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="linked">Linked</option>
                <option value="created">Created</option>
                <option value="ignored">Ignored</option>
            </flux:select>
            <flux:select wire:model.live="confidenceFilter" class="w-full md:w-48">
                <option value="">All Confidence</option>
                <option value="high">High (90%+)</option>
                <option value="medium">Medium (70-89%)</option>
                <option value="low">Low (&lt;70%)</option>
                <option value="none">No Suggestion</option>
            </flux:select>
        </div>
    </div>

    {{-- Products List --}}
    <div class="space-y-4">
        @forelse($this->pendingProducts as $pending)
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4" wire:key="pending-{{ $pending->id }}">
                <div class="flex flex-col lg:flex-row gap-4">
                    {{-- Product Image --}}
                    <div class="flex-shrink-0">
                        @if($pending->main_image_url)
                            <img
                                src="{{ $pending->main_image_url }}"
                                alt="{{ $pending->name }}"
                                class="w-20 h-20 object-cover rounded-lg"
                            >
                        @else
                            <div class="w-20 h-20 bg-gray-100 dark:bg-zinc-700 rounded-lg flex items-center justify-center">
                                <flux:icon name="photo" class="w-8 h-8 text-gray-400" />
                            </div>
                        @endif
                    </div>

                    {{-- Product Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                @if(in_array($pending->status, ['created', 'linked']) && $pending->suggested_package_id)
                                    <a href="{{ route('packages.show', $pending->suggested_package_id) }}" wire:navigate class="font-medium text-gray-900 dark:text-zinc-100 truncate hover:text-purple-600 dark:hover:text-purple-400 transition-colors">
                                        {{ $pending->name }}
                                    </a>
                                @elseif(in_array($pending->status, ['created', 'linked']) && $pending->suggested_product_id)
                                    <a href="{{ route('products.show', $pending->suggested_product_id) }}" wire:navigate class="font-medium text-gray-900 dark:text-zinc-100 truncate hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                        {{ $pending->name }}
                                    </a>
                                @else
                                    <h3 class="font-medium text-gray-900 dark:text-zinc-100 truncate">{{ $pending->name }}</h3>
                                @endif
                                <div class="mt-1 flex items-center gap-3 text-sm text-zinc-500">
                                    @if($pending->platform_sku)
                                        <span class="font-mono">SKU: {{ $pending->platform_sku }}</span>
                                    @endif
                                    @if($pending->price)
                                        <span>{{ $pending->getFormattedPrice() }}</span>
                                    @endif
                                    @if($pending->hasVariants())
                                        <flux:badge size="sm" color="blue">{{ $pending->getVariantCount() }} variants</flux:badge>
                                    @endif
                                </div>
                            </div>

                            <flux:badge size="sm" color="{{ $pending->status === 'pending' ? 'amber' : ($pending->status === 'linked' ? 'green' : ($pending->status === 'created' ? 'blue' : 'zinc')) }}">
                                {{ ucfirst($pending->status) }}
                            </flux:badge>
                        </div>

                        {{-- Match Suggestion --}}
                        @if($pending->hasSuggestion() && $pending->isPending())
                            @if($pending->hasPackageSuggestion())
                                {{-- Package Suggestion --}}
                                <div class="mt-3 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <flux:icon name="cube-transparent" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                                            <div>
                                                <p class="text-sm font-medium text-purple-800 dark:text-purple-200">
                                                    <flux:badge size="sm" color="purple" class="mr-1">Package</flux:badge>
                                                    Suggested: {{ $pending->suggestedPackage?->name }}
                                                </p>
                                                <p class="text-xs text-purple-600 dark:text-purple-400">
                                                    {{ $pending->match_reason }}
                                                </p>
                                            </div>
                                        </div>
                                        <flux:badge size="sm" color="{{ $this->getConfidenceBadgeColor($pending->match_confidence) }}">
                                            {{ number_format($pending->match_confidence, 0) }}% match
                                        </flux:badge>
                                    </div>
                                </div>
                            @else
                                {{-- Product Suggestion --}}
                                <div class="mt-3 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <flux:icon name="sparkles" class="w-5 h-5 text-green-600 dark:text-green-400" />
                                            <div>
                                                <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                                    Suggested: {{ $pending->suggestedProduct?->name }}
                                                    @if($pending->suggestedVariant)
                                                        <span class="text-green-600 dark:text-green-400">- {{ $pending->suggestedVariant->name }}</span>
                                                    @endif
                                                </p>
                                                <p class="text-xs text-green-600 dark:text-green-400">
                                                    {{ $pending->match_reason }}
                                                </p>
                                            </div>
                                        </div>
                                        <flux:badge size="sm" color="{{ $this->getConfidenceBadgeColor($pending->match_confidence) }}">
                                            {{ number_format($pending->match_confidence, 0) }}% match
                                        </flux:badge>
                                    </div>
                                </div>
                            @endif
                        @endif

                        {{-- Actions --}}
                        @if($pending->isPending())
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if($pending->hasSuggestion())
                                    <flux:button size="sm" variant="primary" wire:click="acceptSuggestion({{ $pending->id }})">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="check" class="w-4 h-4 mr-1" />
                                            Accept Suggestion
                                        </div>
                                    </flux:button>
                                @endif

                                <flux:button size="sm" variant="outline" wire:click="openLinkModal({{ $pending->id }})">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="link" class="w-4 h-4 mr-1" />
                                        Link to Product
                                    </div>
                                </flux:button>

                                <flux:button size="sm" variant="outline" wire:click="openPackageLinkModal({{ $pending->id }})">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="cube-transparent" class="w-4 h-4 mr-1" />
                                        Link to Package
                                    </div>
                                </flux:button>

                                @if($pending->hasVariants())
                                    <flux:button size="sm" variant="outline" wire:click="openVariantLinkModal({{ $pending->id }})">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="squares-2x2" class="w-4 h-4 mr-1" />
                                            Map Variants ({{ $pending->getVariantCount() }})
                                        </div>
                                    </flux:button>
                                @endif

                                <flux:button size="sm" variant="outline" wire:click="createAsNew({{ $pending->id }})" wire:confirm="Create a new internal product from this TikTok product?">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="plus" class="w-4 h-4 mr-1" />
                                        Create New
                                    </div>
                                </flux:button>

                                <flux:button size="sm" variant="ghost" wire:click="ignoreProduct({{ $pending->id }})">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                                        Ignore
                                    </div>
                                </flux:button>
                            </div>
                        @elseif($pending->isLinked() || $pending->status === 'created')
                            <div class="mt-3 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-sm text-zinc-500">
                                    <span>Processed {{ $pending->reviewed_at?->diffForHumans() }}</span>
                                    @if($pending->suggested_package_id)
                                        <span>&middot;</span>
                                        <a href="{{ route('packages.show', $pending->suggested_package_id) }}" wire:navigate class="text-purple-600 dark:text-purple-400 hover:underline">
                                            View Package
                                        </a>
                                    @elseif($pending->suggested_product_id)
                                        <span>&middot;</span>
                                        <a href="{{ route('products.show', $pending->suggested_product_id) }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:underline">
                                            View Product
                                        </a>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($pending->suggested_package_id)
                                        <flux:button size="sm" variant="outline" wire:click="openPackageLinkModal({{ $pending->id }})">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                                                Change Package
                                            </div>
                                        </flux:button>
                                    @elseif($pending->suggested_product_id)
                                        <flux:button size="sm" variant="outline" wire:click="openLinkModal({{ $pending->id }})">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                                                Change Product
                                            </div>
                                        </flux:button>
                                    @endif
                                    <flux:button size="sm" variant="ghost" wire:click="unlinkProduct({{ $pending->id }})" wire:confirm="Unlink this product and return it to pending review?">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="no-symbol" class="w-4 h-4 mr-1" />
                                            Unlink
                                        </div>
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-12 text-center">
                <flux:icon name="inbox" class="w-12 h-12 mx-auto text-gray-400 mb-4" />
                <flux:heading size="lg">No products found</flux:heading>
                <flux:text class="mt-2 text-zinc-500">
                    @if($statusFilter === 'pending')
                        All pending products have been reviewed.
                    @else
                        No products match your current filters.
                    @endif
                </flux:text>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($this->pendingProducts->hasPages())
        <div class="mt-6">
            {{ $this->pendingProducts->links() }}
        </div>
    @endif

    {{-- Link to Product Modal --}}
    <flux:modal wire:model="showLinkModal" class="max-w-2xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Link to Internal Product</flux:heading>

            @if($linkingProduct)
                <div class="bg-gray-50 dark:bg-zinc-900 rounded-lg p-4 mb-6">
                    <div class="flex items-center gap-4">
                        @if($linkingProduct->main_image_url)
                            <img src="{{ $linkingProduct->main_image_url }}" alt="{{ $linkingProduct->name }}" class="w-16 h-16 object-cover rounded-lg">
                        @endif
                        <div>
                            <p class="font-medium text-gray-900 dark:text-zinc-100">{{ $linkingProduct->name }}</p>
                            <p class="text-sm text-zinc-500">
                                @if($linkingProduct->platform_sku)
                                    SKU: {{ $linkingProduct->platform_sku }} |
                                @endif
                                {{ $linkingProduct->getFormattedPrice() }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <flux:field>
                        <flux:label>Search Internal Products</flux:label>
                        <flux:input
                            type="text"
                            wire:model.live.debounce.300ms="productSearch"
                            placeholder="Search by name or SKU..."
                        />
                    </flux:field>
                </div>

                @if($this->searchResults->count() > 0)
                    <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-zinc-700 rounded-lg divide-y divide-gray-200 dark:divide-zinc-700">
                        @foreach($this->searchResults as $product)
                            <div
                                class="p-3 hover:bg-gray-50 dark:hover:bg-zinc-800 cursor-pointer {{ $selectedProductId === $product->id && !$selectedVariantId ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}"
                                wire:click="selectProduct({{ $product->id }})"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-zinc-100">{{ $product->name }}</p>
                                        <p class="text-sm text-zinc-500">SKU: {{ $product->sku }} | {{ $product->currency ?? 'MYR' }} {{ number_format($product->base_price, 2) }}</p>
                                    </div>
                                    @if($selectedProductId === $product->id && !$selectedVariantId)
                                        <flux:icon name="check-circle" class="w-5 h-5 text-blue-600" />
                                    @endif
                                </div>

                                @if($product->variants->count() > 0)
                                    <div class="mt-2 pl-4 space-y-1">
                                        @foreach($product->variants as $variant)
                                            <div
                                                class="p-2 rounded text-sm hover:bg-gray-100 dark:hover:bg-zinc-700 {{ $selectedProductId === $product->id && $selectedVariantId === $variant->id ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}"
                                                wire:click.stop="selectProduct({{ $product->id }}, {{ $variant->id }})"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <span>{{ $variant->name }} ({{ $variant->sku }})</span>
                                                    @if($selectedProductId === $product->id && $selectedVariantId === $variant->id)
                                                        <flux:icon name="check-circle" class="w-4 h-4 text-blue-600" />
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @elseif(strlen($productSearch) >= 2)
                    <div class="p-4 text-center text-zinc-500 border border-gray-200 dark:border-zinc-700 rounded-lg">
                        No products found matching "{{ $productSearch }}"
                    </div>
                @else
                    <div class="p-4 text-center text-zinc-500 border border-gray-200 dark:border-zinc-700 rounded-lg">
                        Type at least 2 characters to search
                    </div>
                @endif

                @if($selectedProductId)
                    @php
                        $selectedProduct = App\Models\Product::find($selectedProductId);
                        $selectedVariant = $selectedVariantId ? App\Models\ProductVariant::find($selectedVariantId) : null;
                    @endphp
                    @if($selectedProduct)
                        <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <p class="text-sm text-blue-800 dark:text-blue-200">
                                <strong>Selected:</strong> {{ $selectedProduct->name }}
                                @if($selectedVariant)
                                    - {{ $selectedVariant->name }}
                                @endif
                            </p>
                        </div>
                    @endif
                @endif
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeLinkModal">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="confirmLink" :disabled="!$selectedProductId">
                    <div class="flex items-center justify-center">
                        <flux:icon name="link" class="w-4 h-4 mr-2" />
                        Link Products
                    </div>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Link to Package Modal --}}
    <flux:modal wire:model="showPackageLinkModal" class="max-w-2xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Link to Package</flux:heading>

            @if($packageLinkingProduct)
                <div class="bg-gray-50 dark:bg-zinc-900 rounded-lg p-4 mb-6">
                    <div class="flex items-center gap-4">
                        @if($packageLinkingProduct->main_image_url)
                            <img src="{{ $packageLinkingProduct->main_image_url }}" alt="{{ $packageLinkingProduct->name }}" class="w-16 h-16 object-cover rounded-lg">
                        @endif
                        <div>
                            <p class="font-medium text-gray-900 dark:text-zinc-100">{{ $packageLinkingProduct->name }}</p>
                            <p class="text-sm text-zinc-500">
                                @if($packageLinkingProduct->platform_sku)
                                    SKU: {{ $packageLinkingProduct->platform_sku }} |
                                @endif
                                {{ $packageLinkingProduct->getFormattedPrice() }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <flux:field>
                        <flux:label>Search Packages</flux:label>
                        <flux:input
                            type="text"
                            wire:model.live.debounce.300ms="packageSearch"
                            placeholder="Search by package name..."
                        />
                    </flux:field>
                </div>

                @if($this->packageSearchResults->count() > 0)
                    <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-zinc-700 rounded-lg divide-y divide-gray-200 dark:divide-zinc-700">
                        @foreach($this->packageSearchResults as $package)
                            <div
                                class="p-3 hover:bg-gray-50 dark:hover:bg-zinc-800 cursor-pointer {{ $selectedPackageId === $package->id ? 'bg-purple-50 dark:bg-purple-900/20' : '' }}"
                                wire:click="selectPackage({{ $package->id }})"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-zinc-100">{{ $package->name }}</p>
                                        <p class="text-sm text-zinc-500">
                                            MYR {{ number_format($package->price, 2) }}
                                            @if($package->items->count() > 0)
                                                | {{ $package->items->count() }} item(s)
                                            @endif
                                        </p>
                                        {{-- Show package items --}}
                                        @if($package->items->count() > 0)
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach($package->items->take(5) as $item)
                                                    <flux:badge size="sm" color="zinc">{{ $item->itemable?->name ?? 'Unknown' }}</flux:badge>
                                                @endforeach
                                                @if($package->items->count() > 5)
                                                    <flux:badge size="sm" color="zinc">+{{ $package->items->count() - 5 }} more</flux:badge>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    @if($selectedPackageId === $package->id)
                                        <flux:icon name="check-circle" class="w-5 h-5 text-purple-600" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif(strlen($packageSearch) >= 2)
                    <div class="p-4 text-center text-zinc-500 border border-gray-200 dark:border-zinc-700 rounded-lg">
                        No packages found matching "{{ $packageSearch }}"
                    </div>
                @else
                    <div class="p-4 text-center text-zinc-500 border border-gray-200 dark:border-zinc-700 rounded-lg">
                        Type at least 2 characters to search
                    </div>
                @endif

                @if($selectedPackageId)
                    @php $selectedPkg = App\Models\Package::find($selectedPackageId); @endphp
                    @if($selectedPkg)
                        <div class="mt-4 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                            <p class="text-sm text-purple-800 dark:text-purple-200">
                                <strong>Selected:</strong> {{ $selectedPkg->name }}
                            </p>
                        </div>
                    @endif
                @endif
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closePackageLinkModal">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="confirmPackageLink" :disabled="!$selectedPackageId">
                    <div class="flex items-center justify-center">
                        <flux:icon name="cube-transparent" class="w-4 h-4 mr-2" />
                        Link to Package
                    </div>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Variant Mapping Modal --}}
    <flux:modal wire:model="showVariantLinkModal" class="max-w-3xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-2">Map Variant SKUs</flux:heading>
            <flux:text class="mb-6 text-zinc-500">Map each TikTok variant to an internal product or package</flux:text>

            @if($variantLinkingProduct && $variantLinkingProduct->hasVariants())
                {{-- Variant List --}}
                <div class="space-y-3">
                    @foreach($variantLinkingProduct->variants as $index => $variant)
                        @php
                            $vSku = !empty($variant['sku']) ? $variant['sku'] : ($variant['sku_id'] ?? null);
                            $vName = $variant['name']
                                ?? (collect($variant['attributes'] ?? [])->pluck('value')->filter()->implode(' / ') ?: null);
                        @endphp
                        @if($vSku)
                            <div class="border border-gray-200 dark:border-zinc-700 rounded-lg p-4" wire:key="variant-{{ $index }}">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-zinc-100">
                                            {{ $vName ?? "Variant " . ($index + 1) }}
                                        </p>
                                        <p class="text-sm text-zinc-500">
                                            SKU: <span class="font-mono text-xs">{{ $vSku }}</span>
                                            @if(!empty($variant['price']))
                                                | MYR {{ number_format((float) $variant['price'], 2) }}
                                            @endif
                                            @if(isset($variant['quantity']))
                                                | Qty: {{ $variant['quantity'] }}
                                            @endif
                                        </p>
                                    </div>

                                    {{-- Mapping Status --}}
                                    @if(isset($variantMappings[$vSku]))
                                        <div class="flex items-center gap-2">
                                            @if($variantMappings[$vSku]['type'] === 'package')
                                                <flux:badge size="sm" color="purple">Package: {{ $variantMappings[$vSku]['package_name'] ?? 'Unknown' }}</flux:badge>
                                            @else
                                                <flux:badge size="sm" color="green">
                                                    {{ $variantMappings[$vSku]['product_name'] ?? 'Unknown' }}
                                                    @if($variantMappings[$vSku]['variant_name'] ?? null)
                                                        - {{ $variantMappings[$vSku]['variant_name'] }}
                                                    @endif
                                                </flux:badge>
                                            @endif
                                            <button wire:click="removeVariantMapping('{{ $vSku }}')" class="text-zinc-400 hover:text-red-500">
                                                <flux:icon name="x-mark" class="w-4 h-4" />
                                            </button>
                                        </div>
                                    @else
                                        <flux:button size="sm" variant="outline" wire:click="setActiveVariant('{{ $vSku }}')">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="link" class="w-4 h-4 mr-1" />
                                                Map
                                            </div>
                                        </flux:button>
                                    @endif
                                </div>

                                {{-- Inline mapping search (shown when this variant is active) --}}
                                @if($activeVariantSku === $vSku)
                                    <div class="mt-3 border-t border-gray-200 dark:border-zinc-700 pt-3">
                                        {{-- Toggle between Product and Package --}}
                                        <div class="flex gap-2 mb-3">
                                            <flux:button size="sm" variant="{{ $variantLinkType === 'product' ? 'primary' : 'outline' }}" wire:click="$set('variantLinkType', 'product')">
                                                Product
                                            </flux:button>
                                            <flux:button size="sm" variant="{{ $variantLinkType === 'package' ? 'primary' : 'outline' }}" wire:click="$set('variantLinkType', 'package')">
                                                Package
                                            </flux:button>
                                        </div>

                                        @if($variantLinkType === 'product')
                                            <flux:input
                                                type="text"
                                                wire:model.live.debounce.300ms="variantProductSearch"
                                                placeholder="Search products..."
                                                size="sm"
                                            />
                                            @if($this->variantProductSearchResults->count() > 0)
                                                <div class="mt-2 max-h-48 overflow-y-auto border border-gray-200 dark:border-zinc-700 rounded-lg divide-y divide-gray-200 dark:divide-zinc-700">
                                                    @foreach($this->variantProductSearchResults as $product)
                                                        <div class="p-2 hover:bg-gray-50 dark:hover:bg-zinc-800 cursor-pointer text-sm"
                                                             wire:click="setVariantProductMapping('{{ $vSku }}', {{ $product->id }})">
                                                            <p class="font-medium text-gray-900 dark:text-zinc-100">{{ $product->name }}</p>
                                                            <p class="text-xs text-zinc-500">SKU: {{ $product->sku }}</p>

                                                            @if($product->variants->count() > 0)
                                                                <div class="mt-1 pl-3 space-y-1">
                                                                    @foreach($product->variants as $pv)
                                                                        <div class="p-1 rounded text-xs hover:bg-gray-100 dark:hover:bg-zinc-700 cursor-pointer"
                                                                             wire:click.stop="setVariantProductMapping('{{ $vSku }}', {{ $product->id }}, {{ $pv->id }})">
                                                                            {{ $pv->name }} ({{ $pv->sku }})
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif(strlen($variantProductSearch) >= 2)
                                                <p class="mt-2 text-xs text-zinc-500">No products found</p>
                                            @endif
                                        @else
                                            <flux:input
                                                type="text"
                                                wire:model.live.debounce.300ms="variantPackageSearch"
                                                placeholder="Search packages..."
                                                size="sm"
                                            />
                                            @if($this->variantPackageSearchResults->count() > 0)
                                                <div class="mt-2 max-h-48 overflow-y-auto border border-gray-200 dark:border-zinc-700 rounded-lg divide-y divide-gray-200 dark:divide-zinc-700">
                                                    @foreach($this->variantPackageSearchResults as $package)
                                                        <div class="p-2 hover:bg-gray-50 dark:hover:bg-zinc-800 cursor-pointer text-sm"
                                                             wire:click="setVariantPackageMapping('{{ $vSku }}', {{ $package->id }})">
                                                            <p class="font-medium text-gray-900 dark:text-zinc-100">{{ $package->name }}</p>
                                                            <p class="text-xs text-zinc-500">MYR {{ number_format($package->price, 2) }}</p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif(strlen($variantPackageSearch) >= 2)
                                                <p class="mt-2 text-xs text-zinc-500">No packages found</p>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Summary --}}
                @if(count($variantMappings) > 0)
                    <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            {{ count($variantMappings) }} of {{ $variantLinkingProduct->getVariantCount() }} variants mapped
                        </p>
                    </div>
                @endif
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeVariantLinkModal">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="confirmVariantLinks" :disabled="count($variantMappings) === 0">
                    <div class="flex items-center justify-center">
                        <flux:icon name="check" class="w-4 h-4 mr-2" />
                        Save Mappings ({{ count($variantMappings) }})
                    </div>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Toast Notification --}}
    <div
        x-data="{
            show: false,
            message: '',
            type: 'success',
            timeout: null
        }"
        x-on:notify.window="
            message = $event.detail.message;
            type = $event.detail.type || 'success';
            show = true;
            clearTimeout(timeout);
            timeout = setTimeout(() => show = false, 5000)
        "
        x-show="show"
        x-transition
        class="fixed bottom-4 right-4 z-50"
        style="display: none;"
    >
        <div
            :class="{
                'bg-green-500': type === 'success',
                'bg-red-500': type === 'error',
                'bg-amber-500': type === 'warning',
                'bg-blue-500': type === 'info'
            }"
            class="text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 max-w-md"
        >
            <svg x-show="type === 'success'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <svg x-show="type === 'error'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <svg x-show="type === 'info'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            <span x-text="message" class="flex-1 text-sm font-medium"></span>
            <button @click="show = false" class="ml-2 hover:opacity-75">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
</div>
