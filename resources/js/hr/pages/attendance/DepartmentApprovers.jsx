import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Building2,
    Shield,
    Users,
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

function TieredApproverBadges({ approversByTier }) {
    if (!approversByTier || Object.keys(approversByTier).length === 0) {
        return <span className="text-sm text-zinc-400">Not assigned</span>;
    }

    const sortedTiers = Object.entries(approversByTier).sort(([a], [b]) => Number(a) - Number(b));

    return (
        <div className="space-y-1">
            {sortedTiers.map(([tier, approvers]) => (
                <div key={tier} className="flex items-center gap-1.5">
                    <span className="text-[10px] font-semibold text-zinc-400 uppercase shrink-0">T{tier}</span>
                    <div className="flex flex-wrap gap-1">
                        {(approvers || []).map((approver) => (
                            <Badge key={approver.id} variant="secondary" className="text-xs">
                                {approver.full_name}
                            </Badge>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

function TierBlock({ tierData, tierIndex, totalTiers, employees, onToggle, onRemove }) {
    const [search, setSearch] = useState('');
    const filtered = employees.filter((emp) => {
        const q = search.toLowerCase();
        return (
            emp.full_name?.toLowerCase().includes(q) ||
            emp.department?.name?.toLowerCase().includes(q)
        );
    });

    return (
        <div className="rounded-lg border border-zinc-200">
            <div className="flex items-center justify-between bg-zinc-50 px-3 py-1.5 rounded-t-lg border-b border-zinc-200">
                <span className="text-xs font-semibold text-zinc-600">
                    Tier {tierData.tier}
                    <span className="ml-1.5 text-zinc-400 font-normal">
                        ({tierData.employee_ids.length} approver{tierData.employee_ids.length !== 1 ? 's' : ''})
                    </span>
                </span>
                {totalTiers > 1 && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onRemove}
                        className="h-6 w-6 p-0 text-red-400 hover:text-red-600"
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                )}
            </div>
            <div className="relative border-b border-zinc-100 px-2 py-1.5">
                <Search className="absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-400" />
                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search employee..."
                    className="h-7 border-0 pl-7 text-xs shadow-none focus-visible:ring-0"
                />
            </div>
            <div className="max-h-28 space-y-1 overflow-y-auto p-2">
                {filtered.length === 0 ? (
                    <p className="py-2 text-center text-xs text-zinc-400">No employees found</p>
                ) : (
                    filtered.map((emp) => (
                        <label
                            key={emp.id}
                            className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-zinc-50"
                        >
                            <Checkbox
                                checked={tierData.employee_ids.includes(emp.id)}
                                onCheckedChange={() => onToggle(emp.id)}
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

function TieredApproverSelector({ label, field, form, setForm, employees }) {
    const tiers = form[field] || [{ tier: 1, employee_ids: [] }];

    function addTier() {
        const nextTier = tiers.length + 1;
        setForm((prev) => ({
            ...prev,
            [field]: [...prev[field], { tier: nextTier, employee_ids: [] }],
        }));
    }

    function removeTier(index) {
        if (tiers.length <= 1) return;
        setForm((prev) => ({
            ...prev,
            [field]: prev[field]
                .filter((_, i) => i !== index)
                .map((t, i) => ({ ...t, tier: i + 1 })),
        }));
    }

    function toggleEmployee(tierIndex, employeeId) {
        setForm((prev) => ({
            ...prev,
            [field]: prev[field].map((t, i) =>
                i === tierIndex
                    ? {
                          ...t,
                          employee_ids: t.employee_ids.includes(employeeId)
                              ? t.employee_ids.filter((id) => id !== employeeId)
                              : [...t.employee_ids, employeeId],
                      }
                    : t
            ),
        }));
    }

    const totalSelected = tiers.reduce((sum, t) => sum + t.employee_ids.length, 0);

    return (
        <div>
            <Label className="mb-2 block">{label} ({totalSelected} selected)</Label>
            <div className="space-y-3">
                {tiers.map((tierData, tierIndex) => (
                    <TierBlock
                        key={tierIndex}
                        tierData={tierData}
                        tierIndex={tierIndex}
                        totalTiers={tiers.length}
                        employees={employees}
                        onToggle={(empId) => toggleEmployee(tierIndex, empId)}
                        onRemove={() => removeTier(tierIndex)}
                    />
                ))}
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={addTier}
                className="mt-2 w-full"
            >
                <Plus className="mr-1 h-3.5 w-3.5" />
                Add Tier {tiers.length + 1}
            </Button>
        </div>
    );
}

const EMPTY_FORM = {
    department_id: '',
    ot_approvers: [{ tier: 1, employee_ids: [] }],
    leave_approvers: [{ tier: 1, employee_ids: [] }],
    claims_approvers: [{ tier: 1, employee_ids: [] }],
    exit_permission_approvers: [{ tier: 1, employee_ids: [] }],
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

        const parseTiers = (approversByTier) => {
            if (!approversByTier || typeof approversByTier !== 'object' || Object.keys(approversByTier).length === 0) {
                return [{ tier: 1, employee_ids: [] }];
            }
            const tiers = Object.entries(approversByTier).map(([tier, approvers]) => ({
                tier: parseInt(tier),
                employee_ids: (approvers || []).map((a) => a.id),
            }));
            return tiers.length > 0 ? tiers.sort((a, b) => a.tier - b.tier) : [{ tier: 1, employee_ids: [] }];
        };

        setForm({
            department_id: String(item.department_id || ''),
            ot_approvers: parseTiers(item.ot_approvers),
            leave_approvers: parseTiers(item.leave_approvers),
            claims_approvers: parseTiers(item.claims_approvers),
            exit_permission_approvers: parseTiers(item.exit_permission_approvers),
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

    const isSaving = createMutation.isPending || updateMutation.isPending;

    return (
        <div className="space-y-6">
            <PageHeader
                title="Department Approvers"
                description="Configure approval chains for overtime, leave, claims, and exit permissions by department"
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
                            <p className="mb-4 text-xs text-zinc-400">Set up department approvers for overtime, leave, claims, and exit permissions</p>
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
                                    <TableHead>Exit Permission Approver</TableHead>
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
                                            <TieredApproverBadges approversByTier={item.ot_approvers} />
                                        </TableCell>
                                        <TableCell>
                                            <TieredApproverBadges approversByTier={item.leave_approvers} />
                                        </TableCell>
                                        <TableCell>
                                            <TieredApproverBadges approversByTier={item.claims_approvers} />
                                        </TableCell>
                                        <TableCell>
                                            <TieredApproverBadges approversByTier={item.exit_permission_approvers} />
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

                        <TieredApproverSelector label="OT Approvers" field="ot_approvers" form={form} setForm={setForm} employees={employees} />
                        <TieredApproverSelector label="Leave Approvers" field="leave_approvers" form={form} setForm={setForm} employees={employees} />
                        <TieredApproverSelector label="Claims Approvers" field="claims_approvers" form={form} setForm={setForm} employees={employees} />
                        <TieredApproverSelector label="Exit Permission Approvers" field="exit_permission_approvers" form={form} setForm={setForm} employees={employees} />
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
