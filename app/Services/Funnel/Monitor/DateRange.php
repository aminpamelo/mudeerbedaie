<?php

namespace App\Services\Funnel\Monitor;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * A reporting window chosen by preset (?range=month) or explicit dates (?from=&to=),
 * with a like-for-like previous window for the "vs sebelum" deltas.
 */
final class DateRange
{
    public const PRESETS = ['today', 'yesterday', 'week', '7', '30', 'month', 'last_month', 'year'];

    public function __construct(
        public readonly string $preset,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function fromRequest(Request $request, string $default = 'today'): self
    {
        $from = $request->date('from');
        $to = $request->date('to');

        if ($from !== null && $to !== null) {
            [$a, $b] = $from->lte($to) ? [$from, $to] : [$to, $from];

            return new self('custom', CarbonImmutable::instance($a)->startOfDay(), CarbonImmutable::instance($b)->endOfDay());
        }

        $preset = in_array($request->string('range')->value(), self::PRESETS, true)
            ? $request->string('range')->value()
            : $default;

        return self::preset($preset);
    }

    public static function preset(string $preset): self
    {
        $now = CarbonImmutable::now();

        [$from, $to] = match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'week' => [$now->startOfWeek(CarbonImmutable::MONDAY), $now->endOfDay()],
            '7' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '30' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'year' => [$now->startOfYear(), $now->endOfDay()],
            default => [$now->startOfMonth(), $now->endOfDay()],
        };

        return new self(in_array($preset, self::PRESETS, true) ? $preset : 'month', $from, $to);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function previous(): array
    {
        return match ($this->preset) {
            'week' => [$this->from->subWeek(), $this->to->subWeek()],
            'month' => [$this->from->subMonthNoOverflow(), $this->to->subMonthNoOverflow()->min($this->from->subDay()->endOfDay())],
            'last_month' => [$this->from->subMonthNoOverflow()->startOfMonth(), $this->from->subMonthNoOverflow()->endOfMonth()],
            'year' => [$this->from->subYear(), $this->to->subYear()],
            default => (function (): array {
                $to = $this->from->subSecond();

                return [$to->subSeconds((int) $this->from->diffInSeconds($this->to))->startOfDay(), $to];
            })(),
        };
    }

    /** @return array{preset: string, from: string, to: string} */
    public function toArray(): array
    {
        return ['preset' => $this->preset, 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }
}
