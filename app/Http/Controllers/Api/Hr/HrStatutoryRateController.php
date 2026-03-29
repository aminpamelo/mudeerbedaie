<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\StatutoryRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrStatutoryRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StatutoryRate::query();

        if ($type = $request->get('type')) {
            $query->forType($type);
        }

        if ($types = $request->get('types')) {
            $query->whereIn('type', explode(',', $types));
        }

        if ($request->boolean('current_only', false)) {
            $query->current();
        }

        $rates = $query->orderBy('type')->orderBy('min_salary')->get();

        return response()->json(['data' => $rates]);
    }

    public function update(Request $request, StatutoryRate $statutoryRate): JsonResponse
    {
        $validated = $request->validate([
            'min_salary' => ['required', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'gt:min_salary'],
            'rate_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);

        $statutoryRate->update($validated);

        return response()->json([
            'data' => $statutoryRate->fresh(),
            'message' => 'Statutory rate updated successfully.',
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:epf_employee,epf_employer,socso_employee,socso_employer,eis_employee,eis_employer'],
            'effective_from' => ['required', 'date'],
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.min_salary' => ['required', 'numeric', 'min:0'],
            'rates.*.max_salary' => ['nullable', 'numeric'],
            'rates.*.rate_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rates.*.fixed_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            // End all existing rates of this type
            StatutoryRate::forType($validated['type'])
                ->whereNull('effective_to')
                ->update(['effective_to' => now()->subDay()->toDateString()]);

            // Create new rates
            foreach ($validated['rates'] as $rateData) {
                StatutoryRate::create([
                    'type' => $validated['type'],
                    'min_salary' => $rateData['min_salary'],
                    'max_salary' => $rateData['max_salary'] ?? null,
                    'rate_percentage' => $rateData['rate_percentage'] ?? null,
                    'fixed_amount' => $rateData['fixed_amount'] ?? null,
                    'effective_from' => $validated['effective_from'],
                    'effective_to' => null,
                ]);
            }
        });

        return response()->json([
            'message' => 'Statutory rates updated successfully.',
        ]);
    }
}
