<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeRequest;
use App\Http\Requests\Hr\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrEmployeeController extends Controller
{
    /**
     * Paginated list with search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->with(['department:id,name', 'position:id,title']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($employmentType = $request->get('employment_type')) {
            $query->where('employment_type', $employmentType);
        }

        $sortBy = $request->get('sort_by', 'full_name');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $employees = $query->paginate($request->get('per_page', 15));

        return response()->json($employees);
    }

    /**
     * Create new employee with user account and initial history.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request) {
            // Create user account
            $password = Str::random(12);
            $user = User::create([
                'name' => $validated['full_name'],
                'email' => $validated['personal_email'],
                'phone' => $validated['phone'],
                'password' => bcrypt($password),
                'role' => 'employee',
                'status' => 'active',
            ]);

            // Handle profile photo upload
            $profilePhotoPath = null;
            if ($request->hasFile('profile_photo')) {
                $profilePhotoPath = $request->file('profile_photo')->store('employee-photos', 'public');
            }

            // Generate employee ID
            $employeeId = Employee::generateEmployeeId();

            // Create employee record
            $employee = Employee::create(array_merge($validated, [
                'user_id' => $user->id,
                'employee_id' => $employeeId,
                'profile_photo' => $profilePhotoPath,
                'status' => 'active',
            ]));

            // Create initial history entry
            EmployeeHistory::create([
                'employee_id' => $employee->id,
                'change_type' => 'general_update',
                'field_name' => 'status',
                'old_value' => null,
                'new_value' => 'active',
                'effective_date' => $validated['join_date'],
                'remarks' => 'Employee record created',
                'changed_by' => $request->user()->id,
            ]);

            $employee->load(['department', 'position', 'user']);

            return response()->json([
                'data' => $employee,
                'temporary_password' => $password,
                'message' => 'Employee created successfully.',
            ], 201);
        });
    }

    /**
     * Show employee with all relationships.
     */
    public function show(Employee $employee): JsonResponse
    {
        $employee->load([
            'department',
            'position',
            'user:id,name,email',
            'emergencyContacts',
            'documents',
            'histories' => function ($query) {
                $query->with('changedByUser:id,name')->latest('created_at');
            },
        ]);

        return response()->json(['data' => $employee]);
    }

    /**
     * Update employee and track field changes.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $validated = $request->validated();

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            if ($employee->profile_photo) {
                Storage::disk('public')->delete($employee->profile_photo);
            }
            $validated['profile_photo'] = $request->file('profile_photo')->store('employee-photos', 'public');
        }

        // Track changes on key fields
        $trackedFields = ['department_id', 'position_id', 'status', 'employment_type'];
        $changeTypeMap = [
            'department_id' => 'department_transfer',
            'position_id' => 'position_change',
            'status' => 'status_change',
            'employment_type' => 'general_update',
        ];

        foreach ($trackedFields as $field) {
            if (array_key_exists($field, $validated) && $employee->{$field} != $validated[$field]) {
                EmployeeHistory::create([
                    'employee_id' => $employee->id,
                    'change_type' => $changeTypeMap[$field],
                    'field_name' => $field,
                    'old_value' => (string) $employee->{$field},
                    'new_value' => (string) $validated[$field],
                    'effective_date' => now(),
                    'remarks' => "Updated {$field}",
                    'changed_by' => $request->user()->id,
                ]);
            }
        }

        $employee->update($validated);
        $employee->load(['department', 'position']);

        return response()->json([
            'data' => $employee,
            'message' => 'Employee updated successfully.',
        ]);
    }

    /**
     * Update employee status with effective date and remarks.
     */
    public function updateStatus(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,probation,resigned,terminated'],
            'effective_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $oldStatus = $employee->status;

        EmployeeHistory::create([
            'employee_id' => $employee->id,
            'change_type' => 'status_change',
            'field_name' => 'status',
            'old_value' => $oldStatus,
            'new_value' => $validated['status'],
            'effective_date' => $validated['effective_date'],
            'remarks' => $validated['remarks'] ?? null,
            'changed_by' => $request->user()->id,
        ]);

        $employee->update(['status' => $validated['status']]);

        return response()->json([
            'data' => $employee,
            'message' => 'Employee status updated successfully.',
        ]);
    }

    /**
     * Soft delete employee.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return response()->json(['message' => 'Employee deleted successfully.']);
    }

    /**
     * Export employees as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Employee::query()->with(['department:id,name', 'position:id,title']);

        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $employees = $query->orderBy('full_name')->get();

        return response()->streamDownload(function () use ($employees) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Employee ID', 'Full Name', 'Department', 'Position',
                'Employment Type', 'Status', 'Join Date', 'Phone', 'Email',
            ]);

            foreach ($employees as $employee) {
                fputcsv($handle, [
                    $employee->employee_id,
                    $employee->full_name,
                    $employee->department?->name,
                    $employee->position?->title,
                    $employee->employment_type,
                    $employee->status,
                    $employee->join_date?->format('Y-m-d'),
                    $employee->phone,
                    $employee->personal_email,
                ]);
            }

            fclose($handle);
        }, 'employees-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Return the next available employee ID.
     */
    public function nextId(): JsonResponse
    {
        return response()->json([
            'data' => ['next_id' => Employee::generateEmployeeId()],
        ]);
    }
}
