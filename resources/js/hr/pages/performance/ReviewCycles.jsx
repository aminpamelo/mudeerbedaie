import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    Plus,
    Eye,
    PlayCircle,
    CheckCircle,
    RefreshCw,
    AlertTriangle,
    Loader2,
} from 'lucide-react';
import {
    fetchReviewCycles,
    createReviewCycle,
    activateReviewCycle,
    completeReviewCycle,
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

const CYCLE_TYPES = [
    { value: 'annual', label: 'Annual' },
    { value: 'semi_annual', label: 'Semi-Annual' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'probation', label: 'Probation' },
    { value: 'custom', label: 'Custom' },
];

const STATUS_BADGE = {
    draft: 'bg-zinc-100 text-zinc-600',
    active: 'bg-emerald-100 text-emerald-700',
    completed: 'bg-blue-100 text-blue-700',
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
                    <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1" />
                    <div className="h-6 w-16 animate-pulse rounded-full bg-zinc-200" />
                    <div className="h-8 w-20 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

const EMPTY_FORM = {
    name: '',
    type: 'annual',
    period_start: '',
    period_end: '',
    review_deadline: '',
    description: '',
};

export default function ReviewCycles() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [form, setForm] = useState(EMPTY_FORM);
    const [formError, setFormError] = useState('');

    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'performance', 'cycles'],
        queryFn: () => fetchReviewCycles({ per_page: 50 }),
    });

    const createMutation = useMutation({
        mutationFn: createReviewCycle,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'cycles'] });
            setDialogOpen(false);
            setForm(EMPTY_FORM);
            setFormError('');
        },
        onError: (err) => {
            setFormError(err?.response?.data?.message || 'Failed to create review cycle.');
        },
    });

    const activateMutation = useMutation({
        mutationFn: activateReviewCycle,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'cycles'] });
        },
    });

    const completeMutation = useMutation({
        mutationFn: completeReviewCycle,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'cycles'] });
        },
    });

    const cycles = data?.data || [];

    function handleOpenCreate() {
        setForm(EMPTY_FORM);
        setFormError('');
        setDialogOpen(true);
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (!form.name.trim()) {
            setFormError('Cycle name is required.');
            return;
        }
        createMutation.mutate(form);
    }

    return (
        <div>
            <PageHeader
                title="Review Cycles"
                description="Manage performance review cycles for your organization."
                action={
                    <Button onClick={handleOpenCreate}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        New Cycle
                    </Button>
                }
            />

            <Card>
                {isLoading ? (
                    <SkeletonTable />
                ) : isError ? (
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <AlertTriangle className="mb-3 h-10 w-10 text-red-300" />
                        <p className="text-sm font-medium text-zinc-600">Failed to load review cycles.</p>
                    </CardContent>
                ) : cycles.length === 0 ? (
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <RefreshCw className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">No review cycles yet</h3>
                        <p className="mt-1 text-sm text-zinc-500">Create your first performance review cycle to get started.</p>
                        <Button className="mt-4" onClick={handleOpenCreate}>
                            <Plus className="mr-1.5 h-4 w-4" />
                            New Cycle
                        </Button>
                    </CardContent>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Period</TableHead>
                                <TableHead>Deadline</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Reviews</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {cycles.map((cycle) => (
                                <TableRow key={cycle.id}>
                                    <TableCell className="font-medium text-zinc-900">
                                        {cycle.name}
                                    </TableCell>
                                    <TableCell className="capitalize text-zinc-600">
                                        {cycle.type?.replace('_', ' ') || '-'}
                                    </TableCell>
                                    <TableCell className="text-sm text-zinc-500">
                                        {formatDate(cycle.period_start)} — {formatDate(cycle.period_end)}
                                    </TableCell>
                                    <TableCell className="text-sm text-zinc-500">
                                        {formatDate(cycle.review_deadline)}
                                    </TableCell>
                                    <TableCell>
                                        <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', STATUS_BADGE[cycle.status] || 'bg-zinc-100 text-zinc-600')}>
                                            {cycle.status || '-'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-zinc-600">
                                        {cycle.reviews_count ?? '-'}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => navigate(`/performance/cycles/${cycle.id}`)}
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Button>
                                            {cycle.status === 'draft' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-emerald-600 hover:text-emerald-700"
                                                    disabled={activateMutation.isPending}
                                                    onClick={() => activateMutation.mutate(cycle.id)}
                                                >
                                                    {activateMutation.isPending ? (
                                                        <Loader2 className="h-4 w-4 animate-spin" />
                                                    ) : (
                                                        <PlayCircle className="h-4 w-4" />
                                                    )}
                                                </Button>
                                            )}
                                            {cycle.status === 'active' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-blue-600 hover:text-blue-700"
                                                    disabled={completeMutation.isPending}
                                                    onClick={() => completeMutation.mutate(cycle.id)}
                                                >
                                                    {completeMutation.isPending ? (
                                                        <Loader2 className="h-4 w-4 animate-spin" />
                                                    ) : (
                                                        <CheckCircle className="h-4 w-4" />
                                                    )}
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Create Review Cycle</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {formError && (
                            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{formError}</p>
                        )}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Cycle Name *</label>
                            <input
                                type="text"
                                value={form.name}
                                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                                placeholder="e.g. Annual Review 2026"
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Type</label>
                            <select
                                value={form.type}
                                onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            >
                                {CYCLE_TYPES.map((t) => (
                                    <option key={t.value} value={t.value}>{t.label}</option>
                                ))}
                            </select>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Period Start</label>
                                <input
                                    type="date"
                                    value={form.period_start}
                                    onChange={(e) => setForm((f) => ({ ...f, period_start: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Period End</label>
                                <input
                                    type="date"
                                    value={form.period_end}
                                    onChange={(e) => setForm((f) => ({ ...f, period_end: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Review Deadline</label>
                            <input
                                type="date"
                                value={form.review_deadline}
                                onChange={(e) => setForm((f) => ({ ...f, review_deadline: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Description</label>
                            <textarea
                                value={form.description}
                                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                placeholder="Optional description..."
                                rows={3}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createMutation.isPending}>
                                {createMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Create Cycle
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
