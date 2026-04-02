import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Loader2,
    Tag,
    ToggleLeft,
    ToggleRight,
} from 'lucide-react';
import { fetchLeaveTypes, createLeaveType, updateLeaveType, deleteLeaveType } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Input } from '../../components/ui/input';
import { Checkbox } from '../../components/ui/checkbox';
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

const EMPTY_FORM = {
    name: '',
    code: '',
    is_paid: true,
    is_attachment_required: false,
    gender_restriction: '',
    color: '#3b82f6',
    sort_order: 0,
    is_active: true,
    description: '',
};

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-16 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-12 animate-pulse rounded bg-zinc-200" />
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
            <Tag className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">No leave types yet</h3>
            <p className="mt-1 text-sm text-zinc-500">Create your first leave type to get started.</p>
        </div>
    );
}

export default function LeaveTypes() {
    const queryClient = useQueryClient();
    const [formDialog, setFormDialog] = useState({ open: false, mode: 'create', data: null });
    const [form, setForm] = useState(EMPTY_FORM);
    const [formErrors, setFormErrors] = useState({});
    const [deleteDialog, setDeleteDialog] = useState({ open: false, leaveType: null });

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'leave', 'types'],
        queryFn: () => fetchLeaveTypes({ per_page: 100 }),
    });

    const handleMutationError = (err) => {
        const errors = err?.response?.data?.errors || {};
        setFormErrors(errors);
    };

    const createMutation = useMutation({
        mutationFn: (data) => createLeaveType(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'types'] });
            closeFormDialog();
        },
        onError: handleMutationError,
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateLeaveType(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'types'] });
            closeFormDialog();
        },
        onError: handleMutationError,
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => deleteLeaveType(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'types'] });
            setDeleteDialog({ open: false, leaveType: null });
        },
        onError: (error) => {
            alert(error?.response?.data?.message || 'Failed to delete leave type.');
        },
    });

    const toggleActiveMutation = useMutation({
        mutationFn: ({ id, data }) => updateLeaveType(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'leave', 'types'] });
        },
        onError: (error) => {
            alert(error?.response?.data?.message || 'Failed to update leave type status.');
        },
    });

    const leaveTypes = data?.data || [];

    function openCreateDialog() {
        setForm(EMPTY_FORM);
        setFormErrors({});
        setFormDialog({ open: true, mode: 'create', data: null });
    }

    function openEditDialog(lt) {
        setForm({
            name: lt.name || '',
            code: lt.code || '',
            is_paid: lt.is_paid ?? true,
            is_attachment_required: lt.is_attachment_required ?? false,
            gender_restriction: lt.gender_restriction || '',
            color: lt.color || '#3b82f6',
            sort_order: lt.sort_order ?? 0,
            is_active: lt.is_active ?? true,
            description: lt.description || '',
        });
        setFormErrors({});
        setFormDialog({ open: true, mode: 'edit', data: lt });
    }

    function closeFormDialog() {
        setFormDialog({ open: false, mode: 'create', data: null });
        setForm(EMPTY_FORM);
    }

    function handleSubmit() {
        setFormErrors({});
        const payload = {
            ...form,
            gender_restriction: form.gender_restriction || null,
        };
        if (formDialog.mode === 'create') {
            createMutation.mutate(payload);
        } else {
            updateMutation.mutate({ id: formDialog.data.id, data: payload });
        }
    }

    function handleDelete() {
        deleteMutation.mutate(deleteDialog.leaveType.id);
    }

    function handleToggleActive(lt) {
        toggleActiveMutation.mutate({
            id: lt.id,
            data: {
                name: lt.name,
                code: lt.code,
                is_paid: lt.is_paid,
                is_attachment_required: lt.is_attachment_required,
                gender_restriction: lt.gender_restriction || null,
                color: lt.color,
                sort_order: lt.sort_order,
                description: lt.description || '',
                is_active: !lt.is_active,
            },
        });
    }

    const isSaving = createMutation.isPending || updateMutation.isPending;

    return (
        <div>
            <PageHeader
                title="Leave Types"
                description="Configure the types of leave available in your organization."
                action={
                    <Button onClick={openCreateDialog}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Add Leave Type
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-6">
                    {isLoading ? (
                        <SkeletonTable />
                    ) : leaveTypes.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Code</TableHead>
                                        <TableHead>Paid</TableHead>
                                        <TableHead>Attachment</TableHead>
                                        <TableHead>Gender</TableHead>
                                        <TableHead>System</TableHead>
                                        <TableHead>Active</TableHead>
                                        <TableHead>Color</TableHead>
                                        <TableHead>Sort</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {leaveTypes.map((lt) => (
                                        <TableRow key={lt.id}>
                                            <TableCell className="font-medium">{lt.name}</TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">{lt.code || '-'}</Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={lt.is_paid ? 'success' : 'outline'}>
                                                    {lt.is_paid ? 'Paid' : 'Unpaid'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-500">
                                                {lt.is_attachment_required ? 'Required' : '-'}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-500">
                                                {lt.gender_restriction ? (
                                                    <Badge variant="outline">{lt.gender_restriction}</Badge>
                                                ) : 'All'}
                                            </TableCell>
                                            <TableCell>
                                                {lt.is_system ? (
                                                    <Badge variant="default">System</Badge>
                                                ) : (
                                                    <Badge variant="outline">Custom</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <button
                                                    onClick={() => handleToggleActive(lt)}
                                                    className="flex items-center"
                                                    disabled={toggleActiveMutation.isPending}
                                                >
                                                    {lt.is_active ? (
                                                        <ToggleRight className="h-6 w-6 text-emerald-600" />
                                                    ) : (
                                                        <ToggleLeft className="h-6 w-6 text-zinc-400" />
                                                    )}
                                                </button>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div
                                                        className="h-5 w-5 rounded-full border border-zinc-200"
                                                        style={{ backgroundColor: lt.color || '#e5e7eb' }}
                                                    />
                                                    <span className="text-xs text-zinc-400">{lt.color || '-'}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-500">{lt.sort_order ?? '-'}</TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="sm" onClick={() => openEditDialog(lt)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    {!lt.is_system && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-red-600 hover:text-red-700"
                                                            onClick={() => setDeleteDialog({ open: true, leaveType: lt })}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
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

            <Dialog open={formDialog.open} onOpenChange={closeFormDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {formDialog.mode === 'create' ? 'Add Leave Type' : 'Edit Leave Type'}
                        </DialogTitle>
                        <DialogDescription>
                            {formDialog.mode === 'create'
                                ? 'Create a new leave type for your organization.'
                                : 'Update the leave type configuration.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        {Object.keys(formErrors).length > 0 && (
                            <div className="rounded-lg border border-red-200 bg-red-50 p-3">
                                <p className="text-sm font-medium text-red-800">Please fix the following errors:</p>
                                <ul className="mt-1 list-inside list-disc text-sm text-red-600">
                                    {Object.values(formErrors).flat().map((msg, i) => (
                                        <li key={i}>{msg}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Name</label>
                                <Input
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    placeholder="e.g. Annual Leave"
                                />
                                {formErrors.name && <p className="mt-1 text-xs text-red-500">{formErrors.name[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Code</label>
                                <Input
                                    value={form.code}
                                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                                    placeholder="e.g. AL"
                                />
                                {formErrors.code && <p className="mt-1 text-xs text-red-500">{formErrors.code[0]}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Color</label>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="color"
                                        value={form.color}
                                        onChange={(e) => setForm({ ...form, color: e.target.value })}
                                        className="h-9 w-9 cursor-pointer rounded border border-zinc-300"
                                    />
                                    <Input
                                        value={form.color}
                                        onChange={(e) => setForm({ ...form, color: e.target.value })}
                                        placeholder="#3b82f6"
                                        className="flex-1"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Sort Order</label>
                                <Input
                                    type="number"
                                    value={form.sort_order}
                                    onChange={(e) => setForm({ ...form, sort_order: Number(e.target.value) })}
                                />
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Gender Specific</label>
                            <div className="flex gap-4">
                                {['', 'male', 'female'].map((g) => (
                                    <label key={g} className="flex items-center gap-1.5 text-sm">
                                        <input
                                            type="radio"
                                            name="gender"
                                            checked={form.gender_restriction === g}
                                            onChange={() => setForm({ ...form, gender_restriction: g })}
                                            className="text-zinc-900"
                                        />
                                        {g === '' ? 'All' : g.charAt(0).toUpperCase() + g.slice(1)}
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div className="flex flex-col gap-3">
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    checked={form.is_paid}
                                    onCheckedChange={(v) => setForm({ ...form, is_paid: v })}
                                />
                                <span className="text-sm">Paid leave</span>
                            </label>
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    checked={form.is_attachment_required}
                                    onCheckedChange={(v) => setForm({ ...form, is_attachment_required: v })}
                                />
                                <span className="text-sm">Requires attachment</span>
                            </label>
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    checked={form.is_active}
                                    onCheckedChange={(v) => setForm({ ...form, is_active: v })}
                                />
                                <span className="text-sm">Active</span>
                            </label>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Description</label>
                            <textarea
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                                placeholder="Optional description..."
                                className="w-full rounded-lg border border-zinc-300 p-3 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                rows={2}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeFormDialog}>Cancel</Button>
                        <Button onClick={handleSubmit} disabled={!form.name || !form.code || isSaving}>
                            {isSaving && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {formDialog.mode === 'create' ? 'Create' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, leaveType: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Leave Type</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete &ldquo;{deleteDialog.leaveType?.name}&rdquo;? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, leaveType: null })}>
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
