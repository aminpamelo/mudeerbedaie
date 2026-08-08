<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Course;
use App\Models\Package;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\StoreBanner;
use App\Models\StoreTestimonial;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Public e-commerce storefront — the homepage (served at `/` for guests) and the
 * shop catalogue. Browsing is open to everyone; adding to cart and checking out
 * reuse the existing ProductCart / checkout flow. The storefront defaults to
 * Malay; visitors can switch to English via {@see setLocale()}.
 */
class StorefrontController extends Controller
{
    /**
     * Public homepage: hero carousel, trust bar, categories, best sellers,
     * bundle deals, the brand story, testimonials and the latest articles.
     */
    public function home(): View
    {
        $bestsellerIds = $this->bestsellerProductIds();
        $featured = $this->featuredProducts($bestsellerIds);

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

        // LMS: courses the admin has surfaced for the storefront.
        $courses = Course::query()
            ->storefrontVisible()
            ->with(['feeSettings', 'teacher.user'])
            ->withCount('classes')
            ->latest()
            ->limit(3)
            ->get();

        // Latest articles in the visitor's language, for the "From the journal"
        // strip. Content on the homepage gives crawlers fresh, internally-linked
        // material to follow into the blog.
        $posts = BlogPost::query()
            ->published()
            ->forLocale()
            ->with(['category:id,name,slug,color', 'featuredImage'])
            ->latest('published_at')
            ->limit(config('blog.home_limit'))
            ->get();

        return view('store.home', [
            'featured' => $featured,
            'packages' => $packages,
            'courses' => $courses,
            'categories' => $categories,
            'categoryThumbs' => $this->categoryThumbnails($categories),
            'posts' => $posts,
            'banners' => StoreBanner::query()->live()->ordered()->get(),
            'testimonials' => StoreTestimonial::query()->active()->ordered()->get(),
            // The hero collage is built from real covers, so it stays truthful
            // and needs no separate art direction when the catalogue changes.
            'heroCovers' => $featured->pluck('primaryImage.url')->filter()->take(5)->values(),
            'bestsellerIds' => $bestsellerIds,
            'productCount' => Product::active()->count(),
            'orderCount' => $this->deliveredOrderCount(),
            'seo' => [
                'title' => config('store.name').' — '.__('store.hero_title'),
                'description' => __('store.hero_subtitle'),
                'canonical' => route('storefront.home'),
                'type' => 'website',
            ],
        ]);
    }

    /**
     * The homepage product grid: best sellers first, in sales order, topped up
     * with the newest titles when there aren't enough of them.
     *
     * The ranking is applied in PHP rather than SQL because ordering by a fixed
     * list of IDs has no portable expression — MySQL's `FIELD()` doesn't exist
     * on the SQLite connection the tests run against.
     *
     * Only simple products are eligible: the quick-add storefront has no variant
     * picker, so variable products (priced per variant) can't be added correctly.
     *
     * @param  array<int, int>  $bestsellerIds
     * @return Collection<int, Product>
     */
    private function featuredProducts(array $bestsellerIds): Collection
    {
        $limit = (int) config('store.featured_limit');

        $query = fn () => Product::query()
            ->active()
            ->inStock()
            ->where('type', 'simple')
            ->with(['primaryImage', 'category:id,name,slug', 'stockLevels']);

        $ranking = array_flip($bestsellerIds);

        $featured = $bestsellerIds === []
            ? collect()
            : $query()->whereIn('id', $bestsellerIds)->get()
                ->sortBy(fn (Product $product): int => $ranking[$product->id])
                ->take($limit)
                ->values();

        if ($featured->count() < $limit) {
            $featured = $featured->concat(
                $query()
                    ->whereNotIn('id', $featured->pluck('id'))
                    ->latest()
                    ->limit($limit - $featured->count())
                    ->get()
            );
        }

        return $featured;
    }

