<?php

namespace App\Services\LiveHost\Reports;

class GmvResult
{
    /**
     * @param  array{totalGmv: float, gmvPerSession: float, topAccountId: ?int, topAccountGmv: float, topHostId: ?int, topHostGmv: float}  $kpis
     * @param  array<int, array{date: string, series: array<int, float>}>  $trendByAccount
     * @param  array<int, array{accountId: int, name: string, totalGmv: float}>  $accountSeries
     * @param  array<int, array{hostId: int, hostName: string, sessions: int, gmv: float, avgGmvPerSession: float}>  $hostRows
     * @param  array<int, array{sessionId: int, date: string, hostName: ?string, accountName: ?string, gmv: float}>  $topSessions
     */
    public function __construct(
        public readonly array $kpis,
        public readonly array $trendByAccount,
        public readonly array $accountSeries,
        public readonly array $hostRows,
        public readonly array $topSessions,
    ) {}
}
