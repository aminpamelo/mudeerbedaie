<?php

namespace App\Services\Ceo;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The time window every CEO report is computed against.
 *
 * Presets: today / last 7 days / last 30 days are rolling windows. `month` and
 * `quarter` are calendar periods that can be stepped through via a `ref` anchor
 * (month "YYYY-MM", quarter "YYYY-Q"), so the CEO can review a specific month or
 * a calendar quarter (Q1 = Jan–Mar, etc.) and compare it against the previous
 * one. Refs never advance past the current month/quarter.
 */
final class CeoPeriod
{
    public const KEYS = ['today', '7d', '30d', 'month', 'quarter'];

    public const STEPPED = ['month', 'quarter'];

    public function __construct(
        public readonly string $key,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?string $ref = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromKey($request->query('period'), $request->query('ref'));
    }

    public static function fromKey(?string $key, ?string $ref = null): self
    {
        $key = in_array($key, self::KEYS, true) ? $key : 'today';
        $now = CarbonImmutable::now();

        return match ($key) {
            '7d' => new self($key, $now->subDays(6)->startOfDay(), $now->endOfDay()),
            '30d' => new self($key, $now->subDays(29)->startOfDay(), $now->endOfDay()),
            'month' => self::month($ref, $now),
            'quarter' => self::quarter($ref, $now),
            default => new self('today', $now->startOfDay(), $now->endOfDay()),
        };
    }

    private static function month(?string $ref, CarbonImmutable $now): self
    {
        $anchor = self::parseMonth($ref) ?? $now;
        // Never look past the current month.
        if ($anchor->greaterThan($now)) {
            $anchor = $now;
        }
        $from = $anchor->startOfMonth();

        return new self('month', $from, $from->endOfMonth(), $from->format('Y-m'));
    }

    private static function quarter(?string $ref, CarbonImmutable $now): self
    {
        $anchor = self::parseQuarter($ref) ?? $now;
        $from = self::quarterStart($anchor);
        $currentQuarterStart = self::quarterStart($now);
        if ($from->greaterThan($currentQuarterStart)) {
            $from = $currentQuarterStart;
        }

        $to = $from->addMonths(3)->subDay()->endOfDay();

        return new self('quarter', $from, $to, self::quarterRef($from));
    }

    private static function parseMonth(?string $ref): ?CarbonImmutable
    {
        if (! $ref || ! preg_match('/^\d{4}-\d{2}$/', $ref)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $ref.'-01')->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function parseQuarter(?string $ref): ?CarbonImmutable
    {
        if (! $ref || ! preg_match('/^(\d{4})-([1-4])$/', $ref, $m)) {
            return null;
        }

        $month = ((int) $m[2] - 1) * 3 + 1;

        try {
            return CarbonImmutable::create((int) $m[1], $month, 1)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function quarterStart(CarbonImmutable $date): CarbonImmutable
    {
        $month = (intdiv($date->month - 1, 3) * 3) + 1;

        return $date->setDate($date->year, $month, 1)->startOfDay();
    }

    private static function quarterRef(CarbonImmutable $quarterStart): string
    {
        return $quarterStart->year.'-'.(intdiv($quarterStart->month - 1, 3) + 1);
    }

    /**
     * Equal-length window immediately before this one, for "vs previous" deltas.
     * Calendar periods step back a full month / quarter; rolling windows step
     * back by their own length.
     */
    public function priorPeriod(): self
    {
        if ($this->key === 'month') {
            $from = $this->from->subMonthNoOverflow()->startOfMonth();

            return new self('month', $from, $from->endOfMonth(), $from->format('Y-m'));
        }

        if ($this->key === 'quarter') {
            $from = $this->from->subMonthsNoOverflow(3)->startOfDay();

            return new self('quarter', $from, $from->addMonths(3)->subDay()->endOfDay(), self::quarterRef($from));
        }

        $days = $this->days();
        $priorTo = $this->from->subDay()->endOfDay();
        $priorFrom = $priorTo->subDays($days - 1)->startOfDay();

        return new self($this->key, $priorFrom, $priorTo);
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * Cache namespace — includes the ref so different months/quarters don't
     * share cached payloads.
     */
    public function cacheKey(): string
    {
        return $this->ref ? "{$this->key}:{$this->ref}" : $this->key;
    }

    public function label(): string
    {
        return match ($this->key) {
            'month' => ucfirst($this->from->locale(app()->getLocale())->isoFormat('MMM YYYY')),
            'quarter' => __('ceo.periods.quarter_label', [
                'n' => intdiv($this->from->month - 1, 3) + 1,
                'year' => $this->from->year,
            ]),
            default => __('ceo.periods.'.$this->key),
        };
    }

    private function prevRef(): ?string
    {
        return match ($this->key) {
            'month' => $this->from->subMonthNoOverflow()->format('Y-m'),
            'quarter' => self::quarterRef($this->from->subMonthsNoOverflow(3)),
            default => null,
        };
    }

    private function nextRef(): ?string
    {
        $now = CarbonImmutable::now();

        if ($this->key === 'month') {
            $next = $this->from->addMonthNoOverflow()->startOfMonth();

            return $next->greaterThan($now->startOfMonth()) ? null : $next->format('Y-m');
        }

        if ($this->key === 'quarter') {
            $next = $this->from->addMonthsNoOverflow(3)->startOfDay();

            return $next->greaterThan(self::quarterStart($now)) ? null : self::quarterRef($next);
        }

        return null;
    }

    /**
     * Shape consumed by the period switcher on every CEO page.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label(),
            'ref' => $this->ref,
            'stepped' => in_array($this->key, self::STEPPED, true),
            'prevRef' => $this->prevRef(),
            'nextRef' => $this->nextRef(),
            'options' => [
                ['key' => 'today', 'label' => __('ceo.periods.today')],
                ['key' => '7d', 'label' => __('ceo.periods.7d')],
                ['key' => '30d', 'label' => __('ceo.periods.30d')],
                ['key' => 'month', 'label' => __('ceo.periods.month')],
                ['key' => 'quarter', 'label' => __('ceo.periods.quarter')],
            ],
        ];
    }
}
