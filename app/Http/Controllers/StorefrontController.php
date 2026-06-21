<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public e-commerce storefront — the homepage (served at `/` for guests) and the
 * shop catalogue. Browsing is open to everyone; adding to cart and checking out
 * reuse the existing ProductCart / checkout flow. The storefront defaults to
 * Malay; visitors can switch to English via {@see setLocale()}.
 */
class StorefrontController extends Controller
{
    private const SORTS = ['latest', 'price_low', 'price_high', 'name'];

    /**
     * Public homepage: hero, featured products, bundle deals and categories.
     */
    public function home(): View
    {
        // Only simple products: the quick-add storefront has no variant picker,
        // so variable products (priced per variant) can't be added correctly.
        $featured = Product::query()
            ->active()
            ->where('type', 'simple')
            ->with(['primaryImage', 'category:id,name,slug', 'stockLevels'])
            ->latest()
            ->limit(config('store.featured_limit'))
            ->get();

        $packages = Package::query()
            ->available()
            ->withCount('items')
            ->latest()
            ->limit(config('store.package_limit'))
            ->get();

        $categories = ProductCategory::query()
            ->active()
            ->rootCategories()
            ->ordered()
            ->withCount('activeProducts')
            ->limit(config('store.category_limit'))
            ->get();

        return view('store.home', [
            'featured' => $featured,
            'packages' => $packages,
            'categories' => $categories,
            'productCount' => Product::active()->count(),
        ]);
    }

    /**
     * Shop catalogue with search, category filter, sort and pagination. All
     * filters live in the query string so results are shareable and bookmarkable.
     */
    public function shop(Request $request): View
    {
        $search = trim((string) $request->query('q', '')) ?: null;
        $categoryId = $request->filled('category') ? (int) $request->query('category') : null;
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'latest';

        $products = Product::query()
            ->active()
            ->where('type', 'simple')
            ->with(['primaryImage', 'category:id,name,slug', 'stockLevels'])
            ->when($search, fn ($query) => $query->search($search))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($sort === 'latest', fn ($query) => $query->latest())
            ->when($sort === 'price_low', fn ($query) => $query->orderBy('base_price'))
            ->when($sort === 'price_high', fn ($query) => $query->orderByDesc('base_price'))
            ->when($sort === 'name', fn ($query) => $query->orderBy('name'))
            ->paginate(config('store.per_page'))
            ->withQueryString();

        $categories = ProductCategory::query()->active()->ordered()->get(['id', 'name', 'slug']);

        return view('store.shop', [
            'products' => $products,
            'categories' => $categories,
            'search' => $search,
            'categoryId' => $categoryId,
            'sort' => $sort,
        ]);
    }

    /**
     * Persist the visitor's language choice (ms|en) in the session — the
     * SetLocale middleware applies it on the next request for guests and
     * authenticated users alike — then return them to where they were.
     */
    public function setLocale(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, ['ms', 'en'], true)) {
            session(['locale' => $locale]);
        }

        // Return to the previous page, but only if it's on this site — guards
        // against an attacker-forged Referer redirecting users off-site.
        $back = url()->previous();

        if (parse_url($back, PHP_URL_HOST) !== $request->getHost()) {
            $back = route('home');
        }

        return redirect()->to($back);
    }
}
