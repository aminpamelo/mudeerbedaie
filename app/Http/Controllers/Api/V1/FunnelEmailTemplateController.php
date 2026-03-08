<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FunnelEmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FunnelEmailTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FunnelEmailTemplate::query()
            ->orderBy('name');

        if ($request->boolean('active', false)) {
            $query->active();
        }

        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $template = FunnelEmailTemplate::findOrFail($id);

        return response()->json([
            'data' => $template,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:funnel_email_templates,slug',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(4);
        }

        $template = FunnelEmailTemplate::create($validated);

        return response()->json([
            'data' => $template,
            'message' => 'Template created successfully.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = FunnelEmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:funnel_email_templates,slug,'.$template->id,
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'design_json' => 'nullable|array',
            'html_content' => 'nullable|string',
            'editor_type' => 'sometimes|in:text,visual',
            'category' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'data' => $template->fresh(),
            'message' => 'Template updated successfully.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $template = FunnelEmailTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'message' => 'Template deleted successfully.',
        ]);
    }

    public function duplicate(int $id): JsonResponse
    {
        $template = FunnelEmailTemplate::findOrFail($id);

        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name.' (Copy)';
        $newTemplate->slug = Str::slug($newTemplate->name).'-'.Str::random(4);
        $newTemplate->save();

        return response()->json([
            'data' => $newTemplate,
            'message' => 'Template duplicated successfully.',
        ], 201);
    }
}
