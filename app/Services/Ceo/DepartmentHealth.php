<?php

namespace App\Services\Ceo;

/**
 * Immutable result of one department's health check, shaped for the CEO
 * dashboard cards. Every health report returns one of these.
 *
 * `status` is the traffic-light signal ('green' | 'amber' | 'red'). `metrics`
 * are the lead numbers shown on the card; `trend` is a small numeric series for
 * the sparkline; `alerts` are the items that bubble up into the cross-company
 * "Needs attention" feed; `extra` carries raw scalars the orchestrator uses to
 * compose the top pulse strip without re-querying.
 *
 * @phpstan-type Metric array{label: string, value: string, hint?: string, tone?: string}
 * @phpstan-type Alert array{severity: string, message: string, href?: string}
 */
final class DepartmentHealth
{
    public const GREEN = 'green';

    public const AMBER = 'amber';

    public const RED = 'red';

    /**
     * @param  array<int, Metric>  $metrics
     * @param  array<int, int|float>  $trend
     * @param  array<int, Alert>  $alerts
     * @param  array<string, int|float>  $extra
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $accent,
        public readonly string $status,
        public readonly string $href,
        public readonly array $metrics,
        public readonly array $trend = [],
        public readonly array $alerts = [],
        public readonly array $extra = [],
    ) {}

    /**
     * Pick the worst of several traffic-light signals. Used by reports that
     * derive an overall status from multiple independent checks.
     *
     * @param  array<int, string>  $statuses
     */
    public static function worst(array $statuses): string
    {
        if (in_array(self::RED, $statuses, true)) {
            return self::RED;
        }

        if (in_array(self::AMBER, $statuses, true)) {
            return self::AMBER;
        }

        return self::GREEN;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'accent' => $this->accent,
            'status' => $this->status,
            'href' => $this->href,
            'metrics' => $this->metrics,
            'trend' => $this->trend,
            'alerts' => $this->alerts,
        ];
    }
}
