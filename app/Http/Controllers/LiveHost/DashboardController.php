<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        Inertia::setRootView('livehost.app');

        return Inertia::render('Dashboard', [
            'navCounts' => [
                'hosts' => 0,
                'schedules' => 0,
                'sessions' => 0,
            ],
        ]);
    }
}
