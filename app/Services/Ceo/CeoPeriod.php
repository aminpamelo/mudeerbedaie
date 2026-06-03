<?php

namespace App\Services\Ceo;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The time window every CEO health report is computed against.
 *
 * Mirrors the role of {@see \App\Services\LiveHost\Reports\Filters\ReportFilters}
 * but is deliberately coarse: the CEO overview only offers three presets
 * (today / last 7 days / last 30 days) plus a comparison window so deltas can be
 * shown. "Today" always means the calendar day so operational counters line up
 * with what teams see in their own modules.
 */
final class CeoPeriod
{
    public const KEYS = ['today', '7d', '30d'];

    public function __construct(
        public readonly string $key,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function fromKey(?string $key): self
    {
        $key = in_array($key, self::KEYS, true) ? $key : 'today';
        $now = CarbonImmutable::now();
        $end = $now->endOfDay();

        $start = match ($key) {
            '7d' => $now->subDays(6)->startOfDay(),
            '30d' => $now->subDays(29)->startOfDay(),
            default => $now->startOfDay(),
        };

        return new self($key, $start, $end);
    }

    public static function fromRequest(Request $request): self
    {
        return self::fromKey($request->query('period'));
    }

    /**
     * The window of equal length immediately preceding this one, used to draw
     * "vs previous" deltas on the pulse strip.
     */
    public function priorPeriod(): self
    {
        $days = $this->days();
        $priorTo = $this->from->subDay()->endOfDay();
        $priorFrom = $priorTo->subDays($days - 1)->startOfDay();

        return new self($this->key, $priorFrom, $priorTo);
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    public function label(): string
    {
        return __('ceo.periods.'.$this->key);
    }

    /**
     * Shape consumed by the period switcher on every CEO page.
     *
     * @return array{key: string, label: string, options: array<int, array{key: string, label: string}>}
     */
    public function toPayload(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label(),
            'options' => [
                ['key' => 'today', 'label' => __('ceo.periods.today')],
                ['key' => '7d', 'label' => __('ceo.periods.7d')],
                ['key' => '30d', 'label' => __('ceo.periods.30d')],
            ],
        ];
    }
}
