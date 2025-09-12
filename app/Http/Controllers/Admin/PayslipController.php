<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePayslipsJob;
use App\Models\Payslip;
use App\Models\User;
use App\Services\PayslipGenerationService;
use App\Services\PayslipSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayslipController extends Controller
{
    public function __construct(
        private PayslipGenerationService $generationService,
        private PayslipSyncService $syncService
    ) {}

    public function index(Request $request)
    {
        $query = Payslip::with(['teacher', 'generatedBy']);

        // Apply filters
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('month')) {
            $query->where('month', $request->month);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        // Search
        if ($request->filled('search')) {
            $query->whereHas('teacher', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%');
            });
        }

        $payslips = $query->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Get filter options
        $teachers = User::whereHas('teacher')->orderBy('name')->get();
        $availableMonths = $this->generationService->getAvailableMonthsForPayslips();

        return view('admin.payslips.index', compact('payslips', 'teachers', 'availableMonths'));
    }

    public function show(Payslip $payslip)
    {
        $payslip->load(['teacher', 'generatedBy', 'sessions.class.course', 'sessions.attendances']);

        return view('admin.payslips.show', compact('payslip'));
    }

    public function create()
    {
        $teachers = User::whereHas('teacher')->orderBy('name')->get();
        $availableMonths = $this->generationService->getAvailableMonthsForPayslips();

        return view('admin.payslips.create', compact('teachers', 'availableMonths'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_ids' => 'required|array|min:1',
            'teacher_ids.*' => 'exists:users,id',
            'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'generate_in_background' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $teacherIds = $request->teacher_ids;
            $month = $request->month;

            if ($request->boolean('generate_in_background', true) && count($teacherIds) > 3) {
                // Generate in background for multiple teachers
                GeneratePayslipsJob::dispatch($teacherIds, $month, auth()->id());

                return redirect()->route('admin.payslips.index')
                    ->with('success', 'Payslip generation has been queued. You will be notified when complete.');
            } else {
                // Generate synchronously for single teacher or small batches
                $results = [];
                $errors = [];

                foreach ($teacherIds as $teacherId) {
                    try {
                        $teacher = User::find($teacherId);
                        $payslip = $this->generationService->generateForTeacher($teacher, $month, auth()->user());
                        $results[] = $payslip;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to generate payslip for teacher ID {$teacherId}: ".$e->getMessage();
                    }
                }

                if (count($results) > 0) {
                    $message = count($results) === 1
                        ? 'Payslip generated successfully.'
                        : count($results).' payslips generated successfully.';

                    if (count($errors) > 0) {
                        $message .= ' Some errors occurred: '.implode(', ', $errors);
                    }

                    return redirect()->route('admin.payslips.index')->with('success', $message);
                } else {
                    return redirect()->back()
                        ->with('error', 'No payslips were generated. '.implode(', ', $errors))
                        ->withInput();
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to generate payslips: '.$e->getMessage())
                ->withInput();
        }
    }

    public function edit(Payslip $payslip)
    {
        if (! $payslip->canBeEdited()) {
            return redirect()->route('admin.payslips.show', $payslip)
                ->with('error', 'Only draft payslips can be edited.');
        }

        $payslip->load(['teacher', 'sessions.class.course', 'sessions.attendances']);

        return view('admin.payslips.edit', compact('payslip'));
    }

    public function update(Request $request, Payslip $payslip)
    {
        if (! $payslip->canBeEdited()) {
            return redirect()->route('admin.payslips.show', $payslip)
                ->with('error', 'Only draft payslips can be edited.');
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'session_ids' => 'array',
            'session_ids.*' => 'exists:class_sessions,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Update notes
            $payslip->update([
                'notes' => $request->notes,
            ]);

            // Sync sessions if provided
            if ($request->has('session_ids')) {
                $payslip->syncSessions($request->session_ids);
            }

            return redirect()->route('admin.payslips.show', $payslip)
                ->with('success', 'Payslip updated successfully.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update payslip: '.$e->getMessage())
                ->withInput();
        }
    }

    public function destroy(Payslip $payslip)
    {
        if (! $payslip->canBeEdited()) {
            return redirect()->route('admin.payslips.show', $payslip)
                ->with('error', 'Only draft payslips can be deleted.');
        }

        try {
            // Reset all sessions' payout status
            foreach ($payslip->sessions as $session) {
                $session->update(['payout_status' => 'unpaid']);
            }

            $teacherName = $payslip->teacher->name;
            $month = $payslip->formatted_month;

            $payslip->delete();

            return redirect()->route('admin.payslips.index')
                ->with('success', "Payslip for {$teacherName} ({$month}) has been deleted.");

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to delete payslip: '.$e->getMessage());
        }
    }

    public function finalize(Payslip $payslip)
    {
        try {
            $payslip->finalize();

            return redirect()->route('admin.payslips.show', $payslip)
                ->with('success', 'Payslip has been finalized.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to finalize payslip: '.$e->getMessage());
        }
    }

    public function revertToDraft(Payslip $payslip)
    {
        try {
            $payslip->revertToDraft();

            return redirect()->route('admin.payslips.show', $payslip)
                ->with('success', 'Payslip has been reverted to draft.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to revert payslip: '.$e->getMessage());
        }
    }

    public function markAsPaid(Payslip $payslip)
    {
        try {
            $payslip->markAsPaid();

            return redirect()->route('admin.payslips.show', $payslip)
                ->with('success', 'Payslip has been marked as paid.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to mark payslip as paid: '.$e->getMessage());
        }
    }

    public function sync(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'teacher_ids' => 'array',
            'teacher_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $month = $request->month;

            if ($request->has('teacher_ids') && ! empty($request->teacher_ids)) {
                // Sync specific teachers
                $results = $this->syncService->syncPayslipsByTeacherAndMonth($request->teacher_ids, $month);
            } else {
                // Sync all draft payslips for the month
                $results = $this->syncService->syncAllDraftPayslipsForMonth($month);
            }

            $successful = $results->where('status', 'success')->count();
            $failed = $results->where('status', 'error')->count();
            $totalSessions = $results->where('status', 'success')->sum('sessions_added');

            $message = "Sync completed: {$successful} payslips updated";
            if ($totalSessions > 0) {
                $message .= ", {$totalSessions} sessions added";
            }
            if ($failed > 0) {
                $message .= ", {$failed} failed";
            }

            return redirect()->route('admin.payslips.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to sync payslips: '.$e->getMessage());
        }
    }

    public function preview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_ids' => 'required|array|min:1',
            'teacher_ids.*' => 'exists:users,id',
            'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $previews = $this->generationService->generatePayslipsPreview($request->teacher_ids, $request->month);

            return response()->json([
                'success' => true,
                'previews' => $previews,
                'summary' => [
                    'total_teachers' => $previews->count(),
                    'total_sessions' => $previews->sum('total_sessions'),
                    'total_amount' => $previews->sum('total_amount'),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate preview: '.$e->getMessage()], 500);
        }
    }

    public function export(Request $request)
    {
        // Export payslips as CSV - implementation would go here
        // For now, return a placeholder
        return redirect()->back()->with('info', 'Export functionality will be implemented soon.');
    }
}
