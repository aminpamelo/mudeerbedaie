<?php

namespace App;

enum AcademicStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case WITHDRAWN = 'withdrawn';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
            self::WITHDRAWN => 'Withdrawn',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'badge-green',
            self::COMPLETED => 'badge-emerald',
            self::WITHDRAWN => 'badge-red',
            self::SUSPENDED => 'badge-yellow',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isWithdrawn(): bool
    {
        return $this === self::WITHDRAWN;
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }

    public static function getActiveStatuses(): array
    {
        return [self::ACTIVE];
    }
}
