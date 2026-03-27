import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Lock,
    Loader2,
    Settings2,
} from 'lucide-react';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
} from '../../components/ui/card';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';
import PageHeader from '../../components/PageHeader';
import { cn } from '../../lib/utils';
import {
    fetchSalaryComponents,
    createSalaryComponent,
    updateSalaryComponent,
    deleteSalaryComponent,
} from '../../lib/api';

const EMPTY_FORM = {
    name: '',
    code: '',
    type: 'earning',
    category: 'fixed_allowance',
    is_taxable: true,
    is_epf_applicable: true,
    is_socso_applicable: true,
    is_eis_applicable: true,
    sort_order: 0,
};

const CATEGORY_LABELS = {
    basic: 'Basic',
    fixed_allowance: 'Fixed Allowance',
    variable_allowance: 'Variable Allowance',
    fixed_deduction: 'Fixed Deduction',
    variable_deduction: 'Variable Deduction',
};

function ComponentTypeBadge({ type }) {
    return (
        <span className={cn(
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
            type === 'earning' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700',
        )}>
            {type === 'earning' ? 'Earning' : 'Deduction'}
        </span>
    );
}

function CheckIcon({ checked }) {
    return (
        <span className={cn('text-xs font-medium', checked ? 'text-emerald-600' : 'text-zinc-300')}>
            {checked ? 'Yes' : 'No'}
        </span>
    );
}

export default function SalaryComponents() {
    const queryClient = useQueryClient();
    const [formDialog, setFormDialog] = useState(false);
    const [editingComponent, setEditingComponent] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteDialog, setDeleteDialog] = useState(null);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'components'],
        queryFn: () => fetchSalaryComponents(),
    });

    const components = data?.data || [];

    const createMutation = useMutation({
        mutationFn: createSalaryComponent,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'components'] });
            setFormDialog(false);
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateSalaryComponent(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'components'] });
            setFormDialog(false);
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deleteSalaryComponent,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll', 'components'] });
            setDeleteDialog(null);
        },
    });

    function openCreate() {
        setEditingComponent(null);
        setForm(EMPTY_FORM);
        setFormDialog(true);
    }

    function openEdit(component) {
        setEditingComponent(component);
        setForm({
            name: component.name,
            code: component.code,
            type: component.type,
            category: component.category,
            is_taxable: component.is_taxable,
            is_epf_applicable: component.is_epf_applicable,
            is_socso_applicable: component.is_socso_applicable,
            is_eis_applicable: component.is_eis_applicable,
            sort_order: component.sort_order,
        });
        setFormDialog(true);
    }

    function handleSubmit() {
        if (editingComponent) {
            updateMutation.mutate({ id: editingComponent.id, data: form });
        } else {
            createMutation.mutate(form);
        }
    }

    const isSaving = createMutation.isPending || updateMutation.isPending;

    return (
        <div className="space-y-6">
            <PageHeader
                title="Salary Components"
                description="Manage earnings and deduction components for payroll"
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Component
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="space-y-3 p-6">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-2">
                                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-16 animate-pulse rounded bg-zinc-200" />
                                    <div className="flex-1" />
                                    <div className="h-6 w-20 animate-pulse rounded-full bg-zinc-200" />
                                </div>
                            ))}
                        </div>
                    ) : components.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <Settings2 className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No salary components</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Taxable</TableHead>
                                    <TableHead>EPF</TableHead>
                                    <TableHead>SOCSO</TableHead>
                                    <TableHead>EIS</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {components.map((comp) => (
                                    <TableRow key={comp.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-1.5">
                                                {comp.is_system && (
                                                    <Lock className="h-3.5 w-3.5 text-zinc-400" title="System component" />
                                                )}
                                                <span className="font-medium text-zinc-900">{comp.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <code className="rounded bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-700">{comp.code}</code>
                                        </TableCell>
                                        <TableCell>
                                            <ComponentTypeBadge type={comp.type} />
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {CATEGORY_LABELS[comp.category] || comp.category}
                                        </TableCell>
                                        <TableCell><CheckIcon checked={comp.is_taxable} /></TableCell>
                                        <TableCell><CheckIcon checked={comp.is_epf_applicable} /></TableCell>
                                        <TableCell><CheckIcon checked={comp.is_socso_applicable} /></TableCell>
                                        <TableCell><CheckIcon checked={comp.is_eis_applicable} /></TableCell>
                                        <TableCell>
                                            <span className={cn(
                                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                                comp.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-100 text-zinc-500',
                                            )}>
                                                {comp.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {!comp.is_system ? (
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="sm" onClick={() => openEdit(comp)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() => setDeleteDialog(comp)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-zinc-400">System</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Form Dialog */}
            <Dialog open={formDialog} onOpenChange={setFormDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingComponent ? 'Edit Component' : 'Add Salary Component'}</DialogTitle>
                        <DialogDescription>
                            Configure the salary component details and statutory applicability.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2">
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Name</label>
                            <input
                                type="text"
                                value={form.name}
                                onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                                placeholder="e.g. Housing Allowance"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Code</label>
                            <input
                                type="text"
                                value={form.code}
                                onChange={(e) => setForm((p) => ({ ...p, code: e.target.value.toUpperCase() }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none font-mono"
                                placeholder="e.g. HOUSING"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Sort Order</label>
                            <input
                                type="number"
                                value={form.sort_order}
                                onChange={(e) => setForm((p) => ({ ...p, sort_order: parseInt(e.target.value) || 0 }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Type</label>
                            <select
                                value={form.type}
                                onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                <option value="earning">Earning</option>
                                <option value="deduction">Deduction</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Category</label>
                            <select
                                value={form.category}
                                onChange={(e) => setForm((p) => ({ ...p, category: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                {Object.entries(CATEGORY_LABELS).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                        </div>

                        <div className="col-span-2">
                            <p className="mb-2 text-sm font-medium text-zinc-700">Statutory Applicability</p>
                            <div className="grid grid-cols-2 gap-2">
                                {[
                                    { key: 'is_taxable', label: 'Taxable' },
                                    { key: 'is_epf_applicable', label: 'EPF' },
                                    { key: 'is_socso_applicable', label: 'SOCSO' },
                                    { key: 'is_eis_applicable', label: 'EIS' },
                                ].map(({ key, label }) => (
                                    <label key={key} className="flex items-center gap-2 text-sm text-zinc-700">
                                        <input
                                            type="checkbox"
                                            checked={form[key]}
                                            onChange={(e) => setForm((p) => ({ ...p, [key]: e.target.checked }))}
                                            className="rounded"
                                        />
                                        {label}
                                    </label>
                                ))}
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setFormDialog(false)}>Cancel</Button>
                        <Button onClick={handleSubmit} disabled={isSaving || !form.name || !form.code}>
                            {isSaving && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {editingComponent ? 'Save Changes' : 'Create Component'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm Dialog */}
            <Dialog open={!!deleteDialog} onOpenChange={() => setDeleteDialog(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Component</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{deleteDialog?.name}</strong>? This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog(null)}>Cancel</Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteDialog.id)}
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
