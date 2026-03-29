<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCertification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrEmployeeCertificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeCertification::query()
            ->with([
                'employee:id,full_name,employee_id,department_id',
                'employee.department:id,name',
                'certification:id,name,issuing_body,validity_months',
            ]);

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($certificationId = $request->get('certification_id')) {
            $query->where('certification_id', $certificationId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $certs = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($certs);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'certification_id' => ['required', 'exists:certifications,id'],
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'issued_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:issued_date'],
            'certificate_path' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $cert = EmployeeCertification::create(array_merge($validated, [
            'status' => 'active',
        ]));

        return response()->json([
            'message' => 'Employee certification added.',
            'data' => $cert->load(['employee:id,full_name', 'certification:id,name']),
        ], 201);
    }

    public function update(Request $request, EmployeeCertification $employeeCertification): JsonResponse
    {
        $validated = $request->validate([
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'issued_date' => ['sometimes', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'certificate_path' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:active,expired,revoked'],
            'notes' => ['nullable', 'string'],
        ]);

        $employeeCertification->update($validated);

        return response()->json([
            'message' => 'Certification updated.',
            'data' => $employeeCertification,
        ]);
    }

    public function destroy(EmployeeCertification $employeeCertification): JsonResponse
    {
        $employeeCertification->delete();

        return response()->json(['message' => 'Certification removed.']);
    }

    public function expiring(Request $request): JsonResponse
    {
        $days = $request->get('days', 90);

        $certs = EmployeeCertification::expiringSoon($days)
            ->with([
                'employee:id,full_name,employee_id',
                'certification:id,name',
            ])
            ->orderBy('expiry_date')
            ->get();

        return response()->json(['data' => $certs]);
    }
}
