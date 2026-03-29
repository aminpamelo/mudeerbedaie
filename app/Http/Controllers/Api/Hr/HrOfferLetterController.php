<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\OfferLetter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrOfferLetterController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'applicant_id' => ['required', 'exists:applicants,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'offered_salary' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date', 'after:today'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,intern'],
            'template_data' => ['nullable', 'array'],
        ]);

        $offer = OfferLetter::create(array_merge($validated, [
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Offer letter created.',
            'data' => $offer->load(['applicant:id,full_name', 'position:id,title']),
        ], 201);
    }

    public function show(OfferLetter $offerLetter): JsonResponse
    {
        return response()->json([
            'data' => $offerLetter->load(['applicant:id,full_name,email', 'position:id,title']),
        ]);
    }

    public function update(Request $request, OfferLetter $offerLetter): JsonResponse
    {
        if ($offerLetter->status !== 'draft') {
            return response()->json(['message' => 'Only draft offers can be updated.'], 422);
        }

        $validated = $request->validate([
            'offered_salary' => ['sometimes', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'employment_type' => ['sometimes', 'in:full_time,part_time,contract,intern'],
            'template_data' => ['nullable', 'array'],
        ]);

        $offerLetter->update($validated);

        return response()->json([
            'message' => 'Offer letter updated.',
            'data' => $offerLetter,
        ]);
    }

    public function send(OfferLetter $offerLetter): JsonResponse
    {
        $offerLetter->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Offer letter marked as sent.',
            'data' => $offerLetter,
        ]);
    }

    public function respond(Request $request, OfferLetter $offerLetter): JsonResponse
    {
        $validated = $request->validate([
            'response' => ['required', 'in:accepted,rejected'],
        ]);

        $offerLetter->update([
            'status' => $validated['response'],
            'responded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Offer response recorded.',
            'data' => $offerLetter,
        ]);
    }
}
