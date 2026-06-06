<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Services\Ceo\CeoDashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonthlyReportController extends Controller
{
    public function index(Request $request, CeoDashboardService $service): Response
    {
        $department = $request->query('department', 'ecommerce');
        $year = $this->resolveYear($request->query('year'));

        $report = $service->monthlyReport((string) $department, $year);

        abort_if($report === null, 404);

        return Inertia::render('MonthlyReport', [
            'report' => $report,
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
