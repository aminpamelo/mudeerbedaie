<?php

declare(strict_types=1);

namespace App\Enums;

enum DepartmentRole: string
{
    case DEPARTMENT_PIC = 'department_pic';
    case MEMBER = 'member';

    public function label(): string
    {
        return match ($this) {
            self::DEPARTMENT_PIC => 'PIC Department',
            self::MEMBER => 'Member',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DEPARTMENT_PIC => 'Can create, edit, delete tasks and manage department members',
            self::MEMBER => 'Can view tasks in the department',
        };
    }

    public function canManageTasks(): bool
    {
        return $this === self::DEPARTMENT_PIC;
    }
}
