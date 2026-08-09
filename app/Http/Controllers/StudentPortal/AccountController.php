<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $student = $user->student;

        $pendingOrdersCount = 0;
        $activeSubscriptionsCount = 0;

        if ($student) {
            $pendingOrdersCount = $student->orders()->where('status', 'pending')->count();
            $activeSubscriptionsCount = $student->classStudents()->where('status', 'active')->count();
        }

        return Inertia::render('Account', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'initials' => $user->initials(),
                'phone' => $student?->phone,
            ],
            'pendingOrdersCount' => $pendingOrdersCount,
            'activeSubscriptionsCount' => $activeSubscriptionsCount,
        ]);
    }

    /** Switch the session locale (BM / EN toggle). */
    public function setLocale(Request $request): RedirectResponse
    {
        $locale = $request->input('locale');

        if (in_array($locale, ['en', 'ms'], true)) {
            $request->session()->put('locale', $locale);
        }

        return back();
    }
}
