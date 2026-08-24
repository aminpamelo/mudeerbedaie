<?php

namespace App\Http\Controllers\BlogSeo;

use App\Http\Controllers\Controller;
use App\Models\BlogAuthor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuthorController extends Controller
{
    public function index(): Response
    {
        $authors = BlogAuthor::query()
            ->withCount([
                'posts',
                'posts as published_count' => fn (Builder $q) => $q->published(),
            ])
            ->ordered()
            ->get()
            ->map(fn (BlogAuthor $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'slug' => $a->slug,
                'avatar_url' => $a->avatar_url,
                'posts' => $a->posts_count,
                'published' => $a->published_count,
            ]);

        return Inertia::render('Authors', ['authors' => $authors]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['avatar_path'] = $this->storeAvatar($request);

        BlogAuthor::create($data);

        return back()->with('success', 'Author created.');
    }

    public function update(Request $request, BlogAuthor $author): RedirectResponse
    {
        $data = $this->validated($request, $author->id);

        if ($request->hasFile('avatar')) {
            $this->deleteAvatar($author);
            $data['avatar_path'] = $this->storeAvatar($request);
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteAvatar($author);
            $data['avatar_path'] = null;
        }

        $author->update($data);

        return back()->with('success', 'Author saved.');
    }

    public function destroy(BlogAuthor $author): RedirectResponse
    {
        // blog_author_id is nullOnDelete, so posts keep their content and simply
        // fall back to the generic team byline until reassigned.
        $this->deleteAvatar($author);
        $author->delete();

        return back()->with('success', 'Author deleted. Their posts now use the default byline.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('blog_authors', 'slug')->ignore($ignoreId)],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'remove_avatar' => ['boolean'],
        ], [], [
            'avatar' => 'photo',
        ]);

        return [
            'name' => trim((string) $request->input('name')),
            'slug' => trim((string) $request->input('slug')) ?: null,
        ];
    }

    private function storeAvatar(Request $request): ?string
    {
        if (! $request->hasFile('avatar')) {
            return null;
        }

        return $request->file('avatar')->store('blog-authors', 'public');
    }

    private function deleteAvatar(BlogAuthor $author): void
    {
        if ($author->avatar_path) {
            Storage::disk('public')->delete($author->avatar_path);
        }
    }
}
