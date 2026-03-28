import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Users,
    Calendar,
    UserPlus,
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
    const [showEditDialog, setShowEditDialog] = useState(false);
    const [showBulkDialog, setShowBulkDialog] = useState(false);
    const [editTarget, setEditTarget] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [editForm, setEditForm] = useState({
        work_schedule_id: '',
        custom_start_time: '',
        custom_end_time: '',
        effective_from: '',
    });
    const [bulkForm, setBulkForm] = useState({
        employee_ids: [],
        work_schedule_id: '',
        effective_from: '',
    });

    const filters = {
        department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
        work_schedule_id: scheduleFilter !== 'all' ? scheduleFilter : undefined,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'employee-schedules', filters],
        queryFn: () => fetchEmployeeSchedules(filters),
    });

    const { data: schedulesData } = useQuery({
        queryKey: ['hr', 'attendance', 'schedules'],
        queryFn: fetchSchedules,
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'all'],
        queryFn: () => fetchEmployees({ per_page: 200, status: 'active' }),
    });

    const assignMutation = useMutation({
        mutationFn: assignEmployeeSchedule,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'employee-schedules'] });
            setShowBulkDialog(false);
            setBulkForm({ employee_ids: [], work_schedule_id: '', effective_from: '' });
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateEmployeeSchedule(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'employee-schedules'] });
            setShowEditDialog(false);
            setEditTarget(null);
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deleteEmployeeSchedule,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'employee-schedules'] });
            setDeleteTarget(null);
        },
    });

    const assignments = data?.data || [];
    const schedules = schedulesData?.data || [];
    const employees = employeesData?.data || [];

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

    function handleSaveEdit() {
        updateMutation.mutate({ id: editTarget.id, data: editForm });
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

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="w-48">
                            <Select value={departmentFilter} onValueChange={setDepartmentFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Department" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Departments</SelectItem>
                                    {[...new Set(assignments.map((a) => a.employee?.department?.name).filter(Boolean))].map((name) => (
                                        <SelectItem key={name} value={name}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="w-48">
                            <Select value={scheduleFilter} onValueChange={setScheduleFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Schedule" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Schedules</SelectItem>
                                    {schedules.map((s) => (
                                        <SelectItem key={s.id} value={String(s.id)}>
                                            {s.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Assignments Table */}
            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <SkeletonTable />
                    ) : assignments.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Calendar className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No schedule assignments</p>
                            <p className="mb-4 text-xs text-zinc-400">Assign schedules to employees to track their attendance</p>
                            <Button onClick={() => setShowBulkDialog(true)} size="sm">
                                <UserPlus className="mr-2 h-4 w-4" />
                                Assign Schedules
                            </Button>
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
                                    <TableHead className="w-24">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {assignments.map((assignment) => (
                                    <TableRow key={assignment.id}>
                                        <TableCell>
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {assignment.employee?.full_name || 'Unknown'}
                                                </p>
                                                <p className="text-xs text-zinc-500">
                                                    {assignment.employee?.employee_id || ''}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {assignment.employee?.department?.name || '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                {assignment.work_schedule?.name || '-'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {assignment.custom_start_time && assignment.custom_end_time
                                                ? `${assignment.custom_start_time.slice(0, 5)} - ${assignment.custom_end_time.slice(0, 5)}`
                                                : '-'}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {formatDate(assignment.effective_from)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(assignment)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setDeleteTarget(assignment)}
                                                    className="text-red-500 hover:text-red-700"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

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
                        <Button onClick={handleSaveEdit} disabled={updateMutation.isPending}>
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
                            <Label className="mb-2 block">
                                Select Employees ({bulkForm.employee_ids.length} selected)
                            </Label>
                            <div className="max-h-60 space-y-1 overflow-y-auto rounded-lg border border-zinc-200 p-2">
                                {employees.length === 0 ? (
                                    <p className="py-4 text-center text-sm text-zinc-400">No employees found</p>
                                ) : (
                                    employees.map((emp) => (
                                        <label
                                            key={emp.id}
                                            className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-zinc-50"
                                        >
                                            <Checkbox
                                                checked={bulkForm.employee_ids.includes(emp.id)}
                                                onCheckedChange={() => toggleBulkEmployee(emp.id)}
                                            />
                                            <span className="text-sm text-zinc-900">{emp.full_name}</span>
                                            <span className="text-xs text-zinc-400">{emp.department?.name}</span>
                                        </label>
                                    ))
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
                            disabled={assignMutation.isPending || !bulkForm.work_schedule_id || bulkForm.employee_ids.length === 0}
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
