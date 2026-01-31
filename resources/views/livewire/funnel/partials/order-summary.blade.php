<div class="bg-white rounded-lg shadow-sm border p-6 sticky top-6">
    <h3 class="text-lg font-semibold mb-4">Ringkasan Pesanan</h3>

    <div class="space-y-3 mb-6">
        @foreach($step->products as $product)
            @if(isset($selectedProducts[$product->id]))
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ $product->name }}</span>
                    <span class="font-medium">RM {{ number_format($product->funnel_price, 2) }}</span>
                </div>
            @endif
        @endforeach

        @foreach($step->orderBumps as $bump)
            @if(isset($selectedBumps[$bump->id]))
                <div class="flex justify-between text-sm text-green-700">
                    <span>+ {{ $bump->name }}</span>
                    <span class="font-medium">RM {{ number_format($bump->price, 2) }}</span>
                </div>
            @endif
        @endforeach
    </div>

    @php
        $subtotal = collect($step->products)
            ->filter(fn($p) => isset($selectedProducts[$p->id]))
            ->sum('funnel_price');

        $bumpsTotal = collect($step->orderBumps)
            ->filter(fn($b) => isset($selectedBumps[$b->id]))
            ->sum('price');

        $total = $subtotal + $bumpsTotal;
    @endphp

    <div class="border-t pt-4">
        <div class="flex justify-between mb-2">
            <span class="text-gray-600">Jumlah kecil</span>
            <span class="font-medium">RM {{ number_format($subtotal, 2) }}</span>
        </div>

        @if($bumpsTotal > 0)
            <div class="flex justify-between mb-2 text-green-700">
                <span>Tambahan Pesanan</span>
                <span class="font-medium">RM {{ number_format($bumpsTotal, 2) }}</span>
            </div>
        @endif

        <div class="flex justify-between text-lg font-bold border-t pt-2 mt-2">
            <span>Jumlah</span>
            <span>RM {{ number_format($total, 2) }}</span>
        </div>
    </div>

    <div class="mt-6 pt-4 border-t">
        <div class="flex items-center justify-center text-sm text-gray-500">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            Pembayaran Selamat
        </div>
    </div>
</div>
