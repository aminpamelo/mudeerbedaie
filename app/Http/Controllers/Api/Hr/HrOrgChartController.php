<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class HrOrgChartController extends Controller
{
    /**
     * Return the full organizational chart data.
     *
     * Structure: departments (hierarchical) with their employees,
     * positions, profile photos, and department heads.
     */
    public function __invoke(): JsonResponse
    {
        $departments = Department::with([
            'headEmployee:id,full_name,profile_photo,position_id,department_id,employee_id,status',
            'headEmployee.position:id,title,level',
            'employees' => function ($q) {
                $q->where('status', '!=', 'terminated')
                    ->where('status', '!=', 'resigned')
                    ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
                    ->orderBy('positions.level', 'asc')
                    ->orderBy('employees.full_name', 'asc')
                    ->select('employees.id', 'employees.full_name', 'employees.profile_photo', 'employees.position_id', 'employees.department_id', 'employees.employee_id', 'employees.status');
            },
            'employees.position:id,title,level',
            'children' => function ($q) {
                $q->withCount('employees')->orderBy('name');
            },
            'children.headEmployee:id,full_name,profile_photo,position_id,department_id,employee_id,status',
            'children.headEmployee.position:id,title,level',
            'children.employees' => function ($q) {
                $q->where('status', '!=', 'terminated')
                    ->where('status', '!=', 'resigned')
                    ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
                    ->orderBy('positions.level', 'asc')
                    ->orderBy('employees.full_name', 'asc')
                    ->select('employees.id', 'employees.full_name', 'employees.profile_photo', 'employees.position_id', 'employees.department_id', 'employees.employee_id', 'employees.status');
            },
            'children.employees.position:id,title,level',
            'children.children' => function ($q) {
                $q->withCount('employees')->orderBy('name');
            },
            'children.children.headEmployee:id,full_name,profile_photo,position_id,department_id,employee_id,status',
            'children.children.headEmployee.position:id,title,level',
            'children.children.employees' => function ($q) {
                $q->where('status', '!=', 'terminated')
                    ->where('status', '!=', 'resigned')
                    ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
                    ->orderBy('positions.level', 'asc')
                    ->orderBy('employees.full_name', 'asc')
                    ->select('employees.id', 'employees.full_name', 'employees.profile_photo', 'employees.position_id', 'employees.department_id', 'employees.employee_id', 'employees.status');
            },
            'children.children.employees.position:id,title,level',
        ])
            ->whereNull('parent_id')
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        $totalEmployees = Employee::whereNotIn('status', ['terminated', 'resigned'])->count();
        $totalDepartments = Department::count();

        return response()->json([
            'data' => $departments,
            'meta' => [
                'total_employees' => $totalEmployees,
                'total_departments' => $totalDepartments,
            ],
        ]);
    }
}
