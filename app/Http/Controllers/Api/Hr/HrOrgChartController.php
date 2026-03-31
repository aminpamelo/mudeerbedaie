<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
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
    public function __invoke(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->whereNotIn('status', ['terminated', 'resigned'])
            ->with([
                'position:id,title,level',
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

            $result[] = [
                'id' => $node->id,
                'employee_id' => $node->employee_id,
                'full_name' => $node->full_name,
                'profile_photo_url' => $node->profile_photo_url,
                'position' => $node->position ? [
                    'id' => $node->position->id,
                    'title' => $node->position->title,
                    'level' => $node->position->level,
                ] : null,
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
}
