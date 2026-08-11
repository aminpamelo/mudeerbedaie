<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Models\FormCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-managed global categories used to classify forms (and identify who
 * uses them for what).
 */
class FormCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = FormCategory::query()
            ->ordered()
            ->withCount('forms')
            ->get()
            ->map(fn (FormCategory $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'color' => $c->color,
                'description' => $c->description,
                'is_active' => $c->is_active,
                'sort_order' => $c->sort_order,
                'forms_count' => (int) $c->forms_count,
            ]);

        return Inertia::render('Categories', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        FormCategory::create($data);

        return back()->with('success', 'Kategori berjaya ditambah.');
    }

    public function update(Request $request, FormCategory $category): RedirectResponse
    {
        $data = $this->validateData($request, $category);

        $category->update($data);

        return back()->with('success', 'Kategori berjaya dikemas kini.');
    }

    public function destroy(FormCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', 'Kategori telah dibuang.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?FormCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
