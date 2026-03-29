<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrOnboardingTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $templates = OnboardingTemplate::query()
            ->with(['department:id,name', 'items'])
            ->withCount('items')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.assigned_role' => ['nullable', 'string', 'max:50'],
            'items.*.due_days' => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        return DB::transaction(function () use ($validated) {
            $template = OnboardingTemplate::create([
                'name' => $validated['name'],
                'department_id' => $validated['department_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['items'] as $item) {
                OnboardingTemplateItem::create(array_merge($item, [
                    'onboarding_template_id' => $template->id,
                ]));
            }

            return response()->json([
                'message' => 'Onboarding template created.',
                'data' => $template->load('items'),
            ], 201);
        });
    }

    public function update(Request $request, OnboardingTemplate $onboardingTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['boolean'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'exists:onboarding_template_items,id'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.assigned_role' => ['nullable', 'string', 'max:50'],
            'items.*.due_days' => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        return DB::transaction(function () use ($validated, $onboardingTemplate) {
            $onboardingTemplate->update(collect($validated)->only(['name', 'department_id', 'is_active'])->toArray());

            if (isset($validated['items'])) {
                $existingIds = collect($validated['items'])->pluck('id')->filter();
                $onboardingTemplate->items()->whereNotIn('id', $existingIds)->delete();

                foreach ($validated['items'] as $item) {
                    if (! empty($item['id'])) {
                        OnboardingTemplateItem::where('id', $item['id'])->update($item);
                    } else {
                        OnboardingTemplateItem::create(array_merge($item, [
                            'onboarding_template_id' => $onboardingTemplate->id,
                        ]));
                    }
                }
            }

            return response()->json([
                'message' => 'Onboarding template updated.',
                'data' => $onboardingTemplate->fresh('items'),
            ]);
        });
    }

    public function destroy(OnboardingTemplate $onboardingTemplate): JsonResponse
    {
        $onboardingTemplate->delete();

        return response()->json(['message' => 'Onboarding template deleted.']);
    }
}
