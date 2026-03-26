<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeEmergencyContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrEmergencyContactController extends Controller
{
    /**
     * List emergency contacts for an employee.
     */
    public function index(Employee $employee): JsonResponse
    {
        return response()->json(['data' => $employee->emergencyContacts]);
    }

    /**
     * Create an emergency contact for an employee.
     */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
        ]);

        $contact = $employee->emergencyContacts()->create($validated);

        return response()->json([
            'data' => $contact,
            'message' => 'Emergency contact created successfully.',
        ], 201);
    }

    /**
     * Update an emergency contact.
     */
    public function update(Request $request, EmployeeEmergencyContact $emergencyContact): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'relationship' => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
        ]);

        $emergencyContact->update($validated);

        return response()->json([
            'data' => $emergencyContact,
            'message' => 'Emergency contact updated successfully.',
        ]);
    }

    /**
     * Delete an emergency contact.
     */
    public function destroy(EmployeeEmergencyContact $emergencyContact): JsonResponse
    {
        $emergencyContact->delete();

        return response()->json(['message' => 'Emergency contact deleted successfully.']);
    }
}
