<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — Today screen.
 *
 * Batch 1 renders the Placeholder page so the Inertia bundle, layout, and
 * shared props are proven end-to-end. Batch 2 swaps this for the real
 * Today aggregation (upcoming slot, today's sessions, up-next list).
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Placeholder');
    }
}
