<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeEmergencyContact;
use App\Models\EmployeeHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyProfileController extends Controller
{
    /**
     * Get the logged-in user's employee profile.
     */
    public function show(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $employee->load([
            'department',
            'position',
            'emergencyContacts',
            'documents' => fn ($q) => $q->latest('uploaded_at'),
            'histories' => fn ($q) => $q->latest()->limit(10),
        ]);

        return response()->json(['data' => $employee]);
    }

    /**
     * Update the logged-in user's allowed profile fields.
     */
    public function update(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $validated = $request->validate([
            'phone' => ['sometimes', 'string', 'max:20'],
            'personal_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postcode' => ['sometimes', 'nullable', 'string', 'max:10'],
            'marital_status' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        foreach ($validated as $field => $newValue) {
            $oldValue = $employee->{$field};

            if ((string) $oldValue !== (string) $newValue) {
                EmployeeHistory::create([
                    'employee_id' => $employee->id,
                    'field_name' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'changed_by' => $request->user()->id,
                    'change_type' => 'general_update',
                    'effective_date' => now(),
                ]);
            }
        }

        $employee->update($validated);

        return response()->json([
            'data' => $employee->fresh(['department', 'position']),
            'message' => 'Profile updated successfully.',
        ]);
    }

    /**
     * Get the logged-in user's employee documents.
     */
    public function documents(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $documents = $employee->documents()->latest('uploaded_at')->get();

        return response()->json(['data' => $documents]);
    }

    /**
     * Upload a document for the logged-in employee.
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'document_type' => ['required', 'string', 'in:ic_front,ic_back,offer_letter,contract,bank_statement,epf_form,socso_form'],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $path = $file->store("employee-documents/{$employee->id}", 'public');

        $document = $employee->documents()->create([
            'document_type' => $validated['document_type'],
            'file_name' => $validated['title'],
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_at' => now(),
        ]);

        return response()->json([
            'data' => $document,
            'message' => 'Document uploaded successfully.',
        ], 201);
    }

    /**
     * Get the logged-in user's emergency contacts.
     */
    public function emergencyContacts(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        return response()->json(['data' => $employee->emergencyContacts]);
    }

    /**
     * Create an emergency contact for the logged-in employee.
     */
    public function storeEmergencyContact(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $contact = $employee->emergencyContacts()->create($validated);

        return response()->json([
            'data' => $contact,
            'message' => 'Emergency contact created successfully.',
        ], 201);
    }

    /**
     * Update an emergency contact for the logged-in employee.
     */
    public function updateEmergencyContact(Request $request, int $contactId): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $contact = EmployeeEmergencyContact::where('id', $contactId)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'relationship' => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $contact->update($validated);

        return response()->json([
            'data' => $contact,
            'message' => 'Emergency contact updated successfully.',
        ]);
    }

    /**
     * Delete an emergency contact for the logged-in employee.
     */
    public function deleteEmergencyContact(Request $request, int $contactId): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $contact = EmployeeEmergencyContact::where('id', $contactId)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $contact->delete();

        return response()->json(['message' => 'Emergency contact deleted successfully.']);
    }
}
