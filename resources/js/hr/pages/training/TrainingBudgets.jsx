import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Loader2, Wallet } from 'lucide-react';
import {
    fetchTrainingBudgets,
    createTrainingBudget,
    updateTrainingBudget,
} from '../../lib/api';
import { cn } from '../../lib/utils';
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
    department_id: '',
    year: new Date().getFullYear(),
    allocated_amount: '',
};

function formatCurrency(amount) {
    if (amount === null || amount === undefined || amount === '') return '-';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

export default function TrainingBudgets() {
    const queryClient = useQueryClient();
    const currentYear = new Date().getFullYear();
    const [filterYear, setFilterYear] = useState(currentYear);
    const [formOpen, setFormOpen] = useState(false);
    const [editingBudget, setEditingBudget] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [errors, setErrors] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'training', 'budgets', filterYear],
        queryFn: () => fetchTrainingBudgets({ year: filterYear }),
    });

    const saveMutation = useMutation({
        mutationFn: (formData) =>
            editingBudget
                ? updateTrainingBudget(editingBudget.id, formData)
                : createTrainingBudget(formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'training', 'budgets'] });
            setFormOpen(false);
            setEditingBudget(null);
            setForm(EMPTY_FORM);
            setErrors({});
        },
        onError: (err) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            }
        },
    });

    const budgets = data?.data || [];
    const departments = data?.departments || [];

    function openCreate() {
        setEditingBudget(null);
        setForm({ ...EMPTY_FORM, year: filterYear });
        setErrors({});
        setFormOpen(true);
    }

    function openEdit(budget) {
        setEditingBudget(budget);
        setForm({
            department_id: budget.department_id || '',
            year: budget.year || filterYear,
            allocated_amount: budget.allocated_amount ?? '',
        });
        setErrors({});
        setFormOpen(true);
    }

    function handleSubmit(e) {
        e.preventDefault();
        saveMutation.mutate({
            ...form,
            department_id: parseInt(form.department_id),
            year: parseInt(form.year),
            allocated_amount: parseFloat(form.allocated_amount) || 0,
        });
    }

    return (
        <div>
            <PageHeader
                title="Training Budgets"
                description="Allocate and track training budgets by department."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Add Budget
                    </Button>
                }
            />

            {/* Year Filter */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex items-center gap-3">
                        <label className="text-sm font-medium text-zinc-700">Year</label>
                        <select
                            value={filterYear}
                            onChange={(e) => setFilterYear(parseInt(e.target.value))}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            {[currentYear - 1, currentYear, currentYear + 1].map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : budgets.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Wallet className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No training budgets for {filterYear}</p>
                            <p className="mt-1 text-xs text-zinc-400">Add budget allocations for departments to start tracking.</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Allocated (MYR)</TableHead>
                                    <TableHead>Spent (MYR)</TableHead>
                                    <TableHead>Utilization</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {budgets.map((budget) => {
                                    const allocated = parseFloat(budget.allocated_amount) || 0;
                                    const spent = parseFloat(budget.spent_amount) || 0;
                                    const utilization = allocated > 0 ? Math.min((spent / allocated) * 100, 100) : 0;
                                    const barColor = utilization > 90 ? 'bg-red-500' : utilization > 70 ? 'bg-amber-500' : 'bg-emerald-500';
                                    const textColor = utilization > 90 ? 'text-red-600' : utilization > 70 ? 'text-amber-600' : 'text-emerald-600';

                                    return (
                                        <TableRow key={budget.id}>
                                            <TableCell className="font-medium">
                                                {budget.department?.name || 'Unknown'}
                                            </TableCell>
                                            <TableCell>{formatCurrency(allocated)}</TableCell>
                                            <TableCell>{formatCurrency(spent)}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="h-2 w-24 rounded-full bg-zinc-100">
                                                        <div
                                                            className={cn('h-2 rounded-full transition-all', barColor)}
                                                            style={{ width: `${utilization}%` }}
                                                        />
                                                    </div>
                                                    <span className={cn('text-sm font-medium', textColor)}>
                                                        {utilization.toFixed(0)}%
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(budget)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Form Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingBudget ? 'Edit Training Budget' : 'Add Training Budget'}</DialogTitle>
                        <DialogDescription>
                            {editingBudget
                                ? 'Update the budget allocation for this department.'
                                : 'Allocate a training budget for a department.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Department *</label>
                            <select
                                value={form.department_id}
                                onChange={(e) => setForm((f) => ({ ...f, department_id: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                required
                                disabled={!!editingBudget}
                            >
                                <option value="">Select Department</option>
                                {departments.map((dept) => (
                                    <option key={dept.id} value={dept.id}>{dept.name}</option>
                                ))}
                            </select>
                            {errors.department_id && <p className="mt-1 text-xs text-red-600">{errors.department_id[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Year *</label>
                            <select
                                value={form.year}
                                onChange={(e) => setForm((f) => ({ ...f, year: parseInt(e.target.value) }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                required
                                disabled={!!editingBudget}
                            >
                                {[currentYear - 1, currentYear, currentYear + 1].map((y) => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Allocated Amount (MYR) *</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.allocated_amount}
                                onChange={(e) => setForm((f) => ({ ...f, allocated_amount: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                required
                            />
                            {errors.allocated_amount && <p className="mt-1 text-xs text-red-600">{errors.allocated_amount[0]}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={saveMutation.isPending}>
                                {saveMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                {editingBudget ? 'Update' : 'Create'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
