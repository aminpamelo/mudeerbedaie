import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Building2,
    Shield,
    Users,
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
import { Badge } from '../../components/ui/badge';
import { Checkbox } from '../../components/ui/checkbox';
import PageHeader from '../../components/PageHeader';
import ConfirmDialog from '../../components/ConfirmDialog';
import { cn } from '../../lib/utils';
import {
    fetchDepartmentApprovers,
    createDepartmentApprover,
    updateDepartmentApprover,
    deleteDepartmentApprover,
    fetchDepartments,
    fetchEmployees,
} from '../../lib/api';

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
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

function ApproverBadges({ approvers }) {
    if (!approvers || approvers.length === 0) {
        return <span className="text-sm text-zinc-400">Not assigned</span>;
    }
    return (
        <div className="flex flex-wrap gap-1">
            {approvers.map((approver) => (
                <Badge key={approver.id} variant="secondary" className="text-xs">
                    {approver.full_name}
                </Badge>
            ))}
        </div>
    );
}

const EMPTY_FORM = {
    department_id: '',
    ot_approver_ids: [],
    leave_approver_ids: [],
    claims_approver_ids: [],
};

export default function DepartmentApprovers() {
    const queryClient = useQueryClient();
    const [showDialog, setShowDialog] = useState(false);
    const [editTarget, setEditTarget] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [form, setForm] = useState({ ...EMPTY_FORM });

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'department-approvers'],
        queryFn: fetchDepartmentApprovers,
    });

    const { data: departmentsRes } = useQuery({
        queryKey: ['hr', 'departments'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const { data: employeesRes } = useQuery({
        queryKey: ['hr', 'employees', 'all'],
        queryFn: () => fetchEmployees({ per_page: 200, status: 'active' }),
    });

    const createMutation = useMutation({
        mutationFn: createDepartmentApprover,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'department-approvers'] });
            closeDialog();
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateDepartmentApprover(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'department-approvers'] });
            closeDialog();
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deleteDepartmentApprover,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'department-approvers'] });
            setDeleteTarget(null);
        },
    });

    const approvers = data?.data || [];
    const departments = departmentsRes?.data || [];
    const employees = employeesRes?.data || [];

    function openCreate() {
        setEditTarget(null);
        setForm({ ...EMPTY_FORM });
        setShowDialog(true);
    }

    function openEdit(item) {
        setEditTarget(item);
        setForm({
            department_id: String(item.department_id || ''),
            ot_approver_ids: item.ot_approvers?.map((a) => a.id) || [],
            leave_approver_ids: item.leave_approvers?.map((a) => a.id) || [],
            claims_approver_ids: item.claims_approvers?.map((a) => a.id) || [],
        });
        setShowDialog(true);
    }

    function closeDialog() {
        setShowDialog(false);
        setEditTarget(null);
        setForm({ ...EMPTY_FORM });
    }

    function handleSave() {
        if (editTarget) {
            updateMutation.mutate({ id: editTarget.id, data: form });
        } else {
            createMutation.mutate(form);
        }
    }

    function toggleApprover(field, employeeId) {
        setForm((prev) => ({
            ...prev,
            [field]: prev[field].includes(employeeId)
                ? prev[field].filter((id) => id !== employeeId)
                : [...prev[field], employeeId],
        }));
    }

    const isSaving = createMutation.isPending || updateMutation.isPending;

    function ApproverSelector({ label, field }) {
        return (
            <div>
                <Label className="mb-2 block">{label} ({form[field].length} selected)</Label>
                <div className="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-zinc-200 p-2">
                    {employees.length === 0 ? (
                        <p className="py-3 text-center text-sm text-zinc-400">No employees found</p>
                    ) : (
                        employees.map((emp) => (
                            <label
                                key={emp.id}
                                className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-zinc-50"
                            >
                                <Checkbox
                                    checked={form[field].includes(emp.id)}
                                    onCheckedChange={() => toggleApprover(field, emp.id)}
                                />
                                <span className="text-sm text-zinc-900">{emp.full_name}</span>
                                <span className="text-xs text-zinc-400">{emp.department?.name}</span>
                            </label>
                        ))
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Department Approvers"
                description="Configure approval chains for overtime, leave, and claims by department"
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Configuration
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <SkeletonTable />
                    ) : approvers.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Shield className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No approver configurations</p>
                            <p className="mb-4 text-xs text-zinc-400">Set up department approvers for overtime, leave, and claims</p>
                            <Button onClick={openCreate} size="sm">
                                <Plus className="mr-2 h-4 w-4" />
                                Add Configuration
                            </Button>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Department</TableHead>
                                    <TableHead>OT Approver</TableHead>
                                    <TableHead>Leave Approver</TableHead>
                                    <TableHead>Claims Approver</TableHead>
                                    <TableHead className="w-24">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {approvers.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Building2 className="h-4 w-4 text-zinc-400" />
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {item.department?.name || 'Unknown'}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <ApproverBadges approvers={item.ot_approvers} />
                                        </TableCell>
                                        <TableCell>
                                            <ApproverBadges approvers={item.leave_approvers} />
                                        </TableCell>
                                        <TableCell>
                                            <ApproverBadges approvers={item.claims_approvers} />
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(item)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setDeleteTarget(item)}
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

            {/* Create/Edit Dialog */}
            <Dialog open={showDialog} onOpenChange={closeDialog}>
                <DialogContent className="max-w-lg max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{editTarget ? 'Edit Approver Configuration' : 'Add Approver Configuration'}</DialogTitle>
                        <DialogDescription>
                            {editTarget
                                ? 'Update the approval chain for this department'
                                : 'Select a department and assign approvers for each type'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Department</Label>
                            <Select
                                value={form.department_id}
                                onValueChange={(val) => setForm({ ...form, department_id: val })}
                                disabled={!!editTarget}
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

                        <ApproverSelector label="OT Approvers" field="ot_approver_ids" />
                        <ApproverSelector label="Leave Approvers" field="leave_approver_ids" />
                        <ApproverSelector label="Claims Approvers" field="claims_approver_ids" />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeDialog}>
                            Cancel
                        </Button>
                        <Button onClick={handleSave} disabled={isSaving || !form.department_id}>
                            {isSaving ? 'Saving...' : editTarget ? 'Update' : 'Save Configuration'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={() => setDeleteTarget(null)}
                title="Delete Approver Configuration"
                description={`Remove approver configuration for ${deleteTarget?.department?.name}? This will unset all approvers for this department.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={deleteMutation.isPending}
                onConfirm={() => deleteMutation.mutate(deleteTarget.id)}
            />
        </div>
    );
}
