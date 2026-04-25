<?php

namespace App\Services\LiveHost\Reports;

class HostScorecardResult
{
    /**
     * @param  array{totalHours: float, totalGmv: float, totalCommission: float, attendanceRate: float}  $kpis
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array{date: string, ended: int, missed: int}>  $trend
     */
    public function __construct(
        public readonly array $kpis,
        public readonly array $rows,
        public readonly array $trend,
    ) {}
}
