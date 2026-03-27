import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Shield, Loader2, ChevronLeft, ChevronRight } from 'lucide-react';
import {
    fetchEmployeeBenefits,
    createEmployeeBenefit,
    updateEmployeeBenefit,
    deleteEmployeeBenefit,
    fetchBenefitTypes,
    fetchEmployees,
} from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
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

const EMPTY_FORM = {
    employee_id: '',
    benefit_type_id: '',
    provider: '',
    policy_number: '',
    coverage_amount: '',
    employer_contribution: '',
    employee_contribution: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    notes: '',
    is_active: true,
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatCurrency(amount) {
    if (amount === null || amount === undefined || amount === '') return '-';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

export default function BenefitsManagement() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [benefitTypeFilter, setBenefitTypeFilter] = useState('all');
    const [formOpen, setFormOpen] = useState(false);
    const [editingBenefit, setEditingBenefit] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [deleteDialog, setDeleteDialog] = useState({ open: false, benefit: null });
    const [errors, setErrors] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'benefits', 'list', { search, page, benefitTypeFilter }],
        queryFn: () => fetchEmployeeBenefits({
            search: search || undefined,
            benefit_type_id: benefitTypeFilter !== 'all' ? benefitTypeFilter : undefined,
            page,
            per_page: 15,
        }),
    });

    const { data: benefitTypesData } = useQuery({
        queryKey: ['hr', 'benefits', 'types'],
        queryFn: () => fetchBenefitTypes({ per_page: 100 }),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'list'],
        queryFn: () => fetchEmployees({ per_page: 200 }),
    });

    const saveMutation = useMutation({
        mutationFn: (formData) => editingBenefit
            ? updateEmployeeBenefit(editingBenefit.id, formData)
            : createEmployeeBenefit(formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'benefits', 'list'] });
            setFormOpen(false);
            setEditingBenefit(null);
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
        mutationFn: (id) => deleteEmployeeBenefit(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'benefits', 'list'] });
            setDeleteDialog({ open: false, benefit: null });
        },
    });

    const benefits = data?.data || [];
    const meta = data?.meta || {};
    const benefitTypes = benefitTypesData?.data || [];
    const employees = employeesData?.data || [];

    function openCreate() {
        setEditingBenefit(null);
        setForm(EMPTY_FORM);
        setErrors({});
        setFormOpen(true);
    }

    function openEdit(benefit) {
        setEditingBenefit(benefit);
        setForm({
            employee_id: String(benefit.employee_id || ''),
            benefit_type_id: String(benefit.benefit_type_id || ''),
            provider: benefit.provider || '',
            policy_number: benefit.policy_number || '',
            coverage_amount: benefit.coverage_amount ?? '',
            employer_contribution: benefit.employer_contribution ?? '',
            employee_contribution: benefit.employee_contribution ?? '',
            start_date: benefit.start_date || '',
            end_date: benefit.end_date || '',
            notes: benefit.notes || '',
            is_active: benefit.is_active ?? true,
        });
        setErrors({});
        setFormOpen(true);
    }

    function handleSubmit(e) {
        e.preventDefault();
        saveMutation.mutate({
            ...form,
            employee_id: parseInt(form.employee_id),
            benefit_type_id: parseInt(form.benefit_type_id),
            coverage_amount: form.coverage_amount !== '' ? parseFloat(form.coverage_amount) : null,
            employer_contribution: form.employer_contribution !== '' ? parseFloat(form.employer_contribution) : null,
            employee_contribution: form.employee_contribution !== '' ? parseFloat(form.employee_contribution) : null,
            end_date: form.end_date || null,
        });
    }

    return (
        <div>
            <PageHeader
                title="Employee Benefits"
                description="Manage benefit assignments for employees."
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Assign Benefit
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-6">
                    <div className="mb-4 flex flex-wrap items-center gap-3">
                        <div className="flex-1">
                            <SearchInput
                                value={search}
                                onChange={(v) => { setSearch(v); setPage(1); }}
                                placeholder="Search by employee..."
                            />
                        </div>
                        <div className="w-44">
                            <Select value={benefitTypeFilter} onValueChange={(v) => { setBenefitTypeFilter(v); setPage(1); }}>
                                <SelectTrigger>
                                    <SelectValue placeholder="All Types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    {benefitTypes.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>{t.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {isLoading ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : benefits.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Shield className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No benefits assigned</p>
                            <p className="mt-1 text-xs text-zinc-400">Assign benefits to employees to get started.</p>
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Benefit Type</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead>Coverage</TableHead>
                                        <TableHead>Start Date</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {benefits.map((benefit) => (
                                        <TableRow key={benefit.id}>
                                            <TableCell className="font-medium">
                                                {benefit.employee?.full_name || '-'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {benefit.benefit_type?.name || '-'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-500">
                                                {benefit.provider || '-'}
                                            </TableCell>
                                            <TableCell>
                                                {formatCurrency(benefit.coverage_amount)}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-500">
                                                {formatDate(benefit.start_date)}
                                            </TableCell>
                                            <TableCell>
                                                {benefit.is_active ? (
                                                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                                ) : (
                                                    <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-500">Inactive</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="sm" onClick={() => openEdit(benefit)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() => setDeleteDialog({ open: true, benefit })}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {meta.last_page > 1 && (
                                <div className="mt-4 flex items-center justify-between text-sm text-zinc-500">
                                    <span>Showing {meta.from}–{meta.to} of {meta.total}</span>
                                    <div className="flex items-center gap-2">
                                        <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <span>Page {meta.current_page} of {meta.last_page}</span>
                                        <Button variant="outline" size="sm" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            {/* Form Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingBenefit ? 'Edit Benefit' : 'Assign Benefit'}</DialogTitle>
                        <DialogDescription>
                            {editingBenefit ? 'Update benefit assignment.' : 'Assign a benefit to an employee.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Employee *</label>
                                <Select value={form.employee_id} onValueChange={(v) => setForm((f) => ({ ...f, employee_id: v }))}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {employees.map((emp) => (
                                            <SelectItem key={emp.id} value={String(emp.id)}>
                                                {emp.full_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.employee_id && <p className="mt-1 text-xs text-red-600">{errors.employee_id[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Benefit Type *</label>
                                <Select value={form.benefit_type_id} onValueChange={(v) => setForm((f) => ({ ...f, benefit_type_id: v }))}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {benefitTypes.map((t) => (
                                            <SelectItem key={t.id} value={String(t.id)}>{t.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.benefit_type_id && <p className="mt-1 text-xs text-red-600">{errors.benefit_type_id[0]}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Provider</label>
                                <input
                                    type="text"
                                    value={form.provider}
                                    onChange={(e) => setForm((f) => ({ ...f, provider: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Policy Number</label>
                                <input
                                    type="text"
                                    value={form.policy_number}
                                    onChange={(e) => setForm((f) => ({ ...f, policy_number: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Coverage (MYR)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.coverage_amount}
                                    onChange={(e) => setForm((f) => ({ ...f, coverage_amount: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Employer (MYR)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.employer_contribution}
                                    onChange={(e) => setForm((f) => ({ ...f, employer_contribution: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Employee (MYR)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.employee_contribution}
                                    onChange={(e) => setForm((f) => ({ ...f, employee_contribution: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">Start Date *</label>
                                <input
                                    type="date"
                                    value={form.start_date}
                                    onChange={(e) => setForm((f) => ({ ...f, start_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                    required
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-zinc-700">End Date</label>
                                <input
                                    type="date"
                                    value={form.end_date}
                                    onChange={(e) => setForm((f) => ({ ...f, end_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
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
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>Cancel</Button>
                            <Button type="submit" disabled={saveMutation.isPending}>
                                {saveMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                {editingBenefit ? 'Update' : 'Assign'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={() => setDeleteDialog({ open: false, benefit: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove Benefit</DialogTitle>
                        <DialogDescription>
                            Remove <strong>{deleteDialog.benefit?.benefit_type?.name}</strong> from{' '}
                            <strong>{deleteDialog.benefit?.employee?.full_name}</strong>?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog({ open: false, benefit: null })}>Cancel</Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate(deleteDialog.benefit.id)}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Remove
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
