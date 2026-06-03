<?php

namespace App\Services\Ceo\Reports;

use App\Services\Ceo\CeoPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turns a sparse "day => value" aggregate into a dense, zero-filled series
 * spanning every day of the period — so sparklines render a continuous line
 * regardless of which days actually had activity.
 */
class TrendFiller
{
    /**
     * @param  Collection<string, int|float>|array<string, int|float>  $valuesByDay  keyed by 'Y-m-d'
     * @return array<int, int|float>
     */
    public static function daily(CeoPeriod $period, Collection|array $valuesByDay): array
    {
        $values = $valuesByDay instanceof Collection ? $valuesByDay->all() : $valuesByDay;

        $series = [];
        $cursor = $period->from->startOfDay();
        $end = $period->to->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $series[] = $values[$key] ?? 0;
            $cursor = $cursor->addDay();

            if (count($series) > 366) {
                break;
            }
        }

        return $series;
    }

    /**
     * Convenience for callers that already hold CarbonImmutable bounds but no
     * CeoPeriod instance.
     *
     * @param  array<string, int|float>  $valuesByDay
     * @return array<int, int|float>
     */
    public static function between(CarbonImmutable $from, CarbonImmutable $to, array $valuesByDay): array
    {
        return self::daily(new CeoPeriod('custom', $from, $to), $valuesByDay);
    }
}
