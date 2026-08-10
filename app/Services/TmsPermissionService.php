<?php

namespace App\Services;

use App\Models\User;

class TmsPermissionService
{
    public function resolve(User $user): array
    {
        $role = $this->resolveRole($user);

        return [
            'tms_role' => $role,
            'can_create_project' => in_array($role, ['admin', 'manager']),
            'can_manage_all_tasks' => $role === 'admin',
            'can_view_kpi' => in_array($role, ['admin', 'manager']),
            'can_view_reports' => in_array($role, ['admin', 'manager']),
            'can_approve_tasks' => in_array($role, ['admin', 'manager', 'leader']),
            'can_manage_settings' => $role === 'admin',
        ];
    }

    private function resolveRole(User $user): string
    {
        if (in_array($user->role, ['admin', 'ceo'])) {
            return 'admin';
        }

        $employee = $user->employee;
        if ($employee) {
            $level = $employee->position?->level;
            if ($level && in_array(strtolower($level), ['head', 'manager', 'director', 'lead'])) {
                return 'manager';
            }
        }

        return 'staff';
    }
}
