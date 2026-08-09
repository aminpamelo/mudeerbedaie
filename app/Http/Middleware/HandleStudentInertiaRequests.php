<?php

namespace App\Http\Middleware;

use App\Models\Course;
use Illuminate\Http\Request;

/**
 * Inertia middleware for the Student portal bundle.
 *
 * Overrides the root view to load the student-portal Blade shell and
 * shares student-specific stats used by the Courses page.
 */
class HandleStudentInertiaRequests extends HandleInertiaRequests
{
    protected $rootView = 'student-portal.app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'studentStats' => fn () => $this->studentStats($request),
            'unreadNotificationCount' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'locale' => app()->getLocale(),
            'translations' => fn () => [
                'student' => __('student'),
                'navigation' => __('navigation'),
            ],
        ];
    }

    /**
     * @return array{totalCourses: int, myEnrollments: int}
     */
    private function studentStats(Request $request): array
    {
        $user = $request->user();

        if (! $user || ! $user->student) {
            return ['totalCourses' => 0, 'myEnrollments' => 0];
        }

        return [
            'totalCourses' => Course::where('status', 'active')->count(),
            'myEnrollments' => $user->student->activeEnrollments()->count(),
        ];
    }
}
