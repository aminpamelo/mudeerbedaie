<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\ClassResource;
use App\Models\ClassSession;
use App\Models\ClassStudent;
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

    /**
     * Course learning hub: surfaces the LMS content (the student's classes,
     * recordings, and materials) for a course they are enrolled in, or a
     * curriculum preview + enrol CTA for one they are not.
     */
    public function show(Request $request, Course $course): Response
    {
        if (! $course->isActive()) {
            abort(404);
        }

        $student = $request->user()->student;

        $course->load(['teacher.user', 'feeSettings']);

        $isEnrolled = $student
            ? Enrollment::where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->whereIn('status', ['enrolled', 'active'])
                ->exists()
            : false;

        $myClasses = collect();
        $recordings = collect();
        $resources = collect();
        $totalSessions = 0;
        $completedSessions = 0;

        if ($student) {
            $classStudents = ClassStudent::where('student_id', $student->id)
                ->whereHas('class', fn ($q) => $q->where('course_id', $course->id))
                ->with(['class.teacher.user', 'class.sessions'])
                ->orderByDesc('enrolled_at')
                ->get();

            $classIds = $classStudents->pluck('class_id');

            $myClasses = $classStudents->map(function (ClassStudent $cs) {
                $class = $cs->class;
                $total = $class->sessions->count();
                $completed = $class->sessions->whereIn('status', ['completed', 'no_show'])->count();
                $next = $class->sessions
                    ->where('status', 'scheduled')
                    ->sortBy('session_date')
                    ->first();

                return [
                    'id' => $class->id,
                    'title' => $class->title,
                    'teacher_name' => $class->teacher?->user?->name,
                    'status' => $cs->status,
                    'total_sessions' => $total,
                    'completed_sessions' => $completed,
                    'progress' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                    'next_session' => $next
                        ? $next->session_date->format('M j, Y').($next->session_time ? ' · '.$next->session_time->format('g:i A') : '')
                        : null,
                ];
            })->values();

            if ($classIds->isNotEmpty()) {
                $totalSessions = ClassSession::whereIn('class_id', $classIds)->count();
                $completedSessions = ClassSession::whereIn('class_id', $classIds)
                    ->whereIn('status', ['completed', 'no_show'])
                    ->count();

                $recordings = ClassSession::whereIn('class_id', $classIds)
                    ->whereNotNull('recording_url')
                    ->with('class:id,title')
                    ->orderByDesc('session_date')
                    ->limit(12)
                    ->get()
                    ->map(fn (ClassSession $s) => [
                        'id' => $s->id,
                        'class_title' => $s->class?->title,
                        'session_date' => $s->session_date->format('M j, Y'),
                        'recording_url' => $s->recording_url,
                    ])->values();

                $resources = ClassResource::whereIn('class_id', $classIds)
                    ->published()
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (ClassResource $r) => [
                        'id' => $r->id,
                        'title' => $r->title,
                        'type' => $r->type,
                        'url' => $r->accessible_url,
                        'created_at' => $r->created_at->format('M j, Y'),
                    ])
                    ->filter(fn ($r) => filled($r['url']))
                    ->values();
            }
        }

        // A student has learning access if they hold an active enrolment OR are
        // already a member of at least one class in this course.
        $hasAccess = $isEnrolled || $myClasses->isNotEmpty();

        // Curriculum preview for students who have not enrolled yet.
        $previewClasses = collect();
        if (! $hasAccess) {
            $previewClasses = $course->classes()
                ->whereIn('status', ['active', 'completed'])
                ->with('teacher.user')
                ->withCount('sessions')
                ->orderBy('date_time')
                ->get()
                ->map(fn ($c) => [
                    'title' => $c->title,
                    'teacher_name' => $c->teacher?->user?->name,
                    'sessions_count' => $c->sessions_count,
                ])->values();
        }

        return Inertia::render('CourseShow', [
            'course' => [
                'id' => $course->id,
                'name' => $course->name,
                'description' => $course->description,
                'short_description' => $course->short_description,
                'thumbnail_url' => $course->thumbnail_url,
                'teacher_name' => $course->teacher?->user?->name,
                'fee' => $course->feeSettings?->fee_amount ?? 0,
                'fee_formatted' => $course->formatted_fee,
                'billing_interval' => $course->feeSettings?->billing_interval,
                'is_enrolled' => $isEnrolled,
                'has_access' => $hasAccess,
                'enroll_url' => filled($course->slug)
                    ? route('storefront.course', $course->slug)
                    : route('storefront.courses'),
            ],
            'myClasses' => $myClasses,
            'recordings' => $recordings,
            'resources' => $resources,
            'previewClasses' => $previewClasses,
            'stats' => [
                'overallProgress' => $totalSessions > 0 ? (int) round($completedSessions / $totalSessions * 100) : 0,
                'totalSessions' => $totalSessions,
                'completedSessions' => $completedSessions,
                'classCount' => $hasAccess ? $myClasses->count() : $previewClasses->count(),
            ],
        ]);
    }
}
