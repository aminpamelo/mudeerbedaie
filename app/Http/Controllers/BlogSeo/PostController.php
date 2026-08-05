<?php

namespace App\Http\Controllers\BlogSeo;

use App\Http\Controllers\Controller;
use App\Http\Requests\BlogSeo\StorePostRequest;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Media;
use App\Models\Product;
use App\Services\Blog\MarkdownService;
use App\Services\Seo\SeoAnalyzer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function __construct(
        private readonly SeoAnalyzer $analyzer,
        private readonly MarkdownService $markdown,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', ''),
            'category' => (string) $request->query('category', ''),
            'locale' => (string) $request->query('locale', ''),
            'sort' => (string) $request->query('sort', 'latest'),
        ];

        $posts = BlogPost::query()
            ->with(['category:id,name,color', 'author:id,name', 'featuredImage'])
            ->withCount(['comments as pending_comments' => fn (Builder $q) => $q->where('status', 'pending')])
            ->when($filters['search'], fn (Builder $q, $v) => $q->search($v))
            ->when($filters['status'], fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['category'], fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($filters['locale'], fn (Builder $q, $v) => $q->where('locale', $v))
            ->when($filters['sort'] === 'latest', fn (Builder $q) => $q->latest('created_at'))
            ->when($filters['sort'] === 'views', fn (Builder $q) => $q->orderByDesc('view_count'))
            ->when($filters['sort'] === 'seo_low', fn (Builder $q) => $q->orderBy('seo_score'))
            ->when($filters['sort'] === 'seo_high', fn (Builder $q) => $q->orderByDesc('seo_score'))
            ->paginate(12)
            ->withQueryString()
            ->through(fn (BlogPost $p) => $this->listRow($p));

        return Inertia::render('Posts', [
            'posts' => $posts,
            'filters' => $filters,
            'categories' => BlogCategory::query()->ordered()->get(['id', 'name', 'color']),
            'counts' => [
                'all' => BlogPost::count(),
                'published' => BlogPost::query()->published()->count(),
                'draft' => BlogPost::where('status', BlogPost::STATUS_DRAFT)->count(),
                'scheduled' => BlogPost::where('status', BlogPost::STATUS_SCHEDULED)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PostEditor', [
            'post' => null,
            ...$this->editorOptions(),
        ]);
    }

    public function edit(BlogPost $post): Response
    {
        $post->load(['tags', 'products:id', 'featuredImage', 'ogImage']);

        return Inertia::render('PostEditor', [
            'post' => $this->editorPayload($post),
            ...$this->editorOptions(),
        ]);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $post = BlogPost::create([
            ...$this->payload($request),
            'author_id' => $request->user()->id,
        ]);

        $this->syncRelations($post, $request);
        $this->analyzer->analyseAndStore($post->fresh());
        cache()->forget('seo.sitemap.xml');

        return redirect()
            ->route('blogseo.posts.edit', $post)
            ->with('success', 'Post created.');
    }

    public function update(StorePostRequest $request, BlogPost $post): RedirectResponse
    {
        $post->update($this->payload($request));

        $this->syncRelations($post, $request);
        $this->analyzer->analyseAndStore($post->fresh());
        cache()->forget('seo.sitemap.xml');

        return back()->with('success', 'Post saved.');
    }

    /**
     * Live Markdown render + SEO score for the editor.
     *
     * Runs server-side against an unsaved in-memory model so the preview and
     * the score come from the exact same code that grades a saved post — no
     * second scoring implementation in JavaScript to drift out of sync.
     */
    public function analyze(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:blog_posts,id'],
            'title' => ['nullable', 'string'],
            'slug' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'focus_keyword' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'featured_image_id' => ['nullable', 'integer'],
            'og_image_id' => ['nullable', 'integer'],
            'noindex' => ['nullable', 'boolean'],
        ]);

        $html = $this->markdown->toHtml((string) ($data['content'] ?? ''));

        $draft = new BlogPost;
        $draft->forceFill([
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? '',
            'excerpt' => $data['excerpt'] ?? null,
            'content_html' => $html,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'focus_keyword' => $data['focus_keyword'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'featured_image_id' => $data['featured_image_id'] ?? null,
            'og_image_id' => $data['og_image_id'] ?? null,
            'noindex' => (bool) ($data['noindex'] ?? false),
        ]);

        return response()->json([
            'html' => $html,
            'toc' => $this->markdown->tableOfContents($html),
            'report' => $this->analyzer->analyse($draft),
            'readingTime' => BlogPost::estimateReadingTime((string) ($data['content'] ?? '')),
        ]);
    }

    public function publish(BlogPost $post): RedirectResponse
    {
        $post->update([
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => $post->published_at ?? now(),
        ]);

        $this->analyzer->analyseAndStore($post->fresh());
        cache()->forget('seo.sitemap.xml');

        return back()->with('success', 'Post published.');
    }

    public function unpublish(BlogPost $post): RedirectResponse
    {
        $post->update(['status' => BlogPost::STATUS_DRAFT]);
        cache()->forget('seo.sitemap.xml');

        return back()->with('success', 'Moved back to draft.');
    }

    public function toggleFeatured(BlogPost $post): RedirectResponse
    {
        $post->update(['is_featured' => ! $post->is_featured]);

        return back()->with('success', $post->is_featured ? 'Marked as featured.' : 'Removed from featured.');
    }

    public function reanalyse(BlogPost $post): RedirectResponse
    {
        $report = $this->analyzer->analyseAndStore($post);

        return back()->with('success', "SEO re-scored: {$report['score']}/100.");
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $post->delete();
        cache()->forget('seo.sitemap.xml');

        return redirect()
            ->route('blogseo.posts.index')
            ->with('success', 'Post moved to trash.');
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @return array<string, mixed>
     */
    private function payload(StorePostRequest $request): array
    {
        $data = $request->validated();

        // Blank optional text is stored as NULL so `filled()` checks in the
        // analyzer behave consistently.
        foreach (['excerpt', 'meta_title', 'meta_description', 'focus_keyword', 'canonical_url'] as $key) {
            $trimmed = trim((string) ($data[$key] ?? ''));
            $data[$key] = $trimmed !== '' ? $trimmed : null;
        }

        return collect($data)->except(['tags', 'product_ids'])->all();
    }

    private function syncRelations(BlogPost $post, StorePostRequest $request): void
    {
        $tagIds = collect($request->validated('tags') ?? [])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => BlogTag::findOrCreateByName($name)->id)
            ->all();

        $post->tags()->sync($tagIds);
        $post->products()->sync($request->validated('product_ids') ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'status' => $post->status,
            'locale' => $post->locale,
            'score' => (int) $post->seo_score,
            'views' => (int) $post->view_count,
            'readingTime' => (int) $post->reading_time,
            'isFeatured' => (bool) $post->is_featured,
            'isPublished' => $post->is_published,
            'pendingComments' => (int) ($post->pending_comments ?? 0),
            'category' => $post->category?->name,
            'categoryColor' => $post->category?->color,
            'author' => $post->author_name,
            'image' => $post->featuredImage?->url,
            'publishedAt' => $post->published_at?->toIso8601String(),
            'updatedAt' => $post->updated_at?->toIso8601String(),
            'publicUrl' => $post->is_published ? route('blog.show', $post->slug) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editorPayload(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => (string) $post->title,
            'slug' => (string) $post->slug,
            'excerpt' => (string) $post->excerpt,
            'content' => (string) $post->content,
            'category_id' => $post->category_id,
            'locale' => $post->locale,
            'status' => $post->status,
            'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
            'is_featured' => (bool) $post->is_featured,
            'allow_comments' => (bool) $post->allow_comments,
            'featured_image_id' => $post->featured_image_id,
            'featured_image_url' => $post->featuredImage?->url,
            'og_image_id' => $post->og_image_id,
            'og_image_url' => $post->ogImage?->url,
            'meta_title' => (string) $post->meta_title,
            'meta_description' => (string) $post->meta_description,
            'focus_keyword' => (string) $post->focus_keyword,
            'canonical_url' => (string) $post->canonical_url,
            'noindex' => (bool) $post->noindex,
            'tags' => $post->tags->pluck('name')->all(),
            'product_ids' => $post->products->pluck('id')->all(),
            'views' => (int) $post->view_count,
            'publicUrl' => $post->is_published ? route('blog.show', $post->slug) : null,
        ];
    }

    /**
     * Shared option lists for the editor's selects and pickers.
     *
     * @return array<string, mixed>
     */
    private function editorOptions(): array
    {
        return [
            'categories' => BlogCategory::query()->ordered()->get(['id', 'name', 'color']),
            'allTags' => BlogTag::query()->orderBy('name')->pluck('name'),
            'products' => Product::query()
                ->where('status', 'active')
                ->where('type', 'simple')
                ->orderBy('name')
                ->get(['id', 'name', 'base_price'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->base_price,
                ]),
            'media' => Media::query()
                ->where('type', 'image')
                ->latest()
                ->limit(60)
                ->get(['id', 'title', 'alt_text', 'file_path', 'disk'])
                ->map(fn (Media $m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'alt' => $m->alt_text,
                    'url' => $m->url,
                ]),
            'siteUrl' => rtrim((string) config('app.url'), '/'),
        ];
    }

    /**
     * Suggest a slug from a title without hitting the editor's save path.
     */
    public function slugify(Request $request): JsonResponse
    {
        $title = (string) $request->query('title', '');
        $ignore = $request->integer('id') ?: null;

        return response()->json([
            'slug' => $title === '' ? '' : BlogPost::uniqueSlug($title, $ignore),
        ]);
    }
}
