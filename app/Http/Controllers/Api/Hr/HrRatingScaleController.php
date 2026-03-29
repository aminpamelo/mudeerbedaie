<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\RatingScale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrRatingScaleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => RatingScale::orderBy('score')->get(),
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scales' => ['required', 'array', 'min:1'],
            'scales.*.score' => ['required', 'integer', 'min:1', 'max:5'],
            'scales.*.label' => ['required', 'string', 'max:50'],
            'scales.*.description' => ['nullable', 'string'],
            'scales.*.color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        foreach ($validated['scales'] as $scale) {
            RatingScale::updateOrCreate(
                ['score' => $scale['score']],
                $scale
            );
        }

        return response()->json([
            'message' => 'Rating scales updated.',
            'data' => RatingScale::orderBy('score')->get(),
        ]);
    }
}
