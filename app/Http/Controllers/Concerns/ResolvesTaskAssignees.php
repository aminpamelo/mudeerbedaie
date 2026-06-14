<?php

namespace App\Http\Controllers\Concerns;

/**
 * Shared assignee resolution for the task controllers (CEO board + HR dashboard).
 *
 * A task may be co-owned by several employees: the multi-select sends
 * `assignee_ids`, while older/single-assignee paths send `assigned_to`. The first
 * resolved id becomes the canonical `assigned_to` (so single-assignee features
 * keep working) and the full ordered, de-duplicated set is synced to the
 * task_assignee pivot.
 */
trait ResolvesTaskAssignees
{
    /**
     * Resolve the ordered, de-duplicated set of assignee ids from the validated
     * payload (multi-select `assignee_ids` or the single `assigned_to`). Strips
     * `assignee_ids` from the payload so it isn't mass-assigned.
     *
     * @param  array<string, mixed>  $data
     * @return ($required is true ? array<int, int> : array<int, int>|null)
     */
    protected function resolveAssigneeIds(array &$data, bool $required = true): ?array
    {
        $ids = [];

        if (! empty($data['assignee_ids'])) {
            $ids = $data['assignee_ids'];
        } elseif (! empty($data['assigned_to'])) {
            $ids = [$data['assigned_to']];
        }

        unset($data['assignee_ids']);

        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return $required ? [] : null;
        }

        return $ids;
    }
}
