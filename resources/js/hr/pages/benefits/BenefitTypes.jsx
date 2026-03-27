import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Shield, Loader2 } from 'lucide-react';
import {
    fetchBenefitTypes,
    createBenefitType,
    updateBenefitType,
    deleteBenefitType,
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
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';

const CATEGORIES = [
    { value: 'insurance', label: 'Insurance' },
    { value: 'allowance', label: 'Allowance' },
    { value: 'subsidy', label: 'Subsidy' },
    { value: 'other', label: 'Other' },
];

const CATEGORY_BADGE = {
    insurance: 'bg-blue-100 text-blue-700',
    allowance: 'bg-emerald-100 text-emerald-700',
    subsidy: 'bg-purple-100 text-purple-700',
    other: 'bg-zinc-100 text-zinc-600',
};

const EMPTY_FORM = {
    name: '',
    code: '',
    description: '',
    category: 'other',
    is_active: true,
    sort_order: 0,
};

export default function BenefitTypes() {
    const queryClient = useQueryClient();
    const [formOpen, setFormOpen] = useState(false);
    const [editingType, setEditingType] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteDialog, setDeleteDialog] = useState({ open: false, type: null });
    const [errors, setErrors] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'benefits', 'types'],
        queryFn: () => fetchBenefitTypes({ per_page: 100 }),
    });

    const saveMutation = useMutation({
        mutationFn: (data) => editingType
            ? updateBenefitType(editingType.id, data)
            : createBenefitType(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'benefits', 'types'] });
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
        mutationFn: (id) => deleteBenefitType(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'benefits', 'types'] });
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
            category: type.category || 'other',
            is_active: type.is_active ?? true,
            sort_order: type.sort_order ?? 0,
        });
        setErrors({});
        setFormOpen(true);
    }

    function handleSubmit(e) {
        e.preventDefault();
        saveMutation.mutate({ ...form, sort_order: parseInt(form.sort_order) || 0 });
    }

    return (
        <div>
            <PageHeader
                title="Benefit Types"
                description="Configure employee benefit categories."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Add Benefit Type
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
                            <Shield className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No benefit types configured</p>
                            <p className="mt-1 text-xs text-zinc-400">Add your first benefit type.</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {types.map((type) => (
                                    <TableRow key={type.id}>
                                        <TableCell>
                                            <p className="font-medium">{type.name}</p>
                                            {type.description && (
                                                <p className="text-xs text-zinc-400">{type.description}</p>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-mono text-sm text-zinc-500">{type.code}</TableCell>
                                        <TableCell>
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${CATEGORY_BADGE[type.category] || 'bg-zinc-100 text-zinc-600'}`}>
                                                {type.category}
                                            </span>
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
                        <DialogTitle>{editingType ? 'Edit Benefit Type' : 'Add Benefit Type'}</DialogTitle>
                        <DialogDescription>
                            {editingType ? 'Update benefit type details.' : 'Create a new benefit category.'}
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
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Category *</label>
                            <Select value={form.category} onValueChange={(v) => setForm((f) => ({ ...f, category: v }))}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {CATEGORIES.map((c) => (
                                        <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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
                        <label className="flex items-center gap-2 text-sm text-zinc-700 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={form.is_active}
                                onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
                                className="rounded"
                            />
                            Active
                        </label>
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
                        <DialogTitle>Delete Benefit Type</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{deleteDialog.type?.name}</strong>?
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
