<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Services\TeacherImportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class TeacherController extends Controller
{
    public function index(): View
    {
        return view('livewire.admin.teacher-list');
    }

    public function create(): View
    {
        return view('livewire.admin.teacher-create');
    }

    public function show(Teacher $teacher): View
    {
        return view('livewire.admin.teacher-show', compact('teacher'));
    }

    public function edit(Teacher $teacher): View
    {
        return view('livewire.admin.teacher-edit', compact('teacher'));
    }

    /**
     * Export teachers to CSV with applied filters.
     */
    public function export(): BaseResponse
    {
        $query = Teacher::query()->with(['user']);

        // Apply filters from session
        $search = session('export_search');
        $statusFilter = session('export_status_filter');

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $teachers = $query->get();
        $service = new TeacherImportService;
        $filePath = $service->exportToCsv($teachers);

        // Clear session data
        session()->forget(['export_search', 'export_status_filter']);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    /**
     * Download sample CSV file for import.
     */
    public function sampleCsv(): BaseResponse
    {
        $service = new TeacherImportService;
        $filePath = $service->generateSampleCsv();

        return response()->download($filePath, 'teachers_import_sample.csv')->deleteFileAfterSend(true);
    }
}
