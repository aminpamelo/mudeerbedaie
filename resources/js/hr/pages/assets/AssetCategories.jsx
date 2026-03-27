import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Tag, Loader2 } from 'lucide-react';
import {
    fetchAssetCategories,
    createAssetCategory,
    updateAssetCategory,
    deleteAssetCategory,
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
    requires_serial_number: false,
    is_active: true,
    sort_order: 0,
};

export default function AssetCategories() {
    const queryClient = useQueryClient();
    const [formOpen, setFormOpen] = useState(false);
    const [editingCategory, setEditingCategory] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteDialog, setDeleteDialog] = useState({ open: false, category: null });
    const [errors, setErrors] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'assets', 'categories'],
        queryFn: () => fetchAssetCategories({ per_page: 100 }),
    });

    const saveMutation = useMutation({
        mutationFn: (data) => editingCategory
            ? updateAssetCategory(editingCategory.id, data)
            : createAssetCategory(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'assets', 'categories'] });
            setFormOpen(false);
            setEditingCategory(null);
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
        mutationFn: (id) => deleteAssetCategory(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'assets', 'categories'] });
            setDeleteDialog({ open: false, category: null });
        },
    });

    const categories = data?.data || [];

    function openCreate() {
        setEditingCategory(null);
        setForm(EMPTY_FORM);
        setErrors({});
        setFormOpen(true);
    }

    function openEdit(category) {
        setEditingCategory(category);
        setForm({
            name: category.name || '',
            code: category.code || '',
            description: category.description || '',
            requires_serial_number: category.requires_serial_number ?? false,
            is_active: category.is_active ?? true,
            sort_order: category.sort_order ?? 0,
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
                title="Asset Categories"
                description="Manage asset classification and tracking requirements."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Add Category
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : categories.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Tag className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No categories defined</p>
                            <p className="mt-1 text-xs text-zinc-400">Add asset categories to organise your inventory.</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Requires Serial No.</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {categories.map((category) => (
                                    <TableRow key={category.id}>
                                        <TableCell>
                                            <p className="font-medium">{category.name}</p>
                                            {category.description && (
                                                <p className="text-xs text-zinc-400">{category.description}</p>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-mono text-sm text-zinc-500">{category.code}</TableCell>
                                        <TableCell>
                                            {category.requires_serial_number ? (
                                                <span className="text-xs font-medium text-emerald-600">Yes</span>
                                            ) : (
                                                <span className="text-xs text-zinc-400">No</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {category.is_active ? (
                                                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                            ) : (
                                                <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-500">Inactive</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(category)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-red-600 hover:text-red-700"
                                                    onClick={() => setDeleteDialog({ open: true, category })}
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
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingCategory ? 'Edit Category' : 'Add Asset Category'}</DialogTitle>
                        <DialogDescription>
                            {editingCategory ? 'Update category details.' : 'Create a new asset category.'}
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
                        <div className="flex items-center gap-6">
                            <label className="flex items-center gap-2 text-sm text-zinc-700 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={form.requires_serial_number}
                                    onChange={(e) => setForm((f) => ({ ...f, requires_serial_number: e.target.checked }))}
                                    className="rounded"
                                />
                                Requires Serial Number
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
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>Cancel</Button>
                            <Button type="submit" disabled={saveMutation.isPending}>
                                {saveMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                {editingCategory ? 'Update' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, category: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Category</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{deleteDialog.category?.name}</strong>?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, category: null })}>Cancel</Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteDialog.category.id)}
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
