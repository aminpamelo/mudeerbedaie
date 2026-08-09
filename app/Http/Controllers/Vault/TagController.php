<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\StoreTagRequest;
use App\Models\VaultTag;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        $tags = VaultTag::query()
            ->withCount('credentials')
            ->orderBy('name')
            ->get()
            ->map(fn (VaultTag $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'credentials_count' => $t->credentials_count,
            ]);

        return Inertia::render('Tags', ['tags' => $tags]);
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        VaultTag::create($request->validated());

        return back()->with('success', 'Tag created.');
    }

    public function update(StoreTagRequest $request, VaultTag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return back()->with('success', 'Tag saved.');
    }

    public function destroy(VaultTag $tag): RedirectResponse
    {
        $tag->delete();

        return back()->with('success', 'Tag deleted.');
    }
}
