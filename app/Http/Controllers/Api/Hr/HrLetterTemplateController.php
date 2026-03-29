<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\LetterTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrLetterTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LetterTemplate::query();

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $templates = $query->orderBy('type')->orderBy('name')->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:verbal_warning,first_written,second_written,show_cause,termination,offer_letter,resignation_acceptance'],
            'content' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $template = LetterTemplate::create($validated);

        return response()->json([
            'message' => 'Letter template created.',
            'data' => $template,
        ], 201);
    }

    public function update(Request $request, LetterTemplate $letterTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:verbal_warning,first_written,second_written,show_cause,termination,offer_letter,resignation_acceptance'],
            'content' => ['sometimes', 'string'],
            'is_active' => ['boolean'],
        ]);

        $letterTemplate->update($validated);

        return response()->json([
            'message' => 'Letter template updated.',
            'data' => $letterTemplate,
        ]);
    }

    public function destroy(LetterTemplate $letterTemplate): JsonResponse
    {
        $letterTemplate->delete();

        return response()->json(['message' => 'Letter template deleted.']);
    }
}
