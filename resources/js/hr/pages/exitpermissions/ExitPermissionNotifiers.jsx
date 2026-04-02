import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Trash2,
    Bell,
    Building2,
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
import { Label } from '../../components/ui/label';
import PageHeader from '../../components/PageHeader';
import ConfirmDialog from '../../components/ConfirmDialog';
import {
    getExitPermissionNotifiers,
    addExitPermissionNotifier,
    removeExitPermissionNotifier,
    fetchDepartments,
    fetchEmployees,
} from '../../lib/api';

const EMPTY_FORM = {
    department_id: '',
    employee_id: '',
};

function SkeletonTable() {
    return (
        <div className="space-y-3 p-4">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 py-3">
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function ExitPermissionNotifiers() {
    const queryClient = useQueryClient();
    const [showDialog, setShowDialog] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [form, setForm] = useState({ ...EMPTY_FORM });

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'exit-permission-notifiers'],
        queryFn: () => getExitPermissionNotifiers(),
    });

    const { data: departmentsRes } = useQuery({
        queryKey: ['hr', 'departments'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const { data: employeesRes } = useQuery({
        queryKey: ['hr', 'employees', 'all'],
        queryFn: () => fetchEmployees({ per_page: 200, status: 'active' }),
    });

    const addMutation = useMutation({
        mutationFn: addExitPermissionNotifier,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'exit-permission-notifiers'] });
            closeDialog();
        },
    });

    const deleteMutation = useMutation({
        mutationFn: removeExitPermissionNotifier,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'exit-permission-notifiers'] });
            setDeleteTarget(null);
        },
    });

    const notifiers = data?.data || [];
    const departments = departmentsRes?.data || [];
    const allEmployees = employeesRes?.data || [];

    const filteredEmployees = form.department_id
        ? allEmployees.filter(
              (emp) => String(emp.department_id) === String(form.department_id),
          )
        : allEmployees;

    function openCreate() {
        setForm({ ...EMPTY_FORM });
        setShowDialog(true);
    }

    function closeDialog() {
        setShowDialog(false);
        setForm({ ...EMPTY_FORM });
    }

    function handleDepartmentChange(value) {
        setForm({ department_id: value, employee_id: '' });
    }

    function handleSave() {
        addMutation.mutate({
            department_id: form.department_id,
            employee_id: form.employee_id,
        });
    }

    const isSaving = addMutation.isPending;
    const canSave = !!form.department_id && !!form.employee_id;

    return (
        <div className="space-y-6">
            <PageHeader
                title="Exit Permission Notifiers"
                description="Configure employees who receive notifications when exit permissions are submitted"
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Notifier
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <SkeletonTable />
                    ) : notifiers.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Bell className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No notifiers configured</p>
                            <p className="mb-4 text-xs text-zinc-400">
                                Add employees who should be notified when exit permissions are submitted
                            </p>
                            <Button onClick={openCreate} size="sm">
                                <Plus className="mr-2 h-4 w-4" />
                                Add Notifier
                            </Button>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Position</TableHead>
                                    <TableHead className="w-24">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {notifiers.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            <p className="text-sm font-medium text-zinc-900">
                                                {item.employee?.full_name ?? item.employee?.name}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Building2 className="h-4 w-4 text-zinc-400" />
                                                <span className="text-sm text-zinc-700">
                                                    {item.employee?.department?.name ?? '—'}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <span className="text-sm text-zinc-600">
                                                {item.employee?.position?.name ?? '—'}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setDeleteTarget(item)}
                                                className="text-red-500 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Add Notifier Dialog */}
            <Dialog open={showDialog} onOpenChange={closeDialog}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add Notifier</DialogTitle>
                        <DialogDescription>
                            Select an employee to receive exit permission notifications
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label className="mb-1 block">Department</Label>
                            <Select
                                value={form.department_id}
                                onValueChange={handleDepartmentChange}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select department" />
                                </SelectTrigger>
                                <SelectContent>
                                    {departments.map((dept) => (
                                        <SelectItem key={dept.id} value={String(dept.id)}>
                                            {dept.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label className="mb-1 block">Employee</Label>
                            <Select
                                value={form.employee_id}
                                onValueChange={(val) =>
                                    setForm((prev) => ({ ...prev, employee_id: val }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select employee" />
                                </SelectTrigger>
                                <SelectContent>
                                    {filteredEmployees.length === 0 ? (
                                        <SelectItem value="_none" disabled>
                                            No employees found
                                        </SelectItem>
                                    ) : (
                                        filteredEmployees.map((emp) => (
                                            <SelectItem key={emp.id} value={String(emp.id)}>
                                                {emp.full_name}
                                                {emp.department?.name
                                                    ? ` — ${emp.department.name}`
                                                    : ''}
                                            </SelectItem>
                                        ))
                                    )}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeDialog}>
                            Cancel
                        </Button>
                        <Button onClick={handleSave} disabled={isSaving || !canSave}>
                            {isSaving ? 'Adding...' : 'Add Notifier'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={() => setDeleteTarget(null)}
                title="Remove Notifier"
                description={`Remove ${deleteTarget?.employee?.full_name ?? deleteTarget?.employee?.name} from exit permission notifiers?`}
                confirmLabel="Remove"
                variant="destructive"
                loading={deleteMutation.isPending}
                onConfirm={() => deleteMutation.mutate(deleteTarget.id)}
            />
        </div>
    );
}
