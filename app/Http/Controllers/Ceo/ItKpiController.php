<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Services\Ceo\CeoDashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItKpiController extends Controller
{
    public function index(Request $request, CeoDashboardService $service): Response
    {
        $year = $this->resolveYear($request->query('year'));

        return Inertia::render('ItKpi', [
            'report' => $service->itKpi($year),
        ]);
    }

    private function resolveYear(mixed $raw): int
    {
        $current = CarbonImmutable::now()->year;
        $year = is_numeric($raw) ? (int) $raw : $current;

        // Keep within a sane window and never into the future.
        return max(2000, min($year, $current));
    }
}
