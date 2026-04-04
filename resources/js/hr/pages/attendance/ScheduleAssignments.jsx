import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Users,
    Calendar,
    UserPlus,
    ChevronDown,
    ChevronRight,
    Search,
} from 'lucide-react';
import {
    Card,
    CardContent,
} from '../../components/ui/card';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Badge } from '../../components/ui/badge';
import { Checkbox } from '../../components/ui/checkbox';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import ConfirmDialog from '../../components/ConfirmDialog';
import { cn } from '../../lib/utils';
import {
    fetchEmployeeSchedules,
    assignEmployeeSchedule,
    updateEmployeeSchedule,
    deleteEmployeeSchedule,
    fetchEmployees,
    fetchSchedules,
} from '../../lib/api';

function formatDate(dateString) {
    if (!dateString) {
        return '-';
    }
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                        <div className="h-3 w-28 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function ScheduleAssignments() {
    const queryClient = useQueryClient();
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [scheduleFilter, setScheduleFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [tableSearch, setTableSearch] = useState('');
    const [showEditDialog, setShowEditDialog] = useState(false);
    const [showBulkDialog, setShowBulkDialog] = useState(false);
    const [showQuickAssignDialog, setShowQuickAssignDialog] = useState(false);
    const [quickAssignEmployee, setQuickAssignEmployee] = useState(null);
    const [editTarget, setEditTarget] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [editForm, setEditForm] = useState({
        work_schedule_id: '',
        custom_start_time: '',
        custom_end_time: '',
        effective_from: '',
    });
    const [quickAssignForm, setQuickAssignForm] = useState({
        work_schedule_id: '',
        effective_from: '',
    });
    const [bulkForm, setBulkForm] = useState({
        employee_ids: [],
        work_schedule_id: '',
        effective_from: '',
    });

    const { data: assignmentsData, isLoading: assignmentsLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'employee-schedules'],
        queryFn: () => fetchEmployeeSchedules({ per_page: 500 }),
    });

    const { data: schedulesData } = useQuery({
        queryKey: ['hr', 'attendance', 'schedules'],
        queryFn: fetchSchedules,
    });

    const { data: employeesData, isLoading: employeesLoading } = useQuery({
        queryKey: ['hr', 'employees', 'all'],
        queryFn: () => fetchEmployees({ per_page: 500, status: 'active' }),
    });

    const isLoading = assignmentsLoading || employeesLoading;

    const assignMutation = useMutation({
        mutationFn: assignEmployeeSchedule,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'employee-schedules'] });
            setShowBulkDialog(false);
            setShowQuickAssignDialog(false);
            setBulkForm({ employee_ids: [], work_schedule_id: '', effective_from: '' });
            setQuickAssignForm({ work_schedule_id: '', effective_from: '' });
            setQuickAssignEmployee(null);
        },
        onError: (error) => {
            const message = error?.response?.data?.message || 'Failed to assign schedule. Please try again.';
            alert(message);
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateEmployeeSchedule(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'employee-schedules'] });
            setShowEditDialog(false);
            setEditTarget(null);
        },
        onError: (error) => {
            const message = error?.response?.data?.message || 'Failed to update schedule. Please try again.';
            alert(message);
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deleteEmployeeSchedule,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'employee-schedules'] });
            setDeleteTarget(null);
        },
        onError: (error) => {
            const message = error?.response?.data?.message || 'Failed to remove schedule assignment. Please try again.';
            alert(message);
            setDeleteTarget(null);
        },
    });

    const [bulkSearch, setBulkSearch] = useState('');
    const [collapsedDepts, setCollapsedDepts] = useState({});

    const assignments = assignmentsData?.data || [];
    const schedules = schedulesData?.data || [];
    const employees = employeesData?.data || [];

    // Build merged rows: all active employees with their assignment (if any)
    const employeeRows = useMemo(() => {
        const assignmentMap = {};
        assignments.forEach((a) => {
            const empId = a.employee_id ?? a.employee?.id;
            // Only consider active assignments (no end date or end date in the future)
            const isActive = !a.effective_to || new Date(a.effective_to) >= new Date();
            if (empId && isActive) assignmentMap[empId] = a;
        });
        return employees.map((emp) => ({
            ...emp,
            assignment: assignmentMap[emp.id] || null,
        }));
    }, [employees, assignments]);

    const unassignedCount = useMemo(
        () => employeeRows.filter((r) => !r.assignment).length,
        [employeeRows],
    );

    const departmentNames = useMemo(
        () => [...new Set(employees.map((e) => e.department?.name).filter(Boolean))].sort(),
        [employees],
    );

    const filteredRows = useMemo(() => {
        return employeeRows.filter((row) => {
            if (departmentFilter !== 'all' && row.department?.name !== departmentFilter) return false;
            if (statusFilter === 'assigned' && !row.assignment) return false;
            if (statusFilter === 'unassigned' && row.assignment) return false;
            if (scheduleFilter !== 'all' && String(row.assignment?.work_schedule_id) !== scheduleFilter) return false;
            if (tableSearch.trim()) {
                const q = tableSearch.toLowerCase();
                const matchName = row.full_name?.toLowerCase().includes(q);
                const matchId = row.employee_id?.toLowerCase().includes(q);
                const matchDept = row.department?.name?.toLowerCase().includes(q);
                if (!matchName && !matchId && !matchDept) return false;
            }
            return true;
        });
    }, [employeeRows, departmentFilter, statusFilter, scheduleFilter, tableSearch]);

    const filteredBulkEmployees = useMemo(() => {
        if (!bulkSearch.trim()) return employees;
        const q = bulkSearch.toLowerCase();
        return employees.filter(
            (emp) =>
                emp.full_name?.toLowerCase().includes(q) ||
                emp.department?.name?.toLowerCase().includes(q),
        );
    }, [employees, bulkSearch]);

    const employeesByDept = useMemo(() => {
        return filteredBulkEmployees.reduce((acc, emp) => {
            const dept = emp.department?.name || 'No Department';
            if (!acc[dept]) acc[dept] = [];
            acc[dept].push(emp);
            return acc;
        }, {});
    }, [filteredBulkEmployees]);

    const deptNames = Object.keys(employeesByDept).sort();

    function toggleDeptCollapse(dept) {
        setCollapsedDepts((prev) => ({ ...prev, [dept]: !prev[dept] }));
    }

    function getDeptSelectionState(dept) {
        const deptEmps = employeesByDept[dept] || [];
        const selectedCount = deptEmps.filter((e) => bulkForm.employee_ids.includes(e.id)).length;
        if (selectedCount === 0) return 'none';
        if (selectedCount === deptEmps.length) return 'all';
        return 'partial';
    }

    function toggleDeptEmployees(dept) {
        const deptEmps = (employeesByDept[dept] || []).map((e) => e.id);
        const state = getDeptSelectionState(dept);
        setBulkForm((prev) => {
            if (state === 'all') {
                return { ...prev, employee_ids: prev.employee_ids.filter((id) => !deptEmps.includes(id)) };
            } else {
                const merged = [...new Set([...prev.employee_ids, ...deptEmps])];
                return { ...prev, employee_ids: merged };
            }
        });
    }

    function toggleAllEmployees() {
        const allIds = filteredBulkEmployees.map((e) => e.id);
        const allSelected = allIds.every((id) => bulkForm.employee_ids.includes(id));
        setBulkForm((prev) => ({
            ...prev,
            employee_ids: allSelected ? prev.employee_ids.filter((id) => !allIds.includes(id)) : [...new Set([...prev.employee_ids, ...allIds])],
        }));
    }

    function openEdit(assignment) {
        setEditTarget(assignment);
        setEditForm({
            work_schedule_id: String(assignment.work_schedule_id || ''),
            custom_start_time: assignment.custom_start_time || '',
            custom_end_time: assignment.custom_end_time || '',
            effective_from: assignment.effective_from || '',
        });
        setShowEditDialog(true);
    }

    function openQuickAssign(employee) {
        setQuickAssignEmployee(employee);
        setQuickAssignForm({ work_schedule_id: '', effective_from: '' });
        setShowQuickAssignDialog(true);
    }

    function handleSaveEdit() {
        updateMutation.mutate({ id: editTarget.id, data: editForm });
    }

    function handleQuickAssign() {
        assignMutation.mutate({
            employee_ids: [quickAssignEmployee.id],
            work_schedule_id: quickAssignForm.work_schedule_id,
            effective_from: quickAssignForm.effective_from,
        });
    }

    function handleBulkAssign() {
        assignMutation.mutate(bulkForm);
    }

    function toggleBulkEmployee(employeeId) {
        setBulkForm((prev) => ({
            ...prev,
            employee_ids: prev.employee_ids.includes(employeeId)
                ? prev.employee_ids.filter((id) => id !== employeeId)
                : [...prev.employee_ids, employeeId],
        }));
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Schedule Assignments"
                description="Assign work schedules to employees"
                action={
                    <Button onClick={() => setShowBulkDialog(true)}>
                        <UserPlus className="mr-2 h-4 w-4" />
                        Bulk Assign
                    </Button>
                }
            />

            {/* Summary badges */}
            {!isLoading && unassignedCount > 0 && (
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setStatusFilter('unassigned')}
                        className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 transition-colors hover:bg-amber-100"
                    >
                        <span className="h-1.5 w-1.5 rounded-full bg-amber-500" />
                        {unassignedCount} employee{unassignedCount !== 1 ? 's' : ''} without a schedule
                    </button>
                    {statusFilter === 'unassigned' && (
                        <button
                            type="button"
                            onClick={() => setStatusFilter('all')}
                            className="text-xs text-zinc-400 hover:text-zinc-600"
                        >
                            Show all
                        </button>
                    )}
                </div>
            )}

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        {/* Search */}
                        <div className="relative min-w-48 flex-1">
                            <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-400" />
                            <input
                                type="text"
                                placeholder="Search name, ID, department..."
                                value={tableSearch}
                                onChange={(e) => setTableSearch(e.target.value)}
                                className="w-full rounded-md border border-zinc-200 bg-white py-1.5 pl-8 pr-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-300"
                            />
                        </div>

                        <div className="w-44">
                            <Select value={departmentFilter} onValueChange={setDepartmentFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Department" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Departments</SelectItem>
                                    {departmentNames.map((name) => (
                                        <SelectItem key={name} value={name}>{name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="w-44">
                            <Select value={scheduleFilter} onValueChange={setScheduleFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Schedule" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Schedules</SelectItem>
                                    {schedules.map((s) => (
                                        <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="w-40">
                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Employees</SelectItem>
                                    <SelectItem value="assigned">Assigned</SelectItem>
                                    <SelectItem value="unassigned">Unassigned</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Employees Table */}
            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <SkeletonTable />
                    ) : filteredRows.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Users className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No employees found</p>
                            <p className="text-xs text-zinc-400">Try adjusting your filters</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Current Schedule</TableHead>
                                    <TableHead>Custom Hours</TableHead>
                                    <TableHead>Effective From</TableHead>
                                    <TableHead className="w-28">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredRows.map((row) => (
                                    <TableRow key={row.id} className={!row.assignment ? 'bg-amber-50/40' : ''}>
                                        <TableCell>
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">{row.full_name}</p>
                                                <p className="text-xs text-zinc-500">{row.employee_id}</p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {row.department?.name || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.assignment ? (
                                                <Badge variant="secondary">
                                                    {row.assignment.work_schedule?.name || '-'}
                                                </Badge>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600">
                                                    <span className="h-1.5 w-1.5 rounded-full bg-amber-400" />
                                                    Not Assigned
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {row.assignment?.custom_start_time && row.assignment?.custom_end_time
                                                ? `${row.assignment.custom_start_time.slice(0, 5)} - ${row.assignment.custom_end_time.slice(0, 5)}`
                                                : '-'}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {row.assignment ? formatDate(row.assignment.effective_from) : '-'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                {row.assignment ? (
                                                    <>
                                                        <Button variant="ghost" size="sm" onClick={() => openEdit(row.assignment)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setDeleteTarget(row.assignment)}
                                                            className="text-red-500 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </>
                                                ) : (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => openQuickAssign(row)}
                                                        className="h-7 gap-1 text-xs font-medium text-zinc-700"
                                                    >
                                                        <Plus className="h-3.5 w-3.5" />
                                                        Assign
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Quick Assign Dialog */}
            <Dialog open={showQuickAssignDialog} onOpenChange={() => { setShowQuickAssignDialog(false); setQuickAssignEmployee(null); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Assign Schedule</DialogTitle>
                        <DialogDescription>
                            Assign a work schedule to {quickAssignEmployee?.full_name}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Schedule</Label>
                            <Select value={quickAssignForm.work_schedule_id} onValueChange={(val) => setQuickAssignForm({ ...quickAssignForm, work_schedule_id: val })}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select schedule" />
                                </SelectTrigger>
                                <SelectContent>
                                    {schedules.map((s) => (
                                        <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Effective From</Label>
                            <Input
                                type="date"
                                value={quickAssignForm.effective_from}
                                onChange={(e) => setQuickAssignForm({ ...quickAssignForm, effective_from: e.target.value })}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => { setShowQuickAssignDialog(false); setQuickAssignEmployee(null); }}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleQuickAssign}
                            disabled={assignMutation.isPending || !quickAssignForm.work_schedule_id || !quickAssignForm.effective_from}
                        >
                            {assignMutation.isPending ? 'Assigning...' : 'Assign Schedule'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit Assignment Dialog */}
            <Dialog open={showEditDialog} onOpenChange={() => { setShowEditDialog(false); setEditTarget(null); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Schedule Assignment</DialogTitle>
                        <DialogDescription>
                            Update schedule for {editTarget?.employee?.full_name}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Schedule</Label>
                            <Select value={editForm.work_schedule_id} onValueChange={(val) => setEditForm({ ...editForm, work_schedule_id: val })}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select schedule" />
                                </SelectTrigger>
                                <SelectContent>
                                    {schedules.map((s) => (
                                        <SelectItem key={s.id} value={String(s.id)}>
                                            {s.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Custom Start Time (optional)</Label>
                                <Input
                                    type="time"
                                    value={editForm.custom_start_time}
                                    onChange={(e) => setEditForm({ ...editForm, custom_start_time: e.target.value })}
                                />
                            </div>
                            <div>
                                <Label>Custom End Time (optional)</Label>
                                <Input
                                    type="time"
                                    value={editForm.custom_end_time}
                                    onChange={(e) => setEditForm({ ...editForm, custom_end_time: e.target.value })}
                                />
                            </div>
                        </div>
                        <div>
                            <Label>Effective From</Label>
                            <Input
                                type="date"
                                value={editForm.effective_from}
                                onChange={(e) => setEditForm({ ...editForm, effective_from: e.target.value })}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => { setShowEditDialog(false); setEditTarget(null); }}>
                            Cancel
                        </Button>
                        <Button onClick={handleSaveEdit} disabled={updateMutation.isPending || !editForm.work_schedule_id || !editForm.effective_from}>
                            {updateMutation.isPending ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Bulk Assign Dialog */}
            <Dialog open={showBulkDialog} onOpenChange={() => setShowBulkDialog(false)}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Bulk Assign Schedule</DialogTitle>
                        <DialogDescription>
                            Select employees and assign a work schedule
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Schedule</Label>
                            <Select value={bulkForm.work_schedule_id} onValueChange={(val) => setBulkForm({ ...bulkForm, work_schedule_id: val })}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select schedule" />
                                </SelectTrigger>
                                <SelectContent>
                                    {schedules.map((s) => (
                                        <SelectItem key={s.id} value={String(s.id)}>
                                            {s.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Effective From</Label>
                            <Input
                                type="date"
                                value={bulkForm.effective_from}
                                onChange={(e) => setBulkForm({ ...bulkForm, effective_from: e.target.value })}
                            />
                        </div>
                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <Label>
                                    Select Employees
                                    {bulkForm.employee_ids.length > 0 && (
                                        <span className="ml-2 inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700">
                                            {bulkForm.employee_ids.length} selected
                                        </span>
                                    )}
                                </Label>
                                {filteredBulkEmployees.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={toggleAllEmployees}
                                        className="text-xs font-medium text-zinc-500 hover:text-zinc-900 transition-colors"
                                    >
                                        {filteredBulkEmployees.every((e) => bulkForm.employee_ids.includes(e.id))
                                            ? 'Deselect All'
                                            : 'Select All'}
                                    </button>
                                )}
                            </div>

                            {/* Search */}
                            <div className="relative mb-2">
                                <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-400" />
                                <input
                                    type="text"
                                    placeholder="Search employees or departments..."
                                    value={bulkSearch}
                                    onChange={(e) => setBulkSearch(e.target.value)}
                                    className="w-full rounded-md border border-zinc-200 bg-white py-1.5 pl-8 pr-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-300"
                                />
                            </div>

                            <div className="max-h-64 overflow-y-auto rounded-lg border border-zinc-200">
                                {filteredBulkEmployees.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-zinc-400">No employees found</p>
                                ) : (
                                    deptNames.map((dept) => {
                                        const deptEmps = employeesByDept[dept];
                                        const selState = getDeptSelectionState(dept);
                                        const isCollapsed = collapsedDepts[dept];
                                        return (
                                            <div key={dept}>
                                                {/* Department header */}
                                                <div className="flex items-center gap-2 border-b border-zinc-100 bg-zinc-50 px-3 py-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleDeptCollapse(dept)}
                                                        className="flex items-center gap-1 text-zinc-500 hover:text-zinc-700 transition-colors"
                                                    >
                                                        {isCollapsed
                                                            ? <ChevronRight className="h-3.5 w-3.5" />
                                                            : <ChevronDown className="h-3.5 w-3.5" />}
                                                    </button>
                                                    <Checkbox
                                                        checked={selState === 'all'}
                                                        ref={(el) => {
                                                            if (el) el.indeterminate = selState === 'partial';
                                                        }}
                                                        onCheckedChange={() => toggleDeptEmployees(dept)}
                                                        className="h-3.5 w-3.5"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleDeptCollapse(dept)}
                                                        className="flex flex-1 items-center justify-between"
                                                    >
                                                        <span className="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                                                            {dept}
                                                        </span>
                                                        <span className="rounded-full bg-zinc-200 px-2 py-0.5 text-xs text-zinc-500">
                                                            {deptEmps.filter((e) => bulkForm.employee_ids.includes(e.id)).length}/{deptEmps.length}
                                                        </span>
                                                    </button>
                                                </div>

                                                {/* Employees in dept */}
                                                {!isCollapsed && deptEmps.map((emp) => (
                                                    <label
                                                        key={emp.id}
                                                        className="flex cursor-pointer items-center gap-2.5 border-b border-zinc-50 px-4 py-2 last:border-0 hover:bg-zinc-50 transition-colors"
                                                    >
                                                        <Checkbox
                                                            checked={bulkForm.employee_ids.includes(emp.id)}
                                                            onCheckedChange={() => toggleBulkEmployee(emp.id)}
                                                        />
                                                        <div className="flex flex-1 items-center justify-between">
                                                            <span className="text-sm text-zinc-900">{emp.full_name}</span>
                                                            {emp.position?.name && (
                                                                <span className="text-xs text-zinc-400">{emp.position?.name}</span>
                                                            )}
                                                        </div>
                                                    </label>
                                                ))}
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowBulkDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleBulkAssign}
                            disabled={assignMutation.isPending || !bulkForm.work_schedule_id || !bulkForm.effective_from || bulkForm.employee_ids.length === 0}
                        >
                            {assignMutation.isPending ? 'Assigning...' : `Assign to ${bulkForm.employee_ids.length} Employees`}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={() => setDeleteTarget(null)}
                title="Remove Assignment"
                description={`Remove schedule assignment for ${deleteTarget?.employee?.full_name}?`}
                confirmLabel="Remove"
                variant="destructive"
                loading={deleteMutation.isPending}
                onConfirm={() => deleteMutation.mutate(deleteTarget.id)}
            />
        </div>
    );
}
