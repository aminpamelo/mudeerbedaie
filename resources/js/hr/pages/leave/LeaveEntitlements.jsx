import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Loader2,
    ChevronDown,
    ChevronRight,
    RefreshCw,
    BookOpen,
} from 'lucide-react';
import {
    fetchLeaveEntitlements,
    createLeaveEntitlement,
    updateLeaveEntitlement,
    deleteLeaveEntitlement,
    recalculateEntitlements,
    fetchLeaveTypes,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Input } from '../../components/ui/input';
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

const EMPLOYMENT_TYPES = [
    { value: 'full_time', label: 'Full-time' },
    { value: 'part_time', label: 'Part-time' },
    { value: 'contract', label: 'Contract' },
    { value: 'intern', label: 'Intern' },
];

const EMPTY_FORM = {
    leave_type_id: '',
    employment_type: 'full_time',
    min_service_months: 0,
    max_service_months: '',
    days_per_year: 0,
    is_prorated: true,
    carry_forward_max: 0,
};

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1" />
                    <div className="h-8 w-20 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <BookOpen className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">No entitlement rules yet</h3>
            <p className="mt-1 text-sm text-zinc-500">Add entitlement rules to define how leave is allocated.</p>
        </div>
    );
}

export default function LeaveEntitlements() {
    const queryClient = useQueryClient();
    const [expandedTypes, setExpandedTypes] = useState({});
    const [formDialog, setFormDialog] = useState({ open: false, mode: 'create', data: null });
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteDialog, setDeleteDialog] = useState({ open: false, entitlement: null });
    const [recalculating, setRecalculating] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'leave', 'entitlements'],
        queryFn: () => fetchLeaveEntitlements({ per_page: 200 }),
    });

    const { data: leaveTypesData } = useQuery({
        queryKey: ['hr', 'leave', 'types', 'list'],
        queryFn: () => fetchLeaveTypes({ per_page: 100 }),
    });

    const createMutation = useMutation({
        mutationFn: (data) => createLeaveEntitlement(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'entitlements'] });
            closeFormDialog();
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateLeaveEntitlement(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'entitlements'] });
            closeFormDialog();
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => deleteLeaveEntitlement(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'entitlements'] });
            setDeleteDialog({ open: false, entitlement: null });
        },
    });

    const entitlements = data?.data || [];
    const leaveTypes = leaveTypesData?.data || [];

    // Seed from ALL leave types so types with no rules still appear
    const groupedEntitlements = {};
    leaveTypes.forEach((lt) => {
        groupedEntitlements[lt.id] = { leaveType: lt, rules: [] };
    });
    entitlements.forEach((ent) => {
        const typeId = ent.leave_type_id;
        if (!groupedEntitlements[typeId]) {
            groupedEntitlements[typeId] = {
                leaveType: ent.leave_type || { name: 'Unknown', id: typeId },
                rules: [],
            };
        }
        groupedEntitlements[typeId].rules.push(ent);
    });

    function toggleType(typeId) {
        setExpandedTypes((prev) => ({ ...prev, [typeId]: !prev[typeId] }));
    }

    function openCreateDialog(leaveTypeId) {
        setForm({ ...EMPTY_FORM, leave_type_id: leaveTypeId ? String(leaveTypeId) : '' });
        setFormDialog({ open: true, mode: 'create', data: null });
    }

    function openEditDialog(ent) {
        setForm({
            leave_type_id: String(ent.leave_type_id),
            employment_type: ent.employment_type || 'full-time',
            min_service_months: ent.min_service_months ?? 0,
            max_service_months: ent.max_service_months ?? '',
            days_per_year: ent.days_per_year ?? 0,
            is_prorated: ent.is_prorated ?? true,
            carry_forward_max: ent.carry_forward_max ?? 0,
        });
        setFormDialog({ open: true, mode: 'edit', data: ent });
    }

    function closeFormDialog() {
        setFormDialog({ open: false, mode: 'create', data: null });
        setForm(EMPTY_FORM);
    }

    function handleSubmit() {
        const payload = {
            ...form,
            leave_type_id: Number(form.leave_type_id),
            min_service_months: Number(form.min_service_months),
            max_service_months: form.max_service_months !== '' ? Number(form.max_service_months) : null,
            days_per_year: Number(form.days_per_year),
            carry_forward_max: Number(form.carry_forward_max),
        };
        if (formDialog.mode === 'create') {
            createMutation.mutate(payload);
        } else {
            updateMutation.mutate({ id: formDialog.data.id, data: payload });
        }
    }

    function handleDelete() {
        deleteMutation.mutate(deleteDialog.entitlement.id);
    }

    async function handleRecalculate() {
        setRecalculating(true);
        try {
            await recalculateEntitlements();
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave'] });
        } finally {
            setRecalculating(false);
        }
    }

    const isSaving = createMutation.isPending || updateMutation.isPending;

    return (
        <div>
            <PageHeader
                title="Leave Entitlements"
                description="Define entitlement rules per leave type, employment type, and service period."
                action={
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={handleRecalculate} disabled={recalculating}>
                            {recalculating ? (
                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                            ) : (
                                <RefreshCw className="mr-1.5 h-4 w-4" />
                            )}
                            Recalculate All Balances
                        </Button>
                        <Button onClick={() => openCreateDialog(null)}>
                            <Plus className="mr-1.5 h-4 w-4" />
                            Add Rule
                        </Button>
                    </div>
                }
            />

            {isLoading ? (
                <Card>
                    <CardContent className="p-6">
                        <SkeletonTable />
                    </CardContent>
                </Card>
            ) : Object.keys(groupedEntitlements).length === 0 ? (
                <Card>
                    <CardContent className="p-6">
                        <EmptyState />
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-3">
                    {Object.entries(groupedEntitlements).map(([typeId, group]) => {
                        const isExpanded = expandedTypes[typeId] !== false;
                        return (
                            <Card key={typeId}>
                                <CardContent className="p-0">
                                    <div
                                        onClick={() => toggleType(typeId)}
                                        className="flex w-full cursor-pointer items-center justify-between p-4 text-left hover:bg-zinc-50"
                                    >
                                        <div className="flex items-center gap-3">
                                            {isExpanded ? (
                                                <ChevronDown className="h-4 w-4 text-zinc-400" />
                                            ) : (
                                                <ChevronRight className="h-4 w-4 text-zinc-400" />
                                            )}
                                            <div
                                                className="h-3 w-3 rounded-full"
                                                style={{ backgroundColor: group.leaveType.color || '#e5e7eb' }}
                                            />
                                            <span className="font-semibold text-zinc-900">{group.leaveType.name}</span>
                                            <Badge variant="secondary">{group.rules.length} rule{group.rules.length !== 1 ? 's' : ''}</Badge>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                openCreateDialog(typeId);
                                            }}
                                        >
                                            <Plus className="mr-1 h-3.5 w-3.5" />
                                            Add Rule
                                        </Button>
                                    </div>
                                    {isExpanded && (
                                        <div className="border-t border-zinc-100 px-4 pb-4">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>Employment Type</TableHead>
                                                        <TableHead>Service Range</TableHead>
                                                        <TableHead>Days/Year</TableHead>
                                                        <TableHead>Pro-rated</TableHead>
                                                        <TableHead>Carry Forward</TableHead>
                                                        <TableHead className="text-right">Actions</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {group.rules.map((rule) => (
                                                        <TableRow key={rule.id}>
                                                            <TableCell>
                                                                <Badge variant="outline">
                                                                    {EMPLOYMENT_TYPES.find((t) => t.value === rule.employment_type)?.label || rule.employment_type}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="text-sm text-zinc-600">
                                                                {rule.min_service_months ?? 0}
                                                                {rule.max_service_months ? ` - ${rule.max_service_months}` : '+'} months
                                                            </TableCell>
                                                            <TableCell className="font-medium">{rule.days_per_year} days</TableCell>
                                                            <TableCell>
                                                                <Badge variant={rule.is_prorated ? 'success' : 'secondary'}>
                                                                    {rule.is_prorated ? 'Yes' : 'No'}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="text-sm text-zinc-600">
                                                                {rule.carry_forward_max ?? 0} days
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                <div className="flex items-center justify-end gap-1">
                                                                    <Button variant="ghost" size="sm" onClick={() => openEditDialog(rule)}>
                                                                        <Pencil className="h-4 w-4" />
                                                                    </Button>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="text-red-600 hover:text-red-700"
                                                                        onClick={() => setDeleteDialog({ open: true, entitlement: rule })}
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
                        );
                    })}
                </div>
            )}

            <Dialog open={formDialog.open} onOpenChange={closeFormDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {formDialog.mode === 'create' ? 'Add Entitlement Rule' : 'Edit Entitlement Rule'}
                        </DialogTitle>
                        <DialogDescription>
                            Define the leave entitlement for a specific employment type and service period.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Leave Type</label>
                            <Select
                                value={form.leave_type_id}
                                onValueChange={(v) => setForm({ ...form, leave_type_id: v })}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select leave type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {leaveTypes.map((lt) => (
                                        <SelectItem key={lt.id} value={String(lt.id)}>{lt.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Employment Type</label>
                            <Select
                                value={form.employment_type}
                                onValueChange={(v) => setForm({ ...form, employment_type: v })}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {EMPLOYMENT_TYPES.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Min Service (months)</label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={form.min_service_months}
                                    onChange={(e) => setForm({ ...form, min_service_months: e.target.value })}
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Max Service (months)</label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={form.max_service_months}
                                    onChange={(e) => setForm({ ...form, max_service_months: e.target.value })}
                                    placeholder="No limit"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Days Per Year</label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    value={form.days_per_year}
                                    onChange={(e) => setForm({ ...form, days_per_year: e.target.value })}
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Carry Forward Days</label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={form.carry_forward_max}
                                    onChange={(e) => setForm({ ...form, carry_forward_max: e.target.value })}
                                />
                            </div>
                        </div>
                        <label className="flex items-center gap-2">
                            <Checkbox
                                checked={form.is_prorated}
                                onCheckedChange={(v) => setForm({ ...form, is_prorated: v })}
                            />
                            <span className="text-sm">Pro-rate for partial years</span>
                        </label>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeFormDialog}>Cancel</Button>
                        <Button onClick={handleSubmit} disabled={!form.leave_type_id || isSaving}>
                            {isSaving && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {formDialog.mode === 'create' ? 'Create' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, entitlement: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Entitlement Rule</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this entitlement rule? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, entitlement: null })}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete} disabled={deleteMutation.isPending}>
                            {deleteMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
