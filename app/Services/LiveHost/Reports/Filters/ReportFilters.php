<?php

namespace App\Services\LiveHost\Reports\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReportFilters
{
    /**
     * @param  array<int>  $hostIds
     * @param  array<int>  $platformAccountIds
     */
    public function __construct(
        public readonly CarbonImmutable $dateFrom,
        public readonly CarbonImmutable $dateTo,
        public readonly array $hostIds = [],
        public readonly array $platformAccountIds = [],
    ) {
        if ($this->dateFrom->greaterThan($this->dateTo)) {
            throw new InvalidArgumentException('dateFrom must be on or before dateTo.');
        }
    }

    public static function fromRequest(Request $request): self
    {
        $now = CarbonImmutable::now();
        $defaultFrom = $now->startOfMonth();
        $defaultTo = $now->startOfDay();

        $from = self::parseOrFallback($request->query('dateFrom'), $defaultFrom);
        $to = self::parseOrFallback($request->query('dateTo'), $defaultTo);

        $hostIds = collect((array) $request->query('hostIds', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->values()
            ->all();

        $platformAccountIds = collect((array) $request->query('platformAccountIds', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->values()
            ->all();

        return new self($from, $to, $hostIds, $platformAccountIds);
    }

    private static function parseOrFallback(mixed $raw, CarbonImmutable $fallback): CarbonImmutable
    {
        if (! $raw) {
            return $fallback;
        }

        try {
            return CarbonImmutable::parse((string) $raw)->startOfDay();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public function priorPeriod(): self
    {
        $days = (int) $this->dateFrom->diffInDays($this->dateTo) + 1;
        $priorTo = $this->dateFrom->subDay();
        $priorFrom = $priorTo->subDays($days - 1);

        return new self($priorFrom, $priorTo, $this->hostIds, $this->platformAccountIds);
    }
}
