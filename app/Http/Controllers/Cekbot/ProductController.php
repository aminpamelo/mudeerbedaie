<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotProduct;
use App\Models\CekbotProductTestimonial;
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

    public function show(CekbotProduct $product): Response
    {
        $product->load(['product:id,name', 'product.images', 'testimonials']);

        return Inertia::render('Products/Show', [
            'product' => $this->shape($product),
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

    /**
     * Update the detailed product knowledge (long-form notes + FAQ pairs) used
     * by the sales bot.
     */
    public function updateKnowledge(Request $request, CekbotProduct $product): RedirectResponse
    {
        $validated = $request->validate([
            'knowledge' => 'nullable|string|max:20000',
            'faqs' => 'nullable|array|max:100',
            'faqs.*.question' => 'nullable|string|max:500',
            'faqs.*.answer' => 'nullable|string|max:5000',
        ]);

        $faqs = collect($validated['faqs'] ?? [])
            ->map(fn ($faq) => [
                'question' => trim((string) ($faq['question'] ?? '')),
                'answer' => trim((string) ($faq['answer'] ?? '')),
            ])
            ->filter(fn (array $faq) => $faq['question'] !== '' || $faq['answer'] !== '')
            ->values()
            ->all();

        $product->update([
            'knowledge' => $validated['knowledge'] ?? null,
            'faqs' => $faqs ?: null,
        ]);

        return back()->with('success', 'Product knowledge dikemas kini.');
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

    /**
     * Manage a product's own gallery images from the detail page — append new
     * uploads and/or remove existing ones in a single request.
     */
    public function updateImages(Request $request, CekbotProduct $product): RedirectResponse
    {
        $request->validate([
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'removed_images' => 'nullable|array',
            'removed_images.*' => 'string',
        ]);

        $images = $product->images ?? [];

        foreach ((array) $request->input('removed_images', []) as $path) {
            if (($key = array_search($path, $images, true)) !== false) {
                Storage::disk('public')->delete($path);
                unset($images[$key]);
            }
        }

        $product->update([
            'images' => array_values(array_merge($images, $this->storeImages($request))),
        ]);

        return back()->with('success', 'Gambar produk dikemas kini.');
    }

    /**
     * Add a customer testimonial (image + text) to a product.
     */
    public function storeTestimonial(Request $request, CekbotProduct $product): RedirectResponse
    {
        $validated = $request->validate([
            'author' => 'nullable|string|max:255',
            'text' => 'nullable|string|max:2000',
            'image' => 'nullable|image|max:5120',
        ]);

        if (blank($validated['text'] ?? null) && ! $request->hasFile('image')) {
            return back()->withErrors(['text' => 'Masukkan teks atau gambar testimoni.']);
        }

        $product->testimonials()->create([
            'author' => $validated['author'] ?? null,
            'text' => $validated['text'] ?? null,
            'image' => $request->hasFile('image') ? $request->file('image')->store('cekbot-testimonials', 'public') : null,
        ]);

        return back()->with('success', 'Testimoni ditambah.');
    }

    public function destroyTestimonial(CekbotProduct $product, CekbotProductTestimonial $testimonial): RedirectResponse
    {
        abort_unless($testimonial->cekbot_product_id === $product->id, 404);

        if ($testimonial->image) {
            Storage::disk('public')->delete($testimonial->image);
        }

        $testimonial->delete();

        return back()->with('success', 'Testimoni dipadam.');
    }

    public function destroy(CekbotProduct $product): RedirectResponse
    {
        foreach ($product->images ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        foreach ($product->testimonials()->pluck('image')->filter() as $path) {
            Storage::disk('public')->delete($path);
        }

        $product->delete();

        return redirect()->route('cekbot.products')->with('success', 'Produk dipadam.');
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
            'knowledge' => $p->knowledge,
            'faqs' => $p->faqPairs(),
            'faqs_count' => count($p->faqPairs()),
            'has_knowledge' => filled($p->knowledge) || count($p->faqPairs()) > 0,
            'is_active' => $p->is_active,
            'sort_order' => $p->sort_order,
            'product_id' => $p->product_id,
            'linked_product' => $p->product?->name,
            'images' => $p->images ?? [],
            'image_urls' => $p->imageUrls(),
            'own_images' => collect($p->images ?? [])
                ->map(fn ($path) => ['path' => $path, 'url' => Storage::disk('public')->url($path)])
                ->all(),
            'linked_image_urls' => $p->relationLoaded('product') && $p->product
                ? $p->product->images->pluck('url')->filter()->values()->all()
                : [],
            'testimonials' => $p->relationLoaded('testimonials')
                ? $p->testimonials->map(fn (CekbotProductTestimonial $t) => [
                    'id' => $t->id,
                    'author' => $t->author,
                    'text' => $t->text,
                    'image_url' => $t->imageUrl(),
                ])->all()
                : [],
        ];
    }
}
