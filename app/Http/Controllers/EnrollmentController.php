<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('enrollments.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $courses = Course::where('status', 'active')->get();
        $students = Student::where('status', 'active')->with('user')->get();

        return view('enrollments.create', compact('courses', 'students'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Enrollment $enrollment): View
    {
        $enrollment->load(['student.user', 'course', 'enrolledBy']);

        return view('enrollments.show', compact('enrollment'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Enrollment $enrollment): View
    {
        $enrollment->load(['student.user', 'course', 'enrolledBy']);

        return view('enrollments.edit', compact('enrollment'));
    }
}
