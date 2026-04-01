<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RejectExitPermissionRequest;
use App\Models\AttendanceLog;
use App\Models\ExitPermissionNotifier;
use App\Models\OfficeExitPermission;
use App\Notifications\Hr\ExitPermissionApproved;
use App\Notifications\Hr\ExitPermissionRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrOfficeExitPermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OfficeExitPermission::with(['employee.department', 'approver'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->department_id));
        }

        if ($request->filled('errand_type')) {
            $query->where('errand_type', $request->errand_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('exit_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('exit_date', '<=', $request->date_to);
        }

        return response()->json([
            'data' => $query->paginate(20),
        ]);
    }

    public function show(OfficeExitPermission $officeExitPermission): JsonResponse
    {
        $officeExitPermission->load(['employee.department', 'approver']);

        return response()->json(['data' => $officeExitPermission]);
    }

    public function approve(Request $request, OfficeExitPermission $officeExitPermission): JsonResponse
    {
        if (! $officeExitPermission->isPending()) {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        return DB::transaction(function () use ($request, $officeExitPermission): JsonResponse {
            $officeExitPermission->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $this->createAttendanceNote($officeExitPermission);
            $this->sendApprovalNotifications($officeExitPermission, $request->user());

            return response()->json([
                'data' => $officeExitPermission->fresh(['employee', 'approver']),
                'message' => 'Exit permission approved successfully.',
            ]);
        });
    }

    public function reject(RejectExitPermissionRequest $request, OfficeExitPermission $officeExitPermission): JsonResponse
    {
        if (! $officeExitPermission->isPending()) {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        return DB::transaction(function () use ($request, $officeExitPermission): JsonResponse {
            $officeExitPermission->update([
                'status' => 'rejected',
                'rejection_reason' => $request->validated()['rejection_reason'],
            ]);

            $officeExitPermission->load('employee.user');
            if ($officeExitPermission->employee->user) {
                $officeExitPermission->employee->user->notify(
                    new ExitPermissionRejected($officeExitPermission, $request->user())
                );
            }

            return response()->json([
                'data' => $officeExitPermission->fresh(['employee', 'approver']),
                'message' => 'Exit permission rejected.',
            ]);
        });
    }

    public function pdf(OfficeExitPermission $officeExitPermission): mixed
    {
        if (! $officeExitPermission->isApproved()) {
            return response()->json(['message' => 'PDF is only available for approved permissions.'], 403);
        }

        $officeExitPermission->load(['employee.department', 'approver']);

        $pdf = app('dompdf.wrapper')->loadView('pdf.exit-permission', [
            'permission' => $officeExitPermission,
        ]);

        $filename = $officeExitPermission->permission_number.'.pdf';

        return $pdf->download($filename);
    }

    private function createAttendanceNote(OfficeExitPermission $permission): void
    {
        $note = 'Exit: '.$permission->exit_time.' - '.$permission->return_time
            .' ('.($permission->errand_type === 'company' ? 'Company' : 'Personal').')';

        AttendanceLog::where('employee_id', $permission->employee_id)
            ->whereDate('date', $permission->exit_date)
            ->each(function (AttendanceLog $log) use ($note): void {
                $existing = $log->remarks ?? '';
                $log->update([
                    'remarks' => $existing !== '' ? $existing.' | '.$note : $note,
                ]);
            });

        $permission->update(['attendance_note_created' => true]);
    }

    private function sendApprovalNotifications(OfficeExitPermission $permission, mixed $approver): void
    {
        $permission->load('employee.user');

        if ($permission->employee->user) {
            $permission->employee->user->notify(
                new ExitPermissionApproved($permission, $approver)
            );
        }

        $notifiers = ExitPermissionNotifier::with('employee.user')
            ->where('department_id', $permission->employee->department_id)
            ->get();

        foreach ($notifiers as $notifier) {
            if ($notifier->employee->user && $notifier->employee->id !== $permission->employee_id) {
                $notifier->employee->user->notify(
                    new ExitPermissionApproved($permission, $approver, isCc: true)
                );
            }
        }

        $permission->update(['cc_notified_at' => now()]);
    }
}
