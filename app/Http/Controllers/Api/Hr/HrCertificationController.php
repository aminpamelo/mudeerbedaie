<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrCertificationController extends Controller
{
    public function index(): JsonResponse
    {
        $certifications = Certification::withCount('employeeCertifications')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $certifications]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'validity_months' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $certification = Certification::create($validated);

        return response()->json([
            'message' => 'Certification type created.',
            'data' => $certification,
        ], 201);
    }

    public function update(Request $request, Certification $certification): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'validity_months' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $certification->update($validated);

        return response()->json([
            'message' => 'Certification updated.',
            'data' => $certification,
        ]);
    }

    public function destroy(Certification $certification): JsonResponse
    {
        $certification->delete();

        return response()->json(['message' => 'Certification deleted.']);
    }
}
