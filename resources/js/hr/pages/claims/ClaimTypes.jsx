import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Tags, Loader2, ToggleLeft, ToggleRight } from 'lucide-react';
import {
    fetchClaimTypes,
    createClaimType,
    updateClaimType,
    deleteClaimType,
} from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
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
    description: '',
    monthly_limit: '',
    yearly_limit: '',
    requires_receipt: true,
    is_active: true,
    sort_order: 0,
};

function formatCurrency(amount) {
    if (amount === null || amount === undefined || amount === '') return '-';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

export default function ClaimTypes() {
    const queryClient = useQueryClient();
    const [formOpen, setFormOpen] = useState(false);
    const [editingType, setEditingType] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteDialog, setDeleteDialog] = useState({ open: false, type: null });
    const [errors, setErrors] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'claims', 'types'],
        queryFn: () => fetchClaimTypes({ per_page: 100 }),
    });

    const saveMutation = useMutation({
        mutationFn: (data) => editingType
            ? updateClaimType(editingType.id, data)
            : createClaimType(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'types'] });
            setFormOpen(false);
            setEditingType(null);
            setForm(EMPTY_FORM);
            setErrors({});
        },
        onError: (err) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            }
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => deleteClaimType(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'claims', 'types'] });
            setDeleteDialog({ open: false, type: null });
        },
    });

    const types = data?.data || [];

    function openCreate() {
        setEditingType(null);
        setForm(EMPTY_FORM);
        setErrors({});
        setFormOpen(true);
    }

    function openEdit(type) {
        setEditingType(type);
        setForm({
            name: type.name || '',
            code: type.code || '',
            description: type.description || '',
            monthly_limit: type.monthly_limit ?? '',
            yearly_limit: type.yearly_limit ?? '',
            requires_receipt: type.requires_receipt ?? true,
            is_active: type.is_active ?? true,
            sort_order: type.sort_order ?? 0,
        });
        setErrors({});
        setFormOpen(true);
    }

    function handleSubmit(e) {
        e.preventDefault();
        saveMutation.mutate({
            ...form,
            monthly_limit: form.monthly_limit !== '' ? parseFloat(form.monthly_limit) : null,
            yearly_limit: form.yearly_limit !== '' ? parseFloat(form.yearly_limit) : null,
            sort_order: parseInt(form.sort_order) || 0,
        });
    }

    return (
        <div>
            <PageHeader
                title="Claim Types"
                description="Configure expense claim categories and spending limits."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Add Claim Type
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : types.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Tags className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No claim types configured</p>
                            <p className="mt-1 text-xs text-zinc-400">Add your first claim type to get started.</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Monthly Limit</TableHead>
                                    <TableHead>Yearly Limit</TableHead>
                                    <TableHead>Requires Receipt</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {types.map((type) => (
                                    <TableRow key={type.id}>
                                        <TableCell className="font-medium">{type.name}</TableCell>
                                        <TableCell className="font-mono text-sm text-zinc-500">{type.code}</TableCell>
                                        <TableCell>{formatCurrency(type.monthly_limit)}</TableCell>
                                        <TableCell>{formatCurrency(type.yearly_limit)}</TableCell>
                                        <TableCell>
                                            {type.requires_receipt ? (
                                                <span className="text-xs font-medium text-emerald-600">Yes</span>
                                            ) : (
                                                <span className="text-xs text-zinc-400">No</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {type.is_active ? (
                                                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                    Active
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-500">
                                                    Inactive
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(type)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-red-600 hover:text-red-700"
                                                    onClick={() => setDeleteDialog({ open: true, type })}
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

            {/* Form Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingType ? 'Edit Claim Type' : 'Add Claim Type'}</DialogTitle>
                        <DialogDescription>
                            {editingType ? 'Update this claim type configuration.' : 'Create a new expense claim category.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Name *</label>
                                <input
                                    type="text"
                                    value={form.name}
                                    onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                />
                                {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Code *</label>
                                <input
                                    type="text"
                                    value={form.code}
                                    onChange={(e) => setForm((f) => ({ ...f, code: e.target.value.toUpperCase() }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                    maxLength={20}
                                />
                                {errors.code && <p className="mt-1 text-xs text-red-600">{errors.code[0]}</p>}
                            </div>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Description</label>
                            <textarea
                                value={form.description}
                                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                rows={2}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Monthly Limit (MYR)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.monthly_limit}
                                    onChange={(e) => setForm((f) => ({ ...f, monthly_limit: e.target.value }))}
                                    placeholder="No limit"
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Yearly Limit (MYR)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.yearly_limit}
                                    onChange={(e) => setForm((f) => ({ ...f, yearly_limit: e.target.value }))}
                                    placeholder="No limit"
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-6">
                            <label className="flex items-center gap-2 text-sm text-zinc-700 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={form.requires_receipt}
                                    onChange={(e) => setForm((f) => ({ ...f, requires_receipt: e.target.checked }))}
                                    className="rounded"
                                />
                                Requires Receipt
                            </label>
                            <label className="flex items-center gap-2 text-sm text-zinc-700 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={form.is_active}
                                    onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
                                    className="rounded"
                                />
                                Active
                            </label>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={saveMutation.isPending}>
                                {saveMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                {editingType ? 'Update' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, type: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Claim Type</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{deleteDialog.type?.name}</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, type: null })}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteDialog.type.id)}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
