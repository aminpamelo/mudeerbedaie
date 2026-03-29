import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    Plus,
    Eye,
    AlertTriangle,
    TrendingUp,
    Loader2,
} from 'lucide-react';
import {
    fetchPips,
    createPip,
    fetchEmployees,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
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
    DialogFooter,
} from '../../components/ui/dialog';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'extended', label: 'Extended' },
    { value: 'cancelled', label: 'Cancelled' },
];

const STATUS_BADGE = {
    active: 'bg-amber-100 text-amber-700',
    completed: 'bg-emerald-100 text-emerald-700',
    extended: 'bg-blue-100 text-blue-700',
    cancelled: 'bg-red-100 text-red-700',
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-36 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-48 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1" />
                    <div className="h-6 w-16 animate-pulse rounded-full bg-zinc-200" />
                    <div className="h-8 w-12 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

const EMPTY_FORM = {
    employee_id: '',
    reason: '',
    start_date: '',
    end_date: '',
    goals: [{ title: '', description: '', target_date: '' }],
};

export default function PipManagement() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [statusFilter, setStatusFilter] = useState('all');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [form, setForm] = useState(EMPTY_FORM);
    const [formError, setFormError] = useState('');

    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'performance', 'pips', { statusFilter }],
        queryFn: () => fetchPips({
            status: statusFilter !== 'all' ? statusFilter : undefined,
            per_page: 50,
        }),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', { status: 'active', per_page: 200 }],
        queryFn: () => fetchEmployees({ status: 'active', per_page: 200 }),
    });

    const createMutation = useMutation({
        mutationFn: createPip,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'pips'] });
            setDialogOpen(false);
            setForm(EMPTY_FORM);
            setFormError('');
        },
        onError: (err) => setFormError(err?.response?.data?.message || 'Failed to create PIP.'),
    });

    const pips = data?.data || [];
    const employees = employeesData?.data || [];

    function addGoal() {
        setForm((f) => ({
            ...f,
            goals: [...f.goals, { title: '', description: '', target_date: '' }],
        }));
    }

    function removeGoal(index) {
        setForm((f) => ({
            ...f,
            goals: f.goals.filter((_, i) => i !== index),
        }));
    }

    function updateGoal(index, field, value) {
        setForm((f) => ({
            ...f,
            goals: f.goals.map((g, i) => (i === index ? { ...g, [field]: value } : g)),
        }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (!form.employee_id) {
            setFormError('Please select an employee.');
            return;
        }
        if (!form.reason.trim()) {
            setFormError('Reason is required.');
            return;
        }
        createMutation.mutate(form);
    }

    function getGoalsProgress(pip) {
        const goals = pip.goals || [];
        if (goals.length === 0) return null;
        const completed = goals.filter((g) => g.status === 'completed').length;
        return { completed, total: goals.length };
    }

    return (
        <div>
            <PageHeader
                title="Performance Improvement Plans"
                description="Manage PIPs for employees requiring performance improvement."
                action={
                    <Button onClick={() => { setForm(EMPTY_FORM); setFormError(''); setDialogOpen(true); }}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        New PIP
                    </Button>
                }
            />

            {/* Status Filter */}
            <div className="mb-4 flex gap-2">
                {STATUS_OPTIONS.map((opt) => (
                    <button
                        key={opt.value}
                        type="button"
                        onClick={() => setStatusFilter(opt.value)}
                        className={cn(
                            'rounded-full px-3 py-1.5 text-xs font-medium transition-colors',
                            statusFilter === opt.value
                                ? 'bg-zinc-900 text-white'
                                : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200'
                        )}
                    >
                        {opt.label}
                    </button>
                ))}
            </div>

            <Card>
                {isLoading ? (
                    <SkeletonTable />
                ) : isError ? (
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <AlertTriangle className="mb-3 h-10 w-10 text-red-300" />
                        <p className="text-sm font-medium text-zinc-600">Failed to load PIPs.</p>
                    </CardContent>
                ) : pips.length === 0 ? (
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <TrendingUp className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">No PIPs found</h3>
                        <p className="mt-1 text-sm text-zinc-500">
                            {statusFilter !== 'all'
                                ? 'No PIPs match the selected status.'
                                : 'No performance improvement plans have been created yet.'}
                        </p>
                    </CardContent>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>Start Date</TableHead>
                                <TableHead>End Date</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Goals Progress</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pips.map((pip) => {
                                const progress = getGoalsProgress(pip);

                                return (
                                    <TableRow key={pip.id}>
                                        <TableCell className="font-medium text-zinc-900">
                                            {pip.employee?.full_name || '-'}
                                        </TableCell>
                                        <TableCell className="max-w-48">
                                            <p className="truncate text-sm text-zinc-600" title={pip.reason}>
                                                {pip.reason || '-'}
                                            </p>
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-500">
                                            {formatDate(pip.start_date)}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-500">
                                            {formatDate(pip.end_date)}
                                        </TableCell>
                                        <TableCell>
                                            <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', STATUS_BADGE[pip.status] || 'bg-zinc-100 text-zinc-600')}>
                                                {pip.status || '-'}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {progress ? (
                                                <div className="flex items-center gap-2">
                                                    <div className="h-1.5 w-20 overflow-hidden rounded-full bg-zinc-200">
                                                        <div
                                                            className="h-full rounded-full bg-emerald-500"
                                                            style={{ width: `${Math.round((progress.completed / progress.total) * 100)}%` }}
                                                        />
                                                    </div>
                                                    <span className="text-xs text-zinc-500">
                                                        {progress.completed}/{progress.total}
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-zinc-400">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => navigate(`/performance/pips/${pip.id}`)}
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}
            </Card>

            {/* Create PIP Dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Create Performance Improvement Plan</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {formError && (
                            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{formError}</p>
                        )}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Employee *</label>
                            <select
                                value={form.employee_id}
                                onChange={(e) => setForm((f) => ({ ...f, employee_id: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            >
                                <option value="">Select employee...</option>
                                {employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>
                                        {emp.full_name} — {emp.department?.name || 'No Dept'}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Reason *</label>
                            <textarea
                                value={form.reason}
                                onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))}
                                placeholder="Describe the performance issue..."
                                rows={3}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Start Date</label>
                                <input
                                    type="date"
                                    value={form.start_date}
                                    onChange={(e) => setForm((f) => ({ ...f, start_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">End Date</label>
                                <input
                                    type="date"
                                    value={form.end_date}
                                    onChange={(e) => setForm((f) => ({ ...f, end_date: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                        </div>

                        {/* Goals */}
                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <label className="text-sm font-medium text-zinc-700">Improvement Goals</label>
                                <Button type="button" variant="outline" size="sm" onClick={addGoal}>
                                    <Plus className="mr-1 h-3.5 w-3.5" />
                                    Add Goal
                                </Button>
                            </div>
                            <div className="space-y-3">
                                {form.goals.map((goal, i) => (
                                    <div key={i} className="rounded-lg border border-zinc-200 p-3">
                                        <div className="mb-2 flex items-center justify-between">
                                            <span className="text-xs font-medium text-zinc-500">Goal {i + 1}</span>
                                            {form.goals.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() => removeGoal(i)}
                                                    className="text-xs text-red-500 hover:text-red-700"
                                                >
                                                    Remove
                                                </button>
                                            )}
                                        </div>
                                        <div className="grid gap-2">
                                            <input
                                                type="text"
                                                value={goal.title}
                                                onChange={(e) => updateGoal(i, 'title', e.target.value)}
                                                placeholder="Goal title"
                                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                            />
                                            <div className="grid grid-cols-2 gap-2">
                                                <input
                                                    type="text"
                                                    value={goal.description}
                                                    onChange={(e) => updateGoal(i, 'description', e.target.value)}
                                                    placeholder="Description (optional)"
                                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                                />
                                                <input
                                                    type="date"
                                                    value={goal.target_date}
                                                    onChange={(e) => updateGoal(i, 'target_date', e.target.value)}
                                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createMutation.isPending}>
                                {createMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Create PIP
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
