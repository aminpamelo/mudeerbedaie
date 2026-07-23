@php
    $selectedProductModels = collect($step->products)->filter(fn ($p) => isset($selectedProducts[$p->id]));
    $selectedBumpModels = collect($step->orderBumps)->filter(fn ($b) => isset($selectedBumps[$b->id]));

    $subtotal = $selectedProductModels->sum('funnel_price');
    $bumpsTotal = $selectedBumpModels->sum('price');

    $savings = $selectedProductModels->sum(function ($p) {
        return ($p->compare_at_price && $p->compare_at_price > $p->funnel_price)
            ? $p->compare_at_price - $p->funnel_price
            : 0;
    });

    $shippingCostEnabled = $shippingCostEnabled ?? false;
    $shippingZone = $shippingZone ?? 'semenanjung';

    // The parent component computes the shipping cost (it depends on the shipping
    // mode, delivery zone and — in custom mode — the selected payment method), so
    // the summary just renders the resolved value.
    $shippingCost = $shippingCostEnabled ? ($shippingCost ?? 0) : 0;

    $total = $subtotal + $bumpsTotal + $shippingCost;
@endphp

<div class="fc-summary">
    <h3 class="fc-summary-title">Ringkasan Pesanan</h3>

    <div class="fc-summary-items">
        @forelse($selectedProductModels as $product)
            <div class="fc-summary-line">
                <span class="fc-summary-name">{{ $product->name }}</span>
                <span class="fc-summary-amount">RM {{ number_format($product->funnel_price, 2) }}</span>
            </div>
            @if($product->isPackage() && $product->package)
                <div class="fc-summary-sub">
                    @foreach($product->package->items as $pkgItem)
                        <div class="fc-summary-subitem">
                            <span class="fc-summary-dot {{ $pkgItem->isProduct() ? 'is-product' : 'is-course' }}"></span>
                            {{ $pkgItem->quantity > 1 ? $pkgItem->quantity . 'x ' : '' }}{{ $pkgItem->getDisplayName() }}
                        </div>
                    @endforeach
                </div>
            @endif
        @empty
            <div class="fc-summary-empty">Belum ada item dipilih</div>
        @endforelse

        @foreach($selectedBumpModels as $bump)
            <div class="fc-summary-line is-bump">
                <span class="fc-summary-name">+ {{ $bump->headline }}</span>
                <span class="fc-summary-amount">RM {{ number_format($bump->price, 2) }}</span>
            </div>
        @endforeach
    </div>

    <div class="fc-summary-breakdown">
        <div class="fc-summary-row">
            <span>Jumlah kecil</span>
            <span>RM {{ number_format($subtotal, 2) }}</span>
        </div>

        @if($bumpsTotal > 0)
            <div class="fc-summary-row is-bump">
                <span>Tambahan pesanan</span>
                <span>RM {{ number_format($bumpsTotal, 2) }}</span>
            </div>
        @endif

        @if($shippingCostEnabled)
            <div class="fc-summary-row">
                <span>Penghantaran ({{ $shippingZone === 'sabah_sarawak' ? 'Sabah & Sarawak' : 'Semenanjung' }})</span>
                <span>RM {{ number_format($shippingCost, 2) }}</span>
            </div>
        @endif

        <div class="fc-summary-total">
            <span>Jumlah</span>
            <span class="fc-summary-total-amount">RM {{ number_format($total, 2) }}</span>
        </div>

        @if($savings > 0)
            <div class="fc-summary-savings">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                Anda jimat RM {{ number_format($savings, 2) }}
            </div>
        @endif
    </div>
</div>
