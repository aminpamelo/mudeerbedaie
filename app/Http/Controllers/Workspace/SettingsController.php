<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\TaskCategory;
use App\Models\TmsBadge;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings', [
            'categories' => TaskCategory::orderBy('sort_order')->get(),
            'badges' => TmsBadge::all(),
        ]);
    }
}
