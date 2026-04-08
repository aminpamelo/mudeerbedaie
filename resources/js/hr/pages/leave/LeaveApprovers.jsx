import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Pencil,
    Trash2,
    Loader2,
    Users,
    X,
    ShieldCheck,
    Plus,
    Search,
} from 'lucide-react';
import {
    fetchDepartmentApprovers,
    createDepartmentApprover,
    updateDepartmentApprover,
    deleteDepartmentApprover,
    fetchDepartments,
    fetchEmployees,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Checkbox } from '../../components/ui/checkbox';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
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
import ConfirmDialog from '../../components/ConfirmDialog';

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="flex flex-1 gap-2">
                        <div className="h-6 w-20 animate-pulse rounded-full bg-zinc-200" />
                        <div className="h-6 w-24 animate-pulse rounded-full bg-zinc-200" />
                    </div>
                    <div className="h-8 w-20 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <ShieldCheck className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">No leave approvers configured</h3>
            <p className="mt-1 text-sm text-zinc-500">
                Set up leave approvers per department to enable the approval workflow.
            </p>
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

function TieredApproverSelector({ tiers, setTiers, employees }) {
    function addTier() {
        const nextTier = tiers.length + 1;
        setTiers([...tiers, { tier: nextTier, employee_ids: [] }]);
    }

    function removeTier(index) {
        if (tiers.length <= 1) {
            return;
        }
        setTiers(
            tiers
                .filter((_, i) => i !== index)
                .map((t, i) => ({ ...t, tier: i + 1 }))
        );
    }

    function toggleEmployee(tierIndex, employeeId) {
        setTiers(
            tiers.map((t, i) =>
                i === tierIndex
                    ? {
                          ...t,
                          employee_ids: t.employee_ids.includes(employeeId)
                              ? t.employee_ids.filter((id) => id !== employeeId)
                              : [...t.employee_ids, employeeId],
                      }
                    : t
            )
        );
    }

    const totalSelected = tiers.reduce((sum, t) => sum + t.employee_ids.length, 0);

    return (
        <div>
            <Label className="mb-2 block">Leave Approvers ({totalSelected} selected)</Label>
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

export default function LeaveApprovers() {
    const queryClient = useQueryClient();
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [showDialog, setShowDialog] = useState(false);
    const [editTarget, setEditTarget] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [departmentId, setDepartmentId] = useState('');
    const [leaveTiers, setLeaveTiers] = useState([{ tier: 1, employee_ids: [] }]);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'leave', 'approvers'],
        queryFn: fetchDepartmentApprovers,
    });

    const { data: departmentsData } = useQuery({
        queryKey: ['hr', 'departments', 'list'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'all'],
        queryFn: () => fetchEmployees({ per_page: 200, status: 'active' }),
        enabled: showDialog,
    });

    const createMutation = useMutation({
        mutationFn: createDepartmentApprover,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'approvers'] });
            closeDialog();
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateDepartmentApprover(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'approvers'] });
            closeDialog();
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deleteDepartmentApprover,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'approvers'] });
            setDeleteTarget(null);
        },
    });

    const allDepartments = data?.data || [];
    const departments = departmentsData?.data || [];
    const employees = employeesData?.data || [];

    // Filter to only show departments that have leave approvers configured,
    // or show all if filter is 'all'
    const filteredDepartments = allDepartments.filter((item) => {
        const hasLeaveApprovers = item.leave_approvers && Object.keys(item.leave_approvers).length > 0;
        if (!hasLeaveApprovers) {
            return false;
        }
        if (departmentFilter !== 'all') {
            return String(item.department_id) === departmentFilter;
        }
        return true;
    });

    function parseTiers(approversByTier) {
        if (!approversByTier || typeof approversByTier !== 'object' || Object.keys(approversByTier).length === 0) {
            return [{ tier: 1, employee_ids: [] }];
        }
        const tiers = Object.entries(approversByTier).map(([tier, approvers]) => ({
            tier: parseInt(tier),
            employee_ids: (approvers || []).map((a) => a.id),
        }));
        return tiers.length > 0 ? tiers.sort((a, b) => a.tier - b.tier) : [{ tier: 1, employee_ids: [] }];
    }

    function openCreate() {
        setEditTarget(null);
        setDepartmentId('');
        setLeaveTiers([{ tier: 1, employee_ids: [] }]);
        setShowDialog(true);
    }

    function openEdit(item) {
        setEditTarget(item);
        setDepartmentId(String(item.department_id));
        setLeaveTiers(parseTiers(item.leave_approvers));
        setShowDialog(true);
    }

    function closeDialog() {
        setShowDialog(false);
        setEditTarget(null);
        setDepartmentId('');
        setLeaveTiers([{ tier: 1, employee_ids: [] }]);
    }

    function handleSave() {
        // Build the full payload preserving other approval types from the existing config
        const payload = {
            department_id: editTarget ? editTarget.department_id : Number(departmentId),
            leave_approvers: leaveTiers,
            // Preserve existing approvers for other types when editing
            ot_approvers: editTarget ? parseTiers(editTarget.ot_approvers) : [],
            claims_approvers: editTarget ? parseTiers(editTarget.claims_approvers) : [],
            exit_permission_approvers: editTarget ? parseTiers(editTarget.exit_permission_approvers) : [],
        };

        if (editTarget) {
            updateMutation.mutate({ id: editTarget.id, data: payload });
        } else {
            createMutation.mutate(payload);
        }
    }

    const isSaving = createMutation.isPending || updateMutation.isPending;
    const hasSelectedApprovers = leaveTiers.some((t) => t.employee_ids.length > 0);

    return (
        <div>
            <PageHeader
                title="Leave Approvers"
                description="Configure who can approve leave requests per department."
                action={
                    <Button onClick={openCreate}>
                        <Users className="mr-1.5 h-4 w-4" />
                        Configure Department
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-6">
                    <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
                        <Select value={departmentFilter} onValueChange={setDepartmentFilter}>
                            <SelectTrigger className="w-full lg:w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Departments</SelectItem>
                                {departments.map((dept) => (
                                    <SelectItem key={dept.id} value={String(dept.id)}>{dept.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {isLoading ? (
                        <SkeletonTable />
                    ) : filteredDepartments.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Department</TableHead>
                                        <TableHead>Leave Approvers</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredDepartments.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">{item.department?.name || 'Unknown'}</TableCell>
                                            <TableCell>
                                                <TieredApproverBadges approversByTier={item.leave_approvers} />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="sm" onClick={() => openEdit(item)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() => setDeleteTarget(item)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Create/Edit Dialog */}
            <Dialog open={showDialog} onOpenChange={closeDialog}>
                <DialogContent className="max-w-lg max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editTarget ? `Edit Leave Approvers - ${editTarget.department?.name}` : 'Configure Leave Approvers'}
                        </DialogTitle>
                        <DialogDescription>
                            Select employees who can approve leave requests for this department. Use multiple tiers for escalation chains.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        {!editTarget && (
                            <div>
                                <Label className="mb-1 block">Department</Label>
                                <Select value={departmentId} onValueChange={setDepartmentId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={String(dept.id)}>{dept.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <TieredApproverSelector
                            tiers={leaveTiers}
                            setTiers={setLeaveTiers}
                            employees={employees}
                        />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeDialog}>Cancel</Button>
                        <Button
                            onClick={handleSave}
                            disabled={!hasSelectedApprovers || isSaving || (!editTarget && !departmentId)}
                        >
                            {isSaving && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {editTarget ? 'Update' : 'Save Configuration'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={() => setDeleteTarget(null)}
                title="Remove Leave Approvers"
                description={`Are you sure you want to remove all leave approvers for ${deleteTarget?.department?.name}? Leave requests will not be able to be approved until new approvers are assigned.`}
                confirmLabel="Remove"
                variant="destructive"
                loading={deleteMutation.isPending}
                onConfirm={() => deleteMutation.mutate(deleteTarget.id)}
            />
        </div>
    );
}
