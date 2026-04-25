<?php

namespace App\Services\LiveHost\Reports;

class ReplacementsResult
{
    /**
     * @param  array{total: int, fulfilled: int, expired: int, avgTimeToAssignMinutes: ?float}  $kpis
     * @param  array<int, array{date: string, requested: int, fulfilled: int}>  $trend
     * @param  array<int, array{hostId: int, hostName: string, requestCount: int, reasons: array<string, int>}>  $topRequesters
     * @param  array<int, array{hostId: int, hostName: string, coverCount: int}>  $topCoverers
     */
    public function __construct(
        public readonly array $kpis,
        public readonly array $trend,
        public readonly array $topRequesters,
        public readonly array $topCoverers,
    ) {}
}
