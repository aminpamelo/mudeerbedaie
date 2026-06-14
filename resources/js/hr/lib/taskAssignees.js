/**
 * Shared helpers for rendering a task's assignees across the HR meeting/task UIs.
 *
 * A task may be co-owned by several employees. Prefer the full `assignees` set,
 * falling back to the single `assignee` for older payloads that only carry the
 * primary owner.
 */

/** @returns {{ id: number, full_name: string }[]} */
export function taskAssignees(task) {
    if (Array.isArray(task.assignees) && task.assignees.length) return task.assignees;
    if (task.assignee) return [task.assignee];
    return [];
}

/** Compact label for a task's assignees, e.g. "Alan Strosin +2". Null when none. */
export function assigneeSummary(task) {
    const list = taskAssignees(task);
    if (!list.length) return null;
    const [first, ...rest] = list;
    return rest.length ? `${first.full_name} +${rest.length}` : first.full_name;
}

/** Comma-joined full names, for a hover tooltip listing every co-owner. */
export function assigneeNames(task) {
    return taskAssignees(task).map((a) => a.full_name).join(', ');
}
