<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\StoreCredentialRequest;
use App\Http\Requests\Vault\UpdateCredentialRequest;
use App\Models\VaultCategory;
use App\Models\VaultCredential;
use App\Models\VaultTag;
use App\Services\VaultAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CredentialController extends Controller
{
    public function __construct(private readonly VaultAuditService $audit) {}

    public function index(Request $request): Response
    {
        $credentials = VaultCredential::query()
            ->with(['category:id,name,slug,icon,color', 'tags:id,name,slug', 'creator:id,name'])
            ->search($request->input('search'))
            ->when($request->input('category'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($request->input('tag'), fn ($q, $tagId) => $q->whereHas('tags', fn ($tq) => $tq->where('vault_tags.id', $tagId)))
            ->when(
                $request->input('sort'),
                fn ($q, $sort) => match ($sort) {
                    'name' => $q->orderBy('name'),
                    'oldest' => $q->oldest(),
                    default => $q->latest(),
                },
                fn ($q) => $q->latest(),
            )
            ->paginate(25)
            ->withQueryString()
            ->through(fn (VaultCredential $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'url' => $c->url,
                'username' => $c->username,
                'category' => $c->category ? [
                    'id' => $c->category->id,
                    'name' => $c->category->name,
                    'slug' => $c->category->slug,
                    'icon' => $c->category->icon,
                    'color' => $c->category->color,
                ] : null,
                'tags' => $c->tags->map(fn (VaultTag $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                ]),
                'creator' => $c->creator?->name,
                'created_at' => $c->created_at?->toIso8601String(),
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Credentials', [
            'credentials' => $credentials,
            'filters' => $request->only(['search', 'category', 'tag', 'sort']),
            'categories' => VaultCategory::query()->ordered()->get(['id', 'name', 'color']),
            'tags' => VaultTag::query()
                ->withCount('credentials')
                ->orderBy('name')
                ->get()
                ->map(fn (VaultTag $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'credentials_count' => $t->credentials_count,
                ]),
        ]);
    }

    public function store(StoreCredentialRequest $request): RedirectResponse
    {
        $credential = VaultCredential::create([
            ...$request->safe()->except('tags'),
            'created_by' => $request->user()->id,
        ]);

        if ($request->validated('tags')) {
            $this->syncTags($credential, $request->validated('tags'));
        }

        $this->audit->log('created', $credential);

        return back()->with('success', 'Credential created.');
    }

    public function show(VaultCredential $credential): JsonResponse
    {
        $credential->load('category:id,name,color', 'tags:id,name');

        $this->audit->log('viewed', $credential);

        return response()->json([
            'id' => $credential->id,
            'name' => $credential->name,
            'url' => $credential->url,
            'username' => $credential->username,
            'password' => $credential->password,
            'notes' => $credential->notes,
            'category' => $credential->category,
            'tags' => $credential->tags,
        ]);
    }

    public function update(UpdateCredentialRequest $request, VaultCredential $credential): RedirectResponse
    {
        $old = $credential->only(['name', 'url', 'username', 'password', 'notes', 'category_id']);

        $data = collect($request->safe()->except('tags'))
            ->reject(fn ($value, $key) => $key === 'password' && $value === null)
            ->toArray();

        $data['updated_by'] = $request->user()->id;

        $credential->update($data);

        if ($request->has('tags')) {
            $this->syncTags($credential, $request->validated('tags') ?? []);
        }

        $new = $credential->fresh()->only(['name', 'url', 'username', 'password', 'notes', 'category_id']);

        $this->audit->log('updated', $credential, [
            'old' => $old,
            'new' => $new,
        ]);

        return back()->with('success', 'Credential updated.');
    }

    public function destroy(VaultCredential $credential): RedirectResponse
    {
        $this->audit->log('deleted', $credential);

        $credential->delete();

        return back()->with('success', 'Credential deleted.');
    }

    /**
     * Sync tags by name, creating any that don't exist yet.
     *
     * @param  array<int, string>  $tagNames
     */
    private function syncTags(VaultCredential $credential, array $tagNames): void
    {
        $ids = collect($tagNames)
            ->filter()
            ->map(fn (string $name) => VaultTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )->id);

        $credential->tags()->sync($ids);
    }
}
