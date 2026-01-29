<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskType: string
{
    case KPI = 'kpi';
    case ADHOC = 'adhoc';

    public function label(): string
    {
        return match ($this) {
            self::KPI => 'KPI',
            self::ADHOC => 'Adhoc',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::KPI => 'violet',
            self::ADHOC => 'sky',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::KPI => 'Key Performance Indicator task linked to targets',
            self::ADHOC => 'One-time task not linked to KPI',
        };
    }
}
