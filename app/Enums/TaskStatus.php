<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::TODO => 'To Do',
            self::IN_PROGRESS => 'In Progress',
            self::REVIEW => 'Review',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TODO => 'zinc',
            self::IN_PROGRESS => 'blue',
            self::REVIEW => 'amber',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TODO => 'clipboard-document-list',
            self::IN_PROGRESS => 'play-circle',
            self::REVIEW => 'eye',
            self::COMPLETED => 'check-circle',
            self::CANCELLED => 'x-circle',
        };
    }

    /**
     * Get statuses that are visible on Kanban board
     */
    public static function kanbanStatuses(): array
    {
        return [
            self::TODO,
            self::IN_PROGRESS,
            self::REVIEW,
            self::COMPLETED,
        ];
    }
}
