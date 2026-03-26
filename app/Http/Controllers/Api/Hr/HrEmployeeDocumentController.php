<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrEmployeeDocumentController extends Controller
{
    /**
     * List documents for an employee.
     */
    public function index(Employee $employee): JsonResponse
    {
        $documents = $employee->documents()->latest('uploaded_at')->get();

        return response()->json(['data' => $documents]);
    }

    /**
     * Upload a document for an employee.
     */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'document_type' => ['required', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $file = $request->file('file');
        $path = $file->store('employee-documents', 'public');

        $document = $employee->documents()->create([
            'document_type' => $validated['document_type'],
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_at' => now(),
            'expiry_date' => $validated['expiry_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'data' => $document,
            'message' => 'Document uploaded successfully.',
        ], 201);
    }

    /**
     * Download a document.
     */
    public function download(Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        abort_if($document->employee_id !== $employee->id, 404);

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    /**
     * Delete a document.
     */
    public function destroy(Employee $employee, EmployeeDocument $document): JsonResponse
    {
        abort_if($document->employee_id !== $employee->id, 404);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document deleted successfully.']);
    }
}
