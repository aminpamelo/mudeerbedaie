<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Products page — the fighter's own (editable) plus official HQ products
     * (view-only). Products other fighters created are never shown.
     */
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;

        $myProducts = Product::query()
            ->createdByFighter($userId)
            ->with('primaryImage')
            ->latest()
            ->get()
            ->map(fn (Product $p) => $this->toCard($p, true));

        $search = trim((string) $request->get('search'));

        $hqPage = Product::query()
            ->hq()
            ->where('status', 'active')
            ->with('primaryImage')
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Products', [
            'myProducts' => $myProducts,
            'hq' => [
                'data' => collect($hqPage->items())->map(fn (Product $p) => $this->toCard($p, false))->values(),
                'meta' => [
                    'current_page' => $hqPage->currentPage(),
                    'last_page' => $hqPage->lastPage(),
                    'total' => $hqPage->total(),
                ],
            ],
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProduct($request);

        $product = Product::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'sku' => $this->uniqueSku(),
            'description' => $data['description'] ?? null,
            'base_price' => $data['base_price'],
            'status' => $data['status'],
            'type' => 'simple',
            'track_quantity' => false,
            'created_by_fighter_id' => $request->user()->id,
        ]);

        if ($request->hasFile('image')) {
            $this->setPrimaryImage($product, $request->file('image'));
        }

        return back()->with('success', 'Product created.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwnership($request, $product);

        $data = $this->validateProduct($request);

        $product->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'base_price' => $data['base_price'],
            'status' => $data['status'],
        ]);

        if ($request->hasFile('image')) {
            $this->setPrimaryImage($product, $request->file('image'));
        }

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwnership($request, $product);

        // Order items cascade-delete with the product, which would corrupt order
        // history — so a product that's been sold can only be deactivated.
        if (ProductOrderItem::query()->where('product_id', $product->id)->exists()) {
            $product->update(['status' => 'inactive']);

            return back()->with('error', 'This product has orders, so it was set to inactive instead of deleted.');
        }

        $product->media()->delete();
        $product->delete();

        return back()->with('success', 'Product deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,inactive'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function authorizeOwnership(Request $request, Product $product): void
    {
        abort_unless((int) $product->created_by_fighter_id === (int) $request->user()->id, 403);
    }

    private function setPrimaryImage(Product $product, $file): void
    {
        $path = $file->store('products/images', 'public');

        $product->media()->where('is_primary', true)->delete();

        ProductMedia::create([
            'product_id' => $product->id,
            'type' => 'image',
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        do {
            $slug = Str::slug($name).'-'.Str::lower(Str::random(6));
        } while (Product::query()->where('slug', $slug)->exists());

        return $slug;
    }

    private function uniqueSku(): string
    {
        do {
            $sku = 'FGT-'.strtoupper(Str::random(8));
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }

    /**
     * @return array<string, mixed>
     */
    private function toCard(Product $product, bool $editable): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'base_price' => (float) $product->base_price,
            'description' => $product->description,
            'status' => $product->status,
            'image' => $product->primaryImage?->url,
            'editable' => $editable,
        ];
    }
}
