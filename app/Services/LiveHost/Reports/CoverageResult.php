<?php

namespace App\Services\LiveHost\Reports;

class CoverageResult
{
    /**
     * @param  array{percentFilled: float, unassignedCount: int, replacedCount: int, noShowRate: float, totalSlots: int}  $kpis
     * @param  array<int, array{weekStart: string, assigned: int, unassigned: int, replaced: int, missed: int}>  $weeklyTrend
     * @param  array<int, array{accountId: int, name: string, totalSlots: int, assigned: int, unassigned: int, replaced: int, missed: int, coverageRate: float}>  $accountRows
     */
    public function __construct(
        public readonly array $kpis,
        public readonly array $weeklyTrend,
        public readonly array $accountRows,
    ) {}
}
