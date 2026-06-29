import { useEffect, useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Users,
    UserPlus,
    Search,
    Trash2,
    ChevronDown,
    ChevronRight,
    Clock,
    Calendar,
    X,
} from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from './ui/dialog';
import { Tabs, TabsList, TabsTrigger, TabsContent } from './ui/tabs';
import { Avatar, AvatarImage, AvatarFallback } from './ui/avatar';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { Checkbox } from './ui/checkbox';
import { EmptyState } from './ui/empty-state';
import { useToast } from './Toast';
import { cn } from '../lib/utils';
import {
    fetchScheduleEmployees,
    fetchEmployees,
    fetchEmployeeSchedules,
    assignEmployeeSchedule,
    deleteEmployeeSchedule,
} from '../lib/api';

function getInitials(name) {
    if (!name) {
        return '?';
    }
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function formatDate(value) {
    if (!value) {
        return '-';
    }
    return new Date(value).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function todayIso() {
    const now = new Date();
    const offset = now.getTimezoneOffset();
    return new Date(now.getTime() - offset * 60000).toISOString().slice(0, 10);
}

function isAssignmentActive(assignment) {
    if (!assignment) {
        return false;
    }
    const now = new Date();
    // Mirror the server's active() scope: started on/before today, not yet ended.
    if (assignment.effective_from && new Date(assignment.effective_from) > now) {
        return false;
    }
    return !assignment.effective_to || new Date(assignment.effective_to) >= now;
}

function EmployeeIdentity({ employee }) {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <Avatar className="h-9 w-9">
                {employee?.profile_photo_url && (
                    <AvatarImage src={employee.profile_photo_url} alt={employee.full_name || 'Employee'} />
                )}
                <AvatarFallback className="bg-indigo-50 text-xs font-semibold text-indigo-600">
                    {getInitials(employee?.full_name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                <p className="truncate text-sm font-medium text-slate-900">{employee?.full_name || '-'}</p>
                <p className="truncate text-xs text-slate-500">
                    {employee?.employee_id}
                    {employee?.department?.name ? ` · ${employee.department.name}` : ''}
                </p>
            </div>
        </div>
    );
}

export default function ScheduleEmployeesDialog({ schedule, open, onOpenChange }) {
    const queryClient = useQueryClient();
    const { toast } = useToast();
    const scheduleId = schedule?.id;

    const [tab, setTab] = useState('assigned');
    const [assignedSearch, setAssignedSearch] = useState('');
    const [pickerSearch, setPickerSearch] = useState('');
    const [selectedIds, setSelectedIds] = useState([]);
    const [effectiveFrom, setEffectiveFrom] = useState(todayIso());
    const [collapsedDepts, setCollapsedDepts] = useState({});
    const [confirmRemoveId, setConfirmRemoveId] = useState(null);

    // Reset transient state whenever the dialog opens for a (possibly different) schedule.
    useEffect(() => {
        if (open) {
            setTab('assigned');
            setAssignedSearch('');
            setPickerSearch('');
            setSelectedIds([]);
            setEffectiveFrom(todayIso());
            setCollapsedDepts({});
            setConfirmRemoveId(null);
        }
    }, [open, scheduleId]);

    const assignedQuery = useQuery({
        queryKey: ['hr', 'attendance', 'schedule-employees', scheduleId],
        queryFn: () => fetchScheduleEmployees(scheduleId),
        enabled: open && !!scheduleId,
    });

    // Distinct key + params so this picker never collides with other pages that
    // cache employees under different filters. Fetch everyone and exclude only
    // terminated/resigned below, matching the badge/list scope (incl. probation).
    const employeesQuery = useQuery({
        queryKey: ['hr', 'employees', 'schedule-picker'],
        queryFn: () => fetchEmployees({ per_page: 500 }),
        enabled: open,
    });

    const employeeSchedulesQuery = useQuery({
        queryKey: ['hr', 'attendance', 'employee-schedules'],
        queryFn: () => fetchEmployeeSchedules({ per_page: 500 }),
        enabled: open,
    });

    const assignments = assignedQuery.data?.data || [];
    const employees = employeesQuery.data?.data || [];
    const allAssignments = employeeSchedulesQuery.data?.data || [];

    function invalidateAll() {
        queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'schedules'] });
        queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'schedule-employees', scheduleId] });
        queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'employee-schedules'] });
    }

    const assignMutation = useMutation({
        mutationFn: assignEmployeeSchedule,
        onSuccess: (_data, variables) => {
            invalidateAll();
            const count = variables?.employee_ids?.length || 0;
            toast.success(
                `${count} employee${count !== 1 ? 's' : ''} assigned`,
                `Now on ${schedule?.name}.`,
            );
            setSelectedIds([]);
            setPickerSearch('');
            setTab('assigned');
        },
        onError: (error) => {
            toast.error('Assignment failed', error?.response?.data?.message || 'Please try again.');
        },
    });

    const removeMutation = useMutation({
        mutationFn: deleteEmployeeSchedule,
        onSuccess: () => {
            invalidateAll();
            setConfirmRemoveId(null);
            toast.success('Employee removed', `Removed from ${schedule?.name}.`);
        },
        onError: (error) => {
            setConfirmRemoveId(null);
            toast.error('Could not remove', error?.response?.data?.message || 'Please try again.');
        },
    });

    // Employees already on this schedule are excluded from the "Add" picker.
    const assignedEmployeeIds = useMemo(
        () => new Set(assignments.map((a) => a.employee_id ?? a.employee?.id)),
        [assignments],
    );

    // Map of employee id -> the work schedule they are currently active on (for "moving from" hints).
    const currentScheduleByEmployee = useMemo(() => {
        const map = {};
        allAssignments.forEach((a) => {
            const empId = a.employee_id ?? a.employee?.id;
            if (empId && isAssignmentActive(a)) {
                map[empId] = a.work_schedule;
            }
        });
        return map;
    }, [allAssignments]);

    const filteredAssignments = useMemo(() => {
        const q = assignedSearch.trim().toLowerCase();
        if (!q) {
            return assignments;
        }
        return assignments.filter((a) => {
            const emp = a.employee;
            return (
                emp?.full_name?.toLowerCase().includes(q) ||
                emp?.employee_id?.toLowerCase().includes(q) ||
                emp?.department?.name?.toLowerCase().includes(q)
            );
        });
    }, [assignments, assignedSearch]);

    const availableEmployees = useMemo(
        () => employees.filter(
            (emp) => !assignedEmployeeIds.has(emp.id)
                && !['terminated', 'resigned'].includes(emp.status),
        ),
        [employees, assignedEmployeeIds],
    );

    const filteredPickerEmployees = useMemo(() => {
        const q = pickerSearch.trim().toLowerCase();
        if (!q) {
            return availableEmployees;
        }
        return availableEmployees.filter(
            (emp) =>
                emp.full_name?.toLowerCase().includes(q) ||
                emp.employee_id?.toLowerCase().includes(q) ||
                emp.department?.name?.toLowerCase().includes(q),
        );
    }, [availableEmployees, pickerSearch]);

    const employeesByDept = useMemo(() => {
        return filteredPickerEmployees.reduce((acc, emp) => {
            const dept = emp.department?.name || 'No Department';
            (acc[dept] = acc[dept] || []).push(emp);
            return acc;
        }, {});
    }, [filteredPickerEmployees]);

    const deptNames = useMemo(() => Object.keys(employeesByDept).sort(), [employeesByDept]);

    function toggleEmployee(id) {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    }

    function deptSelectionState(dept) {
        const ids = (employeesByDept[dept] || []).map((e) => e.id);
        const selected = ids.filter((id) => selectedIds.includes(id)).length;
        if (selected === 0) {
            return 'none';
        }
        return selected === ids.length ? 'all' : 'partial';
    }

    function toggleDept(dept) {
        const ids = (employeesByDept[dept] || []).map((e) => e.id);
        const state = deptSelectionState(dept);
        setSelectedIds((prev) =>
            state === 'all'
                ? prev.filter((id) => !ids.includes(id))
                : [...new Set([...prev, ...ids])],
        );
    }

    function toggleAll() {
        const ids = filteredPickerEmployees.map((e) => e.id);
        const allSelected = ids.length > 0 && ids.every((id) => selectedIds.includes(id));
        setSelectedIds((prev) =>
            allSelected ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])],
        );
    }

    function handleAssign() {
        if (!selectedIds.length || !effectiveFrom) {
            return;
        }
        // A future "effective from" would create a row the badge/list don't yet
        // show (active scope requires it to have started) — block it outright.
        if (effectiveFrom > todayIso()) {
            toast.error('Pick a start date of today or earlier', 'Future-dated assignments are not supported here.');
            return;
        }
        assignMutation.mutate({
            employee_ids: selectedIds,
            work_schedule_id: scheduleId,
            effective_from: effectiveFrom,
        });
    }

    const assignedCount = assignments.length;
    const availableCount = availableEmployees.length;
    const allPickerSelected =
        filteredPickerEmployees.length > 0 &&
        filteredPickerEmployees.every((e) => selectedIds.includes(e.id));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Users className="h-5 w-5 text-indigo-500" />
                        {schedule?.name || 'Schedule'}
                    </DialogTitle>
                    <DialogDescription className="flex flex-wrap items-center gap-2">
                        <span className="inline-flex items-center gap-1 text-slate-500">
                            <Clock className="h-3.5 w-3.5" />
                            {schedule?.start_time && schedule?.end_time
                                ? `${schedule.start_time.slice(0, 5)} - ${schedule.end_time.slice(0, 5)}`
                                : 'Flexible hours'}
                        </span>
                        <span className="text-slate-300">·</span>
                        <span>Manage who works on this schedule</span>
                    </DialogDescription>
                </DialogHeader>

                <Tabs value={tab} onValueChange={setTab} className="w-full">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="assigned">
                            Assigned
                            <span className="ml-1.5 rounded-full bg-slate-200 px-1.5 text-xs font-semibold text-slate-600 data-[state=active]:bg-indigo-100">
                                {assignedCount}
                            </span>
                        </TabsTrigger>
                        <TabsTrigger value="add">
                            <UserPlus className="mr-1.5 h-3.5 w-3.5" />
                            Add Employees
                        </TabsTrigger>
                    </TabsList>

                    {/* ───── Assigned tab ───── */}
                    <TabsContent value="assigned" className="space-y-3">
                        {assignedCount > 0 && (
                            <div className="relative">
                                <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    aria-label="Search assigned employees"
                                    placeholder="Search assigned employees..."
                                    value={assignedSearch}
                                    onChange={(e) => setAssignedSearch(e.target.value)}
                                    className="w-full rounded-md border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-300"
                                />
                            </div>
                        )}

                        <div className="max-h-[20rem] min-h-[12rem] overflow-y-auto rounded-lg border border-slate-200">
                            {assignedQuery.isLoading ? (
                                <div className="space-y-2 p-3">
                                    {Array.from({ length: 4 }).map((_, i) => (
                                        <div key={i} className="flex items-center gap-3">
                                            <div className="h-9 w-9 animate-pulse rounded-full bg-slate-200" />
                                            <div className="flex-1 space-y-1.5">
                                                <div className="h-3.5 w-40 animate-pulse rounded bg-slate-200" />
                                                <div className="h-3 w-24 animate-pulse rounded bg-slate-100" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : filteredAssignments.length === 0 ? (
                                <EmptyState
                                    icon={Users}
                                    accent="indigo"
                                    title={assignedCount === 0 ? 'No one assigned yet' : 'No matches'}
                                    description={
                                        assignedCount === 0
                                            ? 'Use the “Add Employees” tab to assign staff to this schedule.'
                                            : 'Try a different search.'
                                    }
                                    action={
                                        assignedCount === 0 ? (
                                            <Button size="sm" onClick={() => setTab('add')}>
                                                <UserPlus className="mr-1.5 h-3.5 w-3.5" />
                                                Add Employees
                                            </Button>
                                        ) : null
                                    }
                                />
                            ) : (
                                <ul className="divide-y divide-slate-100">
                                    {filteredAssignments.map((assignment) => {
                                        const hasCustom =
                                            assignment.custom_start_time && assignment.custom_end_time;
                                        const isConfirming = confirmRemoveId === assignment.id;
                                        return (
                                            <li
                                                key={assignment.id}
                                                className="flex items-center justify-between gap-3 px-3 py-2.5 hover:bg-slate-50"
                                            >
                                                <EmployeeIdentity employee={assignment.employee} />
                                                <div className="flex shrink-0 items-center gap-3">
                                                    <div className="hidden text-right sm:block">
                                                        {hasCustom ? (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-600">
                                                                <Clock className="h-3 w-3" />
                                                                {assignment.custom_start_time.slice(0, 5)}–
                                                                {assignment.custom_end_time.slice(0, 5)}
                                                            </span>
                                                        ) : null}
                                                        <p className="mt-0.5 flex items-center justify-end gap-1 text-[11px] text-slate-400">
                                                            <Calendar className="h-3 w-3" />
                                                            from {formatDate(assignment.effective_from)}
                                                        </p>
                                                    </div>
                                                    {isConfirming ? (
                                                        <div className="flex items-center gap-1">
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                className="h-7 bg-red-600 px-2.5 text-xs text-white hover:bg-red-700 hover:text-white focus-visible:ring-red-500"
                                                                disabled={removeMutation.isPending}
                                                                onClick={() => removeMutation.mutate(assignment.id)}
                                                            >
                                                                <Trash2 className="mr-1 h-3.5 w-3.5" />
                                                                Remove
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                className="h-7 w-7 p-0 text-slate-400 hover:text-slate-600"
                                                                disabled={removeMutation.isPending}
                                                                onClick={() => setConfirmRemoveId(null)}
                                                                aria-label="Cancel removal"
                                                                title="Cancel"
                                                            >
                                                                <X className="h-3.5 w-3.5" />
                                                            </Button>
                                                        </div>
                                                    ) : (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="h-7 w-7 p-0 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                                            onClick={() => setConfirmRemoveId(assignment.id)}
                                                            aria-label={`Remove ${assignment.employee?.full_name || 'employee'} from schedule`}
                                                            title="Remove from schedule"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </div>
                    </TabsContent>

                    {/* ───── Add Employees tab ───── */}
                    <TabsContent value="add" className="space-y-3">
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="flex-1">
                                <Label>Effective From</Label>
                                <Input
                                    type="date"
                                    value={effectiveFrom}
                                    max={todayIso()}
                                    onChange={(e) => setEffectiveFrom(e.target.value)}
                                />
                            </div>
                            <div className="text-xs text-slate-400">
                                {selectedIds.length > 0
                                    ? `${selectedIds.length} selected`
                                    : `${availableCount} available`}
                            </div>
                        </div>

                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <Label>Select Employees</Label>
                                {filteredPickerEmployees.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={toggleAll}
                                        className="rounded text-xs font-medium text-slate-500 transition-colors hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1"
                                    >
                                        {allPickerSelected ? 'Deselect All' : 'Select All'}
                                    </button>
                                )}
                            </div>

                            <div className="relative mb-2">
                                <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    aria-label="Search employees or departments"
                                    placeholder="Search employees or departments..."
                                    value={pickerSearch}
                                    onChange={(e) => setPickerSearch(e.target.value)}
                                    className="w-full rounded-md border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-300"
                                />
                            </div>

                            <div className="max-h-[20rem] min-h-[12rem] overflow-y-auto rounded-lg border border-slate-200">
                                {employeesQuery.isLoading ? (
                                    <p className="py-6 text-center text-sm text-slate-400">Loading employees…</p>
                                ) : filteredPickerEmployees.length === 0 ? (
                                    <EmptyState
                                        icon={Users}
                                        accent="emerald"
                                        title={availableCount === 0 ? 'Everyone is already assigned' : 'No matches'}
                                        description={
                                            availableCount === 0
                                                ? 'All active employees are on this schedule.'
                                                : 'Try a different search.'
                                        }
                                    />
                                ) : (
                                    deptNames.map((dept) => {
                                        const deptEmps = employeesByDept[dept];
                                        const state = deptSelectionState(dept);
                                        const isCollapsed = collapsedDepts[dept];
                                        return (
                                            <div key={dept}>
                                                <div className="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-3 py-2">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setCollapsedDepts((p) => ({ ...p, [dept]: !p[dept] }))
                                                        }
                                                        aria-label={isCollapsed ? `Expand ${dept}` : `Collapse ${dept}`}
                                                        className="rounded text-slate-500 transition-colors hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1"
                                                    >
                                                        {isCollapsed ? (
                                                            <ChevronRight className="h-3.5 w-3.5" />
                                                        ) : (
                                                            <ChevronDown className="h-3.5 w-3.5" />
                                                        )}
                                                    </button>
                                                    <Checkbox
                                                        checked={state === 'all' ? true : state === 'partial' ? 'indeterminate' : false}
                                                        onCheckedChange={() => toggleDept(dept)}
                                                        className="h-3.5 w-3.5"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setCollapsedDepts((p) => ({ ...p, [dept]: !p[dept] }))
                                                        }
                                                        aria-label={isCollapsed ? `Expand ${dept}` : `Collapse ${dept}`}
                                                        className="flex flex-1 items-center justify-between rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1"
                                                    >
                                                        <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                                                            {dept}
                                                        </span>
                                                        <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-500">
                                                            {deptEmps.filter((e) => selectedIds.includes(e.id)).length}/
                                                            {deptEmps.length}
                                                        </span>
                                                    </button>
                                                </div>

                                                {!isCollapsed &&
                                                    deptEmps.map((emp) => {
                                                        const movingFrom = currentScheduleByEmployee[emp.id];
                                                        return (
                                                            <label
                                                                key={emp.id}
                                                                className="flex cursor-pointer items-center gap-2.5 border-b border-slate-50 px-4 py-2 last:border-0 hover:bg-slate-50"
                                                            >
                                                                <Checkbox
                                                                    checked={selectedIds.includes(emp.id)}
                                                                    onCheckedChange={() => toggleEmployee(emp.id)}
                                                                />
                                                                <div className="flex flex-1 items-center justify-between gap-2">
                                                                    <span className="text-sm text-slate-900">
                                                                        {emp.full_name}
                                                                    </span>
                                                                    {movingFrom && (
                                                                        <span className="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-600">
                                                                            on {movingFrom.name}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </label>
                                                        );
                                                    })}
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-2 pt-1">
                            <Button variant="outline" onClick={() => setTab('assigned')}>
                                Cancel
                            </Button>
                            <Button
                                onClick={handleAssign}
                                disabled={
                                    assignMutation.isPending || selectedIds.length === 0 || !effectiveFrom
                                }
                            >
                                {assignMutation.isPending
                                    ? 'Assigning…'
                                    : `Assign ${selectedIds.length || ''}`.trim()}
                            </Button>
                        </div>
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}
