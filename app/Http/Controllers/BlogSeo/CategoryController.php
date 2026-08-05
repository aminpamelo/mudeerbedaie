<?php

namespace App\Http\Controllers\BlogSeo;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = BlogCategory::query()
            ->withCount([
                'posts',
                'posts as published_count' => fn (Builder $q) => $q->published(),
            ])
            ->ordered()
            ->get()
            ->map(fn (BlogCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'description' => $c->description,
                'color' => $c->color,
                'sort_order' => $c->sort_order,
                'is_active' => (bool) $c->is_active,
                'meta_title' => (string) $c->meta_title,
                'meta_description' => (string) $c->meta_description,
                'posts' => $c->posts_count,
                'published' => $c->published_count,
                'url' => route('blog.category', $c->slug),
            ]);

        return Inertia::render('Categories', ['categories' => $categories]);
    }

    public function store(Request $request): RedirectResponse
    {
        BlogCategory::create($this->validated($request));
        cache()->forget('seo.sitemap.xml');

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, BlogCategory $category): RedirectResponse
    {
        $category->update($this->validated($request, $category->id));
        cache()->forget('seo.sitemap.xml');

        return back()->with('success', 'Category saved.');
    }

    public function destroy(BlogCategory $category): RedirectResponse
    {
        // category_id is nullOnDelete, so posts are detached rather than
        // deleted — removing a category never destroys published content.
        $category->delete();
        cache()->forget('seo.sitemap.xml');

        return back()->with('success', 'Category deleted. Its posts are now uncategorised.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('blog_categories', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['required', 'string', 'max:20'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['slug'] = trim((string) ($data['slug'] ?? '')) ?: null;

        return $data;
    }
}
