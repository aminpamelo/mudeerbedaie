<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrOrgChartController extends Controller
{
    /**
     * Return the employee hierarchy tree for the org chart.
     *
     * Builds a top-down tree: top-level employees (no reports_to)
     * with their direct reports nested recursively.
     */
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->whereNotIn('status', ['terminated', 'resigned'])
            ->with([
                'position:id,title,level',
                'positions:id,title,level',
                'department:id,name,code',
            ])
            ->select('id', 'full_name', 'profile_photo', 'position_id', 'department_id', 'employee_id', 'status', 'reports_to')
            ->orderByRaw('CASE WHEN reports_to IS NULL THEN 0 ELSE 1 END')
            ->orderBy('full_name')
            ->get();

        // Build adjacency map
        $childrenMap = [];
        $roots = [];

        foreach ($employees as $emp) {
            $childrenMap[$emp->id] = [];
        }

        foreach ($employees as $emp) {
            if ($emp->reports_to && isset($childrenMap[$emp->reports_to])) {
                $childrenMap[$emp->reports_to][] = $emp;
            } else {
                $roots[] = $emp;
            }
        }

        // Recursively build tree
        $tree = $this->buildTree($roots, $childrenMap);

        $totalEmployees = $employees->count();
        $linkedCount = $employees->whereNotNull('reports_to')->count();

        return response()->json([
            'data' => $tree,
            'meta' => [
                'total_employees' => $totalEmployees,
                'linked_employees' => $linkedCount,
                'unlinked_employees' => $totalEmployees - $linkedCount,
            ],
        ]);
    }

    /**
     * Recursively build the tree structure.
     *
     * @param  iterable<Employee>  $nodes
     * @param  array<int, array<Employee>>  $childrenMap
     * @return array<array>
     */
    private function buildTree(iterable $nodes, array $childrenMap): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $children = $childrenMap[$node->id] ?? [];

            // Sort children by position level (ascending), then by name
            usort($children, function ($a, $b) {
                $levelA = $a->position->level ?? 999;
                $levelB = $b->position->level ?? 999;
                if ($levelA !== $levelB) {
                    return $levelA <=> $levelB;
                }

                return strcmp($a->full_name, $b->full_name);
            });

            // Merge primary position with many-to-many positions, deduplicated
            $allPositions = $node->positions->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'level' => $p->level,
                'is_primary' => (bool) $p->pivot->is_primary,
            ])->sortByDesc('is_primary')->values()->all();

            // If no many-to-many positions, fall back to position_id
            if (empty($allPositions) && $node->position) {
                $allPositions = [[
                    'id' => $node->position->id,
                    'title' => $node->position->title,
                    'level' => $node->position->level,
                    'is_primary' => true,
                ]];
            }

            $primaryPosition = $allPositions[0] ?? null;

            $result[] = [
                'id' => $node->id,
                'employee_id' => $node->employee_id,
                'full_name' => $node->full_name,
                'profile_photo_url' => $node->profile_photo_url,
                'position' => $primaryPosition,
                'positions' => $allPositions,
                'department' => $node->department ? [
                    'id' => $node->department->id,
                    'name' => $node->department->name,
                    'code' => $node->department->code,
                ] : null,
                'status' => $node->status,
                'reports_to' => $node->reports_to,
                'children' => $this->buildTree($children, $childrenMap),
            ];
        }

        return $result;
    }

    /**
     * Assign or remove the "Reports To" manager for an employee.
     */
    public function assignManager(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'reports_to' => ['nullable', 'exists:employees,id'],
        ]);

        // Prevent self-referencing
        if (isset($validated['reports_to']) && (int) $validated['reports_to'] === $employee->id) {
            return response()->json([
                'message' => 'An employee cannot report to themselves.',
            ], 422);
        }

        // Prevent circular references
        if (isset($validated['reports_to']) && $this->wouldCreateCycle($employee->id, (int) $validated['reports_to'])) {
            return response()->json([
                'message' => 'This assignment would create a circular reporting chain.',
            ], 422);
        }

        $employee->update([
            'reports_to' => $validated['reports_to'] ?? null,
        ]);

        $employee->load(['position:id,title,level', 'department:id,name,code', 'manager:id,full_name']);

        return response()->json([
            'message' => 'Manager assigned successfully.',
            'data' => $employee,
        ]);
    }

    /**
     * Return the department hierarchy tree with employees nested under each department.
     */
    public function departmentTree(Request $request): JsonResponse
    {
        $departments = Department::query()
            ->with([
                'headEmployee:id,full_name,profile_photo,position_id',
                'headEmployee.position:id,title,level',
            ])
            ->withCount([
                'employees' => function ($query) {
                    $query->whereNotIn('status', ['terminated', 'resigned']);
                },
            ])
            ->orderBy('name')
            ->get();

        // Load active employees for each department
        $employeesByDept = Employee::query()
            ->whereNotIn('status', ['terminated', 'resigned'])
            ->with(['position:id,title,level', 'positions:id,title,level'])
            ->select('id', 'full_name', 'profile_photo', 'position_id', 'department_id', 'employee_id')
            ->orderBy('full_name')
            ->get()
            ->groupBy('department_id');

        // Build adjacency map for departments
        $childrenMap = [];
        $roots = [];

        foreach ($departments as $dept) {
            $childrenMap[$dept->id] = [];
        }

        foreach ($departments as $dept) {
            if ($dept->parent_id && isset($childrenMap[$dept->parent_id])) {
                $childrenMap[$dept->parent_id][] = $dept;
            } else {
                $roots[] = $dept;
            }
        }

        $tree = $this->buildDepartmentTree($roots, $childrenMap, $employeesByDept);

        $totalDepartments = $departments->count();
        $withParent = $departments->whereNotNull('parent_id')->count();

        return response()->json([
            'data' => $tree,
            'meta' => [
                'total_departments' => $totalDepartments,
                'in_hierarchy' => $withParent,
                'root_level' => $totalDepartments - $withParent,
            ],
        ]);
    }

    /**
     * Assign or remove the parent department.
     */
    public function assignParent(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:departments,id'],
        ]);

        // Prevent self-referencing
        if (isset($validated['parent_id']) && (int) $validated['parent_id'] === $department->id) {
            return response()->json([
                'message' => 'A department cannot be its own parent.',
            ], 422);
        }

        // Prevent circular references
        if (isset($validated['parent_id']) && $this->wouldCreateDeptCycle($department->id, (int) $validated['parent_id'])) {
            return response()->json([
                'message' => 'This assignment would create a circular department chain.',
            ], 422);
        }

        $department->update([
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        $department->load('parent:id,name,code');

        return response()->json([
            'message' => 'Parent department assigned successfully.',
            'data' => $department,
        ]);
    }

    /**
     * Recursively build the department tree structure.
     *
     * @param  iterable<Department>  $nodes
     * @param  array<int, array<Department>>  $childrenMap
     * @param  \Illuminate\Support\Collection  $employeesByDept
     * @return array<array>
     */
    private function buildDepartmentTree(iterable $nodes, array $childrenMap, $employeesByDept): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $children = $childrenMap[$node->id] ?? [];

            usort($children, fn ($a, $b) => strcmp($a->name, $b->name));

            $deptEmployees = $employeesByDept->get($node->id, collect());

            // Put head employee first if set
            $headId = $node->head_employee_id;
            $sortedEmployees = $deptEmployees->sortBy(function ($emp) use ($headId) {
                return $emp->id === $headId ? 0 : 1;
            })->values();

            $result[] = [
                'id' => $node->id,
                'name' => $node->name,
                'code' => $node->code,
                'description' => $node->description,
                'parent_id' => $node->parent_id,
                'head_employee_id' => $node->head_employee_id,
                'head_employee' => $node->headEmployee ? [
                    'id' => $node->headEmployee->id,
                    'full_name' => $node->headEmployee->full_name,
                    'profile_photo_url' => $node->headEmployee->profile_photo_url,
                    'position' => $node->headEmployee->position ? [
                        'title' => $node->headEmployee->position->title,
                    ] : null,
                ] : null,
                'employee_count' => $node->employees_count,
                'employees' => $sortedEmployees->map(function ($emp) use ($headId) {
                    $allPositions = $emp->positions->map(fn ($p) => [
                        'id' => $p->id,
                        'title' => $p->title,
                        'level' => $p->level,
                        'is_primary' => (bool) $p->pivot->is_primary,
                    ])->sortByDesc('is_primary')->values()->all();

                    if (empty($allPositions) && $emp->position) {
                        $allPositions = [[
                            'id' => $emp->position->id,
                            'title' => $emp->position->title,
                            'level' => $emp->position->level,
                            'is_primary' => true,
                        ]];
                    }

                    return [
                        'id' => $emp->id,
                        'full_name' => $emp->full_name,
                        'profile_photo_url' => $emp->profile_photo_url,
                        'employee_id' => $emp->employee_id,
                        'position' => $allPositions[0] ?? null,
                        'positions' => $allPositions,
                        'is_head' => $emp->id === $headId,
                    ];
                })->values()->all(),
                'children' => $this->buildDepartmentTree($children, $childrenMap, $employeesByDept),
            ];
        }

        return $result;
    }

    /**
     * Check if assigning managerId to employeeId would create a cycle.
     */
    private function wouldCreateCycle(int $employeeId, int $managerId): bool
    {
        $visited = [$employeeId];
        $current = $managerId;

        while ($current) {
            if (in_array($current, $visited)) {
                return true;
            }
            $visited[] = $current;
            $current = Employee::where('id', $current)->value('reports_to');
        }

        return false;
    }

    /**
     * Check if assigning parentId to departmentId would create a cycle.
     */
    private function wouldCreateDeptCycle(int $departmentId, int $parentId): bool
    {
        $visited = [$departmentId];
        $current = $parentId;

        while ($current) {
            if (in_array($current, $visited)) {
                return true;
            }
            $visited[] = $current;
            $current = Department::where('id', $current)->value('parent_id');
        }

        return false;
    }
}
