<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\StoreCategoryRequest;
use App\Models\VaultCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = VaultCategory::query()
            ->withCount('credentials')
            ->ordered()
            ->get()
            ->map(fn (VaultCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'icon' => $c->icon,
                'color' => $c->color,
                'sort_order' => $c->sort_order,
                'credentials_count' => $c->credentials_count,
            ]);

        return Inertia::render('Categories', ['categories' => $categories]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        VaultCategory::create($request->validated());

        return back()->with('success', 'Category created.');
    }

    public function update(StoreCategoryRequest $request, VaultCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return back()->with('success', 'Category saved.');
    }

    public function destroy(VaultCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
