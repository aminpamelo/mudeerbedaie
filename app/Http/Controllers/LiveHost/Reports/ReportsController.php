<?php

namespace App\Http\Controllers\LiveHost\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('reports/Index', [
            'reports' => [
                [
                    'key' => 'host-scorecard',
                    'title' => 'Host Scorecard',
                    'description' => 'Per-host hours live, GMV, commission, attendance, no-shows.',
                    'href' => '/livehost/reports/host-scorecard',
                    'available' => true,
                ],
                [
                    'key' => 'gmv',
                    'title' => 'GMV Performance',
                    'description' => 'Daily GMV trend by host, account, and platform.',
                    'href' => '/livehost/reports/gmv',
                    'available' => false,
                ],
                [
                    'key' => 'coverage',
                    'title' => 'Schedule Coverage',
                    'description' => 'Slots filled vs unassigned, weekly trend.',
                    'href' => '/livehost/reports/coverage',
                    'available' => false,
                ],
                [
                    'key' => 'replacements',
                    'title' => 'Replacement Activity',
                    'description' => 'Frequency, top requesters and coverers, fulfillment SLA.',
                    'href' => '/livehost/reports/replacements',
                    'available' => false,
                ],
            ],
        ]);
    }
}