    /**
     * IDs of the best-selling active products, most-sold first.
     *
     * Aggregating paid order lines is too heavy to repeat on every homepage
     * hit — the orders table is in the tens of thousands of rows — and the
     * ranking barely moves hour to hour, so the result is cached.
     *
     * @return array<int, int>
     */
    private function bestsellerProductIds(): array
    {
        return Cache::remember('storefront.bestsellers', now()->addHour(), function (): array {
            return ProductOrderItem::query()
                ->selectRaw('product_id, SUM(quantity_ordered) as sold')
                ->whereNotNull('product_id')
                ->whereHas('order', fn ($query) => $query->where('payment_status', 'paid'))
                ->groupBy('product_id')
                ->orderByDesc('sold')
                ->limit(config('store.bestseller_limit'))
                ->pluck('product_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        });
    }

    /**
     * Total paid orders — the store's strongest piece of social proof, shown as
     * an animated counter in the hero's trust bar.
     */
    private function deliveredOrderCount(): int
    {
        return Cache::remember(
            'storefront.order_count',
            now()->addHour(),
            fn (): int => ProductOrder::query()->where('payment_status', 'paid')->count()
        );
    }

    /**
     * One cover image per category, borrowed from the first product in it.
     *
     * `product_categories.image` is the intended source, but it is unset for
     * every category today; falling back to a real product cover means the
     * category grid shows actual books instead of eight identical tag icons.
     *
     * @param  Collection<int, ProductCategory>  $categories
     * @return Collection<int, string> category id => image URL
     */
    private function categoryThumbnails(Collection $categories): Collection
    {
        $missing = $categories->whereNull('image');

        if ($missing->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->active()
            ->whereIn('category_id', $missing->pluck('id'))
            ->whereHas('primaryImage')
            ->with('primaryImage')
            ->get(['id', 'category_id'])
            ->groupBy('category_id')
            ->map(fn (Collection $products): ?string => $products->first()->primaryImage?->url)
            ->filter();
    }

    /**
     * Shop catalogue. Search, category filter, sort and infinite-scroll loading
     * are handled by the {@see \livewire\store\shop-browser} Volt component, which
     * keeps the active filters in the query string so results stay shareable.
     */
    public function shop(): View
    {
        return view('store.shop');
    }

    /**
     * Product detail page. Only active simple products are viewable (variable
     * products have no storefront variant picker). Out-of-stock products are
     * hidden from listings but still reachable by direct link, where the page
     * shows the sold-out state.
     */
    public function product(Product $product): View
    {
        abort_unless($product->status === 'active' && $product->type === 'simple', 404);

        $product->load(['images', 'primaryImage', 'category:id,name,slug', 'stockLevels']);

        $related = Product::query()
            ->active()
            ->inStock()
            ->where('type', 'simple')
            ->whereKeyNot($product->id)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->with(['primaryImage', 'category:id,name,slug', 'stockLevels'])
            ->latest()
            ->limit(4)
            ->get();

        $inStock = ! $product->track_quantity || $product->stockLevels->sum('available_quantity') > 0;

        return view('store.product', [
            'product' => $product,
            'related' => $related,
            'seo' => [
                'title' => $product->name,
                'description' => filled($product->short_description)
                    ? strip_tags($product->short_description)
                    : Str::limit(strip_tags((string) $product->description), 155),
                'canonical' => route('storefront.product', $product->slug),
                'type' => 'product',
                'image' => $product->primaryImage?->url,
                'breadcrumbs' => array_values(array_filter([
                    ['name' => config('store.name'), 'url' => route('storefront.home')],
                    ['name' => __('store.nav_shop'), 'url' => route('shop')],
                    $product->category ? ['name' => $product->category->name, 'url' => route('shop', ['category' => $product->category->id])] : null,
                    ['name' => $product->name, 'url' => route('storefront.product', $product->slug)],
                ])),
                // Feeds the Product JSON-LD block, which is what earns the
                // price/availability rich result in Google.
                'product' => [
                    'sku' => $product->sku,
                    'price' => $product->base_price,
                    'currency' => 'MYR',
                    'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'brand' => $product->category?->name,
                ],
            ],
        ]);
    }

    /**
     * Package detail page — the bundle's included items, savings and an
     * add-to-cart control so packages can be bought online like products.
     */
    public function package(Package $package): View
    {
        abort_unless($package->status === 'active', 404);

        $package->load([
            'products.primaryImage',
            'products.category:id,name,slug',
            'courses:id,name',
            'classes:id,title',
        ]);

        return view('store.package', [
            'package' => $package,
            'seo' => [
                'title' => $package->name,
                'description' => filled($package->short_description)
                    ? strip_tags($package->short_description)
                    : Str::limit(strip_tags((string) $package->description), 155),
                'canonical' => route('storefront.package', $package->slug),
                'type' => 'product',
                'image' => $package->featured_image,
            ],
        ]);
    }

    /**
     * Public course catalogue (the LMS section) — courses the admin has flagged
     * to show on the storefront.
     */
    public function courses(): View
    {
        $courses = Course::query()
            ->storefrontVisible()
            ->with(['feeSettings', 'teacher.user'])
            ->withCount(['classes', 'activeEnrollments'])
            ->latest()
            ->get();

        return view('store.courses', [
            'courses' => $courses,
            'seo' => [
                'title' => __('store.lms_title'),
                'description' => __('store.lms_subtitle'),
                'canonical' => route('storefront.courses'),
                'type' => 'website',
            ],
        ]);
    }

    /**
     * Course detail page — its classes, schedule, which are active, which have
     * recordings, and a buy/enrol control.
     */
    public function course(Course $course): View
    {
        abort_unless($course->show_on_storefront && $course->isActive(), 404);

        $course->load([
            'feeSettings',
            'teacher.user',
            'classes' => function ($query) {
                $query->where('show_on_storefront', true)
                    ->where('status', 'active')
                    ->with(['teacher.user', 'timetable', 'syllabi', 'activeStudents'])
                    ->withCount('activeStudents')
                    ->orderByDesc('date_time');
            },
        ]);

        $course->loadCount([
            'activeEnrollments',
            'classes',
        ]);

        // Recording counts per class (only meaningful for enrolled students, but
        // the count itself is safe to surface as a "has recordings" signal).
        $course->classes->loadCount([
            'sessions as recordings_count' => fn ($query) => $query->whereNotNull('recording_url'),
        ]);

        return view('store.course', [
            'course' => $course,
            'seo' => [
                'title' => $course->name,
                'description' => filled($course->short_description)
                    ? strip_tags($course->short_description)
                    : Str::limit(strip_tags((string) $course->description), 155),
                'canonical' => route('storefront.course', $course->slug),
                'type' => 'product',
                'image' => $course->thumbnail_url,
            ],
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
