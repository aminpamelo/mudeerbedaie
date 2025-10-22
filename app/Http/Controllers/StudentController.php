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

    /**
     * Export CRM database to CSV with applied filters.
     */
    public function exportCrm(): BaseResponse
    {
        $query = Student::query()->with(['user', 'paidOrders.items.product']);

        // Apply filters from session
        $search = session('crm_export_search');
        $countryFilter = session('crm_export_country_filter');

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            })
                ->orWhere('student_id', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%');
        }

        if ($countryFilter) {
            $query->where('country', $countryFilter);
        }

        $students = $query->get();

        // Generate CSV
        $filename = 'crm_database_'.date('Y-m-d_His').'.csv';
        $filePath = storage_path('app/temp/'.$filename);

        // Create temp directory if it doesn't exist
        if (! \File::exists(storage_path('app/temp'))) {
            \File::makeDirectory(storage_path('app/temp'), 0755, true);
        }

        $handle = fopen($filePath, 'w');

        // Add CSV headers
        fputcsv($handle, [
            'Name',
            'Email',
            'Phone',
            'Student ID',
            'Created On',
            'Country/Region',
            'Total Revenue',
            'Number of Orders',
            'Purchased Products',
        ]);

        // Add data rows
        foreach ($students as $student) {
            $products = $student->paidOrders
                ->flatMap(fn ($order) => $order->items)
                ->pluck('product.title')
                ->unique()
                ->implode(', ');

            fputcsv($handle, [
                $student->user->name ?? '',
                $student->user->email ?? '',
                $student->phone ?? '',
                $student->student_id ?? '',
                $student->created_at->format('Y-m-d H:i:s'),
                $student->country ?? '',
                number_format($student->paidOrders->sum('total_amount'), 2),
                $student->paidOrders->count(),
                $products ?: 'No purchases',
            ]);
        }

        fclose($handle);

        // Clear session data
        session()->forget(['crm_export_search', 'crm_export_country_filter']);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }
}
