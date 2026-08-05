<?php

namespace App\Http\Controllers\BlogSeo;

use App\Http\Controllers\Controller;
use App\Models\BlogTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        $tags = BlogTag::query()
            ->withCount([
                'posts',
                'posts as published_count' => fn (Builder $q) => $q->published(),
            ])
            ->orderByDesc('published_count')
            ->orderBy('name')
            ->get()
            ->map(fn (BlogTag $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'posts' => $t->posts_count,
                'published' => $t->published_count,
                'url' => route('blog.tag', $t->slug),
            ]);

        return Inertia::render('Tags', [
            'tags' => $tags,
            'unusedCount' => BlogTag::query()->doesntHave('posts')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        BlogTag::create($this->validated($request));
        cache()->forget('seo.sitemap.xml');

        return back()->with('success', 'Tag created.');
    }

    public function update(Request $request, BlogTag $tag): RedirectResponse
    {
        $tag->update($this->validated($request, $tag->id));
        cache()->forget('seo.sitemap.xml');

        return back()->with('success', 'Tag saved.');
    }

    public function destroy(BlogTag $tag): RedirectResponse
    {
        $tag->delete();
        cache()->forget('seo.sitemap.xml');

        return back()->with('success', 'Tag deleted.');
    }

    /**
     * Tags with no posts render as thin, empty pages that waste crawl budget.
     */
    public function prune(): RedirectResponse
    {
        $count = BlogTag::query()->doesntHave('posts')->delete();
        cache()->forget('seo.sitemap.xml');

        return back()->with('success', "{$count} unused tag(s) removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', Rule::unique('blog_tags', 'slug')->ignore($ignoreId)],
        ]);

        $data['slug'] = trim((string) ($data['slug'] ?? '')) ?: null;

        return $data;
    }
}
