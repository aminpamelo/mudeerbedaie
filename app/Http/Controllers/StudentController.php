<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentImportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('students.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('students.create');
    }

    /**
     * Display the specified resource.
     */
    public function show(Student $student): View
    {
        $student->load(['user', 'enrollments.course', 'activeEnrollments.course']);

        return view('students.show', compact('student'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Student $student): View
    {
        $student->load(['user']);

        return view('students.edit', compact('student'));
    }

    /**
     * Export students to CSV with applied filters.
     */
    public function export(): BaseResponse
    {
        $query = Student::query()->with(['user']);

        // Apply filters from session
        $search = session('export_search');
        $statusFilter = session('export_status_filter');

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            })
                ->orWhere('student_id', 'like', '%'.$search.'%')
                ->orWhere('ic_number', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%');
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $students = $query->get();
        $service = new StudentImportService;
        $filePath = $service->exportToCsv($students);

        // Clear session data
        session()->forget(['export_search', 'export_status_filter']);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    /**
     * Download sample CSV file for import.
     */
    public function sampleCsv(): BaseResponse
    {
        $service = new StudentImportService;
        $filePath = $service->generateSampleCsv();

        return response()->download($filePath, 'students_import_sample.csv')->deleteFileAfterSend(true);
    }
}
