<?php

namespace App\Http\Controllers\Fighter;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Browsable product catalogue for the fighter's order-create screen —
     * returns the fighter's favourites (pinned) plus a page of active products,
     * each with image, price and variants.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $favouriteIds = $user->favouriteProducts()->pluck('products.id')->all();

        $favourites = $user->favouriteProducts()
            ->where('status', 'active')
            ->with($this->cardRelations())
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => $this->toCard($p, true));

        $query = Product::query()
            ->where('status', 'active')
            ->sellableByFighter((int) $user->id)
            ->with($this->cardRelations());

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')
            ->limit(60)
            ->get()
            ->map(fn (Product $p) => $this->toCard($p, in_array($p->id, $favouriteIds, true)))
            ->values();

        return response()->json([
            'favourites' => $favourites->values(),
            'products' => $products,
        ]);
    }

    /**
     * Toggle a product in the fighter's favourites.
     */
    public function toggleFavourite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $result = $request->user()->favouriteProducts()->toggle($validated['product_id']);

        return response()->json([
            'favourited' => count($result['attached']) > 0,
        ]);
    }

    /**
     * @return array<int, string|\Closure>
     */
    private function cardRelations(): array
    {
        return [
            'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            'primaryImage',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toCard(Product $product, bool $isFavourite): array
    {
        $base = (float) $product->base_price;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'base_price' => $base,
            'image' => $product->primaryImage?->url,
            'is_favourite' => $isFavourite,
            'variants' => $product->variants->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'sku' => $v->sku,
                'price' => (float) ($v->price ?? $base),
            ])->values(),
        ];
    }
}
