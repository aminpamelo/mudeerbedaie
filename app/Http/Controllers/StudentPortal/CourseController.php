<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /** Switch the session locale (BM / EN toggle). */
    public function setLocale(Request $request): RedirectResponse
    {
        $locale = $request->input('locale');

        if (in_array($locale, ['en', 'ms'], true)) {
            $request->session()->put('locale', $locale);
        }

        return back();
    }

    public function index(Request $request): Response
    {
        $student = $request->user()->student;

        $query = Course::query()
            ->with(['teacher.user', 'feeSettings'])
            ->withCount('activeEnrollments as student_count')
            ->where('status', 'active')
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('teacher.user', fn ($t) => $t->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->input('teacher'), function ($q, $teacherId) {
                $q->where('teacher_id', $teacherId);
            })
            ->when($request->input('status') === 'enrolled' && $student, function ($q) use ($student) {
                $q->whereHas('enrollments', fn ($e) => $e->where('student_id', $student->id)->whereIn('status', ['enrolled', 'active']));
            })
            ->when($request->input('status') === 'not_enrolled' && $student, function ($q) use ($student) {
                $q->whereDoesntHave('enrollments', fn ($e) => $e->where('student_id', $student->id)->whereIn('status', ['enrolled', 'active']));
            })
            ->when($request->input('fee'), function ($q, $fee) {
                match ($fee) {
                    'free' => $q->whereHas('feeSettings', fn ($f) => $f->where('fee_amount', 0)),
                    '1-50' => $q->whereHas('feeSettings', fn ($f) => $f->whereBetween('fee_amount', [1, 50])),
                    '51-100' => $q->whereHas('feeSettings', fn ($f) => $f->whereBetween('fee_amount', [51, 100])),
                    '101+' => $q->whereHas('feeSettings', fn ($f) => $f->where('fee_amount', '>', 100)),
                    default => null,
                };
            })
            ->orderBy('name');

        $courses = $query->paginate(12)->withQueryString();

        // Map courses to a simpler shape for the frontend
        $enrolledCourseIds = $student
            ? Enrollment::where('student_id', $student->id)
                ->whereIn('status', ['enrolled', 'active'])
                ->pluck('course_id')
                ->all()
            : [];

        $courses->getCollection()->transform(function (Course $course) use ($enrolledCourseIds) {
            return [
                'id' => $course->id,
                'name' => $course->name,
                'description' => $course->description,
                'thumbnail_url' => $course->thumbnail_url,
                'teacher_name' => $course->teacher?->user?->name,
                'student_count' => $course->student_count,
                'fee' => $course->feeSettings?->fee_amount ?? 0,
                'fee_formatted' => $course->formatted_fee,
                'billing_interval' => $course->feeSettings?->billing_interval,
                'is_enrolled' => in_array($course->id, $enrolledCourseIds),
            ];
        });

        // Teachers for filter dropdown
        $teachers = Course::query()
            ->with('teacher.user')
            ->where('status', 'active')
            ->get()
            ->pluck('teacher')
            ->filter(fn ($t) => $t && $t->user)
            ->unique('id')
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->user->name])
            ->values();

        return Inertia::render('Courses', [
            'courses' => $courses,
            'teachers' => $teachers,
            'filters' => [
                'search' => $request->input('search', ''),
                'teacher' => $request->input('teacher', ''),
                'status' => $request->input('status', ''),
                'fee' => $request->input('fee', ''),
            ],
        ]);
    }
}
