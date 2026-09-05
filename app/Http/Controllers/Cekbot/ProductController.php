<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotProduct;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        $products = CekbotProduct::query()
            ->with(['product:id,name', 'product.images'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CekbotProduct $p) => $this->shape($p));

        return Inertia::render('Products/Index', [
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);
        $validated['images'] = $this->storeImages($request);
        $validated['created_by'] = $request->user()->id;

        CekbotProduct::create($validated);

        return back()->with('success', 'Produk ditambah.');
    }

    public function update(Request $request, CekbotProduct $product): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        $images = $product->images ?? [];

        // Remove images the user deleted.
        foreach ((array) $request->input('removed_images', []) as $path) {
            if (($key = array_search($path, $images, true)) !== false) {
                Storage::disk('public')->delete($path);
                unset($images[$key]);
            }
        }

        $validated['images'] = array_values(array_merge($images, $this->storeImages($request)));

        $product->update($validated);

        return back()->with('success', 'Produk dikemas kini.');
    }

    public function destroy(CekbotProduct $product): RedirectResponse
    {
        foreach ($product->images ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        $product->delete();

        return back()->with('success', 'Produk dipadam.');
    }

    /**
     * Search the catalogue to link a product knowledge entry.
     */
    public function searchCatalog(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $products = Product::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('sku', 'like', "%{$q}%")))
            ->with('images')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->base_price,
                'image' => $p->images->first()?->url,
            ]);

        return response()->json(['data' => $products]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:8',
            'url' => 'nullable|url|max:500',
            'description' => 'nullable|string|max:5000',
            'product_id' => 'nullable|exists:products,id',
            'is_active' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
        ]);
    }

    /**
     * Store any uploaded images and return their relative paths.
     *
     * @return array<int, string>
     */
    private function storeImages(Request $request): array
    {
        $paths = [];

        foreach ((array) $request->file('images', []) as $file) {
            $paths[] = $file->store('cekbot-products', 'public');
        }

        return $paths;
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(CekbotProduct $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'price' => $p->price,
            'currency' => $p->currency,
            'url' => $p->url,
            'description' => $p->description,
            'is_active' => $p->is_active,
            'sort_order' => $p->sort_order,
            'product_id' => $p->product_id,
            'linked_product' => $p->product?->name,
            'images' => $p->images ?? [],
            'image_urls' => $p->imageUrls(),
        ];
    }
}
