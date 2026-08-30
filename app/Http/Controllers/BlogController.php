<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\Blog\MarkdownService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Public blog. Articles carry a language tag (`locale`) and the index shows the
 * ones matching the visitor's active storefront locale, so BM and EN readers
 * each get a coherent feed instead of a mixed one.
 */
class BlogController extends Controller
{
    public function __construct(private readonly MarkdownService $markdown) {}

    /**
     * Blog index: featured hero, category rail, searchable article grid.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $posts = $this->baseQuery()
            ->search($search)
            ->latest('published_at')
            ->paginate(config('blog.per_page'))
            ->withQueryString();

        // The news-portal front page (ticker, lead block, category sections) is
        // only assembled for the unfiltered first page; searches and deeper
        // pages fall back to the plain article list.
        $isFront = $search === '' && (int) $request->query('page', 1) === 1;

        return view('blog.index', [
            'posts' => $posts,
            'featured' => $this->featuredPost($search),
            'categories' => $this->categoriesWithCounts(),
            'popular' => $this->popularPosts(),
            'news' => $isFront ? $this->newsPayload() : null,
            'search' => $search,
            'activeCategory' => null,
            'activeTag' => null,
            'seo' => $this->indexSeo($search),
        ]);
    }

    /**
     * Article page.
     */
    public function show(Request $request, string $slug): View
    {
        $post = $this->baseQuery()
            ->with([
                'author:id,name',
                'category',
                'tags',
                'featuredImage',
                'ogImage',
                'products' => fn ($q) => $q->where('products.status', 'active')
                    ->where('products.type', 'simple')
                    ->with(['primaryImage', 'category:id,name,slug', 'stockLevels']),
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        // One view per session, so a reader scrolling back up doesn't inflate the count.
        if (! $request->session()->has("blog_viewed_{$post->id}")) {
            $post->incrementViews();
            $request->session()->put("blog_viewed_{$post->id}", true);
        }

        return view('blog.show', [
            'post' => $post,
            'toc' => $this->markdown->tableOfContents($post->content_html),
            'related' => $this->relatedPosts($post),
            'rail' => $this->railPosts($post),
            'comments' => $post->allow_comments
                ? $post->approvedComments()->with(['user:id,name', 'replies.user:id,name'])->get()
                : new Collection,
            'seo' => $this->postSeo($post),
        ]);
    }

    /**
     * Posts within one category.
     */
    public function category(Request $request, string $slug): View
    {
        $category = BlogCategory::query()->active()->where('slug', $slug)->firstOrFail();
        $search = trim((string) $request->query('q', ''));

        $posts = $this->baseQuery()
            ->where('category_id', $category->id)
            ->search($search)
            ->latest('published_at')
            ->paginate(config('blog.per_page'))
            ->withQueryString();

        return view('blog.index', [
            'posts' => $posts,
            'featured' => null,
            'categories' => $this->categoriesWithCounts(),
            'popular' => $this->popularPosts(),
            'news' => null,
            'search' => $search,
            'activeCategory' => $category,
            'activeTag' => null,
            'seo' => [
                'title' => $category->meta_title ?: $category->name,
                'description' => $category->meta_description
                    ?: __('blog.category_meta', ['category' => $category->name, 'store' => config('store.name')]),
                'canonical' => route('blog.category', $category->slug),
                'type' => 'website',
            ],
        ]);
    }

    /**
     * Posts carrying one tag.
     */
    public function tag(Request $request, string $slug): View
    {
        $tag = BlogTag::query()->where('slug', $slug)->firstOrFail();

        $posts = $this->baseQuery()
            ->whereHas('tags', fn (Builder $q) => $q->whereKey($tag->id))
            ->latest('published_at')
            ->paginate(config('blog.per_page'))
            ->withQueryString();

        return view('blog.index', [
            'posts' => $posts,
            'featured' => null,
            'categories' => $this->categoriesWithCounts(),
            'popular' => $this->popularPosts(),
            'news' => null,
            'search' => '',
            'activeCategory' => null,
            'activeTag' => $tag,
            'seo' => [
                'title' => __('blog.tag_title', ['tag' => $tag->name]),
                'description' => __('blog.tag_meta', ['tag' => $tag->name, 'store' => config('store.name')]),
                'canonical' => route('blog.tag', $tag->slug),
                'type' => 'website',
            ],
        ]);
    }

    // ----------------------------------------------------------------- helpers

    /**
     * Published posts in the visitor's language, eager-loading what the article
     * cards render so the grid never walks into an N+1.
     */
    private function baseQuery(): Builder
    {
        return BlogPost::query()
            ->published()
            ->forLocale()
            ->with(['category:id,name,slug,color', 'author:id,name', 'featuredImage']);
    }

    private function featuredPost(string $search): ?BlogPost
    {
        // The hero only makes sense on an unfiltered index — when someone is
        // searching, every slot should be a result.
        if ($search !== '') {
            return null;
        }

        return $this->baseQuery()->featured()->latest('published_at')->first()
            ?? $this->baseQuery()->latest('published_at')->first();
    }

    /**
     * Assemble the news-portal front page: a lead story, a rail of secondary
     * headlines, a headline ticker and per-category section strips. Everything
     * is drawn from the same eager-loaded base query so the page stays flat on
     * queries no matter how many blocks render.
     *
     * @return array{lead: ?BlogPost, secondary: Collection<int, BlogPost>, ticker: Collection<int, BlogPost>, sections: Collection<int, array{category: BlogCategory, posts: Collection<int, BlogPost>}>}
     */
    private function newsPayload(): array
    {
        $recent = $this->baseQuery()->latest('published_at')->limit(9)->get();

        // An editor-pinned post leads; otherwise the newest article does.
        $lead = $this->baseQuery()->featured()->latest('published_at')->first()
            ?? $recent->first();

        if (! $lead instanceof BlogPost) {
            return ['lead' => null, 'secondary' => collect(), 'ticker' => collect(), 'sections' => collect()];
        }

        $secondary = $recent->reject(fn (BlogPost $p) => $p->is($lead))->take(4)->values();

        // Section strips only earn their space once the blog has enough articles
        // to fill them without echoing the lead block back at the reader.
        $sections = $recent->count() >= 6
            ? $this->categoriesWithCounts()
                ->take(4)
                ->map(fn (BlogCategory $category) => [
                    'category' => $category,
                    'posts' => $this->baseQuery()
                        ->where('category_id', $category->id)
                        ->latest('published_at')
                        ->limit(4)
                        ->get(),
                ])
                ->filter(fn (array $section) => $section['posts']->isNotEmpty())
                ->values()
            : collect();

        return [
            'lead' => $lead,
            'secondary' => $secondary,
            'ticker' => $recent->take(8)->values(),
            'sections' => $sections,
        ];
    }

    /**
     * @return Collection<int, BlogCategory>
     */
    private function categoriesWithCounts(): Collection
    {
        return BlogCategory::query()
            ->active()
            ->ordered()
            ->withCount(['posts as published_count' => fn (Builder $q) => $q->published()->forLocale()])
            ->having('published_count', '>', 0)
            ->get();
    }

    /**
     * @return Collection<int, BlogPost>
     */
    private function popularPosts(): Collection
    {
        return $this->baseQuery()
            ->orderByDesc('view_count')
            ->limit(config('blog.popular_limit'))
            ->get();
    }

    /**
     * Related articles: same category first, topped up with recent posts so the
     * strip is never half-empty on a thin category.
     *
     * @return Collection<int, BlogPost>
     */
    private function relatedPosts(BlogPost $post): Collection
    {
        $limit = (int) config('blog.related_limit');

        $related = $this->baseQuery()
            ->whereKeyNot($post->id)
            ->when($post->category_id, fn (Builder $q) => $q->where('category_id', $post->category_id))
            ->latest('published_at')
            ->limit($limit)
            ->get();

        if ($related->count() >= $limit) {
            return $related;
        }

        $exclude = $related->modelKeys();
        $exclude[] = $post->id;

        $filler = $this->baseQuery()
            ->whereNotIn('id', $exclude)
            ->latest('published_at')
            ->limit($limit - $related->count())
            ->get();

        return $related->concat($filler);
    }

    /**
     * Sidebar discovery rail for the article page: the newest articles and the
     * most-read ones, each excluding the post being read. `baseQuery()` already
     * eager-loads the featured image the list thumbnails render.
     *
     * @return array{latest: Collection<int, BlogPost>, popular: Collection<int, BlogPost>}
     */
    private function railPosts(BlogPost $post): array
    {
        $limit = (int) config('blog.rail_limit');

        return [
            'latest' => $this->baseQuery()
                ->whereKeyNot($post->id)
                ->latest('published_at')
                ->limit($limit)
                ->get(),
            'popular' => $this->baseQuery()
                ->whereKeyNot($post->id)
                ->orderByDesc('view_count')
                ->limit($limit)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function indexSeo(string $search): array
    {
        return [
            'title' => $search !== ''
                ? __('blog.search_title', ['term' => $search])
                : __('blog.index_title', ['store' => config('store.name')]),
            'description' => __('blog.index_meta', ['store' => config('store.name')]),
            'canonical' => route('blog.index'),
            'type' => 'website',
            // Search result pages must never be indexed: they are effectively
            // infinite, thin, and burn crawl budget that belongs to the articles.
            'noindex' => $search !== '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postSeo(BlogPost $post): array
    {
        return [
            'title' => $post->seo_title,
            'description' => $post->seo_description,
            'canonical' => filled($post->canonical_url) ? $post->canonical_url : route('blog.show', $post->slug),
            'type' => 'article',
            'image' => $post->ogImage?->url ?? $post->featuredImage?->url,
            'noindex' => (bool) $post->noindex,
            'locale' => $post->locale,
            'published_time' => $post->published_at?->toAtomString(),
            'modified_time' => $post->updated_at?->toAtomString(),
            'author' => $post->author_name,
            'section' => $post->category?->name,
            'tags' => $post->tags->pluck('name')->all(),
            'reading_time' => $post->reading_time,
        ];
    }
}
