import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import {
    ChevronLeft,
    Plus,
    CheckCircle,
    Clock,
    AlertTriangle,
    Loader2,
    CalendarDays,
    RefreshCw,
} from 'lucide-react';
import {
    fetchPip,
    extendPip,
    completePip,
    addPipGoal,
    updatePipGoal,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '../../components/ui/dialog';

const GOAL_STATUS_OPTIONS = ['pending', 'in_progress', 'completed', 'missed'];

const GOAL_STATUS_BADGE = {
    pending: 'bg-zinc-100 text-zinc-600',
    in_progress: 'bg-blue-100 text-blue-700',
    completed: 'bg-emerald-100 text-emerald-700',
    missed: 'bg-red-100 text-red-700',
};

const PIP_STATUS_BADGE = {
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

function SkeletonDetail() {
    return (
        <div className="space-y-6">
            <div className="h-32 animate-pulse rounded-lg bg-zinc-200" />
            <div className="h-64 animate-pulse rounded-lg bg-zinc-200" />
        </div>
    );
}

export default function PipDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [addGoalDialog, setAddGoalDialog] = useState(false);
    const [goalForm, setGoalForm] = useState({ title: '', description: '', target_date: '' });
    const [extendDialog, setExtendDialog] = useState(false);
    const [extendForm, setExtendForm] = useState({ new_end_date: '', extension_reason: '' });
    const [completeDialog, setCompleteDialog] = useState(false);
    const [completeForm, setCompleteForm] = useState({ outcome: 'improved', notes: '' });
    const [updateGoalDialog, setUpdateGoalDialog] = useState(null);
    const [updateGoalForm, setUpdateGoalForm] = useState({ status: 'pending', check_in_notes: '' });
    const [formError, setFormError] = useState('');

    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'performance', 'pips', id],
        queryFn: () => fetchPip(id),
        enabled: !!id,
    });

    const extendMutation = useMutation({
        mutationFn: (formData) => extendPip(id, formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'pips', id] });
            setExtendDialog(false);
            setExtendForm({ new_end_date: '', extension_reason: '' });
        },
        onError: (err) => setFormError(err?.response?.data?.message || 'Failed to extend PIP.'),
    });

    const completeMutation = useMutation({
        mutationFn: (formData) => completePip(id, formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'pips', id] });
            setCompleteDialog(false);
        },
        onError: (err) => setFormError(err?.response?.data?.message || 'Failed to complete PIP.'),
    });

    const addGoalMutation = useMutation({
        mutationFn: (goalData) => addPipGoal(id, goalData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'pips', id] });
            setAddGoalDialog(false);
            setGoalForm({ title: '', description: '', target_date: '' });
            setFormError('');
        },
        onError: (err) => setFormError(err?.response?.data?.message || 'Failed to add goal.'),
    });

    const updateGoalMutation = useMutation({
        mutationFn: ({ goalId, data }) => updatePipGoal(id, goalId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'pips', id] });
            setUpdateGoalDialog(null);
            setFormError('');
        },
        onError: (err) => setFormError(err?.response?.data?.message || 'Failed to update goal.'),
    });

    const pip = data?.data || data || null;
    const goals = pip?.goals || [];

    function handleOpenUpdateGoal(goal) {
        setUpdateGoalForm({
            status: goal.status || 'pending',
            check_in_notes: goal.check_in_notes || '',
        });
        setFormError('');
        setUpdateGoalDialog(goal);
    }

    function handleAddGoal(e) {
        e.preventDefault();
        if (!goalForm.title.trim()) {
            setFormError('Goal title is required.');
            return;
        }
        addGoalMutation.mutate(goalForm);
    }

    if (isError) {
        return (
            <div>
                <PageHeader title="PIP Detail" />
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <AlertTriangle className="mb-3 h-10 w-10 text-red-300" />
                        <p className="text-sm font-medium text-zinc-600">Failed to load PIP details.</p>
                        <Button variant="outline" className="mt-4" onClick={() => navigate('/performance/pips')}>
                            Back to PIPs
                        </Button>
                    </CardContent>
                </Card>
            </div>
        );
    }

    const isActive = pip?.status === 'active' || pip?.status === 'extended';

    return (
        <div>
            <PageHeader
                title="PIP Detail"
                description={pip ? `${pip.employee?.full_name} — Performance Improvement Plan` : ''}
                action={
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={() => navigate('/performance/pips')}>
                            <ChevronLeft className="mr-1 h-4 w-4" />
                            Back
                        </Button>
                        {isActive && (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => { setFormError(''); setAddGoalDialog(true); }}
                                >
                                    <Plus className="mr-1.5 h-4 w-4" />
                                    Add Goal
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => { setExtendForm({ new_end_date: '', extension_reason: '' }); setFormError(''); setExtendDialog(true); }}
                                >
                                    <RefreshCw className="mr-1.5 h-4 w-4" />
                                    Extend PIP
                                </Button>
                                <Button
                                    onClick={() => { setCompleteForm({ outcome: 'improved', notes: '' }); setFormError(''); setCompleteDialog(true); }}
                                >
                                    <CheckCircle className="mr-1.5 h-4 w-4" />
                                    Complete PIP
                                </Button>
                            </>
                        )}
                    </div>
                }
            />

            {isLoading ? (
                <SkeletonDetail />
            ) : (
                <div className="space-y-6">
                    {/* PIP Overview */}
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex flex-col gap-6 sm:flex-row">
                                <div className="flex items-center gap-4">
                                    <div className="flex h-14 w-14 items-center justify-center rounded-full bg-zinc-100 text-lg font-bold text-zinc-600">
                                        {pip?.employee?.full_name?.charAt(0) || '?'}
                                    </div>
                                    <div>
                                        <p className="font-semibold text-zinc-900">{pip?.employee?.full_name || '-'}</p>
                                        <p className="text-sm text-zinc-500">
                                            {pip?.employee?.department?.name || '-'} · {pip?.employee?.position?.name || '-'}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-6 sm:ml-auto">
                                    <div>
                                        <p className="text-xs text-zinc-500">Status</p>
                                        <span className={cn('mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', PIP_STATUS_BADGE[pip?.status] || 'bg-zinc-100 text-zinc-600')}>
                                            {pip?.status || '-'}
                                        </span>
                                    </div>
                                    <div>
                                        <p className="text-xs text-zinc-500">Start Date</p>
                                        <p className="mt-1 text-sm font-medium text-zinc-900">{formatDate(pip?.start_date)}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-zinc-500">End Date</p>
                                        <p className="mt-1 text-sm font-medium text-zinc-900">{formatDate(pip?.end_date)}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-zinc-500">Goals</p>
                                        <p className="mt-1 text-sm font-medium text-zinc-900">
                                            {goals.filter((g) => g.status === 'completed').length}/{goals.length} completed
                                        </p>
                                    </div>
                                </div>
                            </div>
                            {pip?.reason && (
                                <div className="mt-4 rounded-lg bg-zinc-50 p-4">
                                    <p className="text-xs font-medium text-zinc-500">Reason</p>
                                    <p className="mt-1 text-sm text-zinc-700">{pip.reason}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Goals List */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 text-lg font-semibold text-zinc-900">Improvement Goals</h3>

                            {goals.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-10 text-center">
                                    <CalendarDays className="mb-3 h-10 w-10 text-zinc-300" />
                                    <p className="text-sm text-zinc-500">No goals added yet.</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {goals.map((goal) => (
                                        <div key={goal.id} className="rounded-lg border border-zinc-200 p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-medium text-zinc-900">{goal.title}</p>
                                                        <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium capitalize', GOAL_STATUS_BADGE[goal.status] || 'bg-zinc-100 text-zinc-600')}>
                                                            {goal.status?.replace('_', ' ') || 'pending'}
                                                        </span>
                                                    </div>
                                                    {goal.description && (
                                                        <p className="mt-1 text-sm text-zinc-600">{goal.description}</p>
                                                    )}
                                                    {goal.target_date && (
                                                        <p className="mt-1.5 flex items-center gap-1 text-xs text-zinc-400">
                                                            <Clock className="h-3.5 w-3.5" />
                                                            Target: {formatDate(goal.target_date)}
                                                        </p>
                                                    )}
                                                    {goal.check_in_notes && (
                                                        <div className="mt-2 rounded-md bg-zinc-50 px-3 py-2">
                                                            <p className="text-xs font-medium text-zinc-500">Check-in Notes</p>
                                                            <p className="mt-0.5 text-sm text-zinc-600">{goal.check_in_notes}</p>
                                                        </div>
                                                    )}
                                                </div>
                                                {isActive && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handleOpenUpdateGoal(goal)}
                                                    >
                                                        Update
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Add Goal Dialog */}
            <Dialog open={addGoalDialog} onOpenChange={setAddGoalDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Add Improvement Goal</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleAddGoal} className="space-y-4">
                        {formError && (
                            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{formError}</p>
                        )}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Title *</label>
                            <input
                                type="text"
                                value={goalForm.title}
                                onChange={(e) => setGoalForm((f) => ({ ...f, title: e.target.value }))}
                                placeholder="e.g. Improve punctuality to 95%"
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Description</label>
                            <textarea
                                value={goalForm.description}
                                onChange={(e) => setGoalForm((f) => ({ ...f, description: e.target.value }))}
                                placeholder="Describe the goal in detail..."
                                rows={3}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Target Date</label>
                            <input
                                type="date"
                                value={goalForm.target_date}
                                onChange={(e) => setGoalForm((f) => ({ ...f, target_date: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setAddGoalDialog(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={addGoalMutation.isPending}>
                                {addGoalMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Add Goal
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Update Goal Status Dialog */}
            <Dialog open={!!updateGoalDialog} onOpenChange={() => setUpdateGoalDialog(null)}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Update Goal Status</DialogTitle>
                    </DialogHeader>
                    {updateGoalDialog && (
                        <div className="space-y-4">
                            {formError && (
                                <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{formError}</p>
                            )}
                            <div className="rounded-lg bg-zinc-50 p-3">
                                <p className="text-sm font-medium text-zinc-900">{updateGoalDialog.title}</p>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Status</label>
                                <select
                                    value={updateGoalForm.status}
                                    onChange={(e) => setUpdateGoalForm((f) => ({ ...f, status: e.target.value }))}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                >
                                    {GOAL_STATUS_OPTIONS.map((s) => (
                                        <option key={s} value={s} className="capitalize">
                                            {s.replace('_', ' ').charAt(0).toUpperCase() + s.replace('_', ' ').slice(1)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-zinc-700">Check-in Notes</label>
                                <textarea
                                    value={updateGoalForm.check_in_notes}
                                    onChange={(e) => setUpdateGoalForm((f) => ({ ...f, check_in_notes: e.target.value }))}
                                    placeholder="Notes from check-in or progress update..."
                                    rows={3}
                                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                />
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setUpdateGoalDialog(null)}>
                                    Cancel
                                </Button>
                                <Button
                                    disabled={updateGoalMutation.isPending}
                                    onClick={() => updateGoalMutation.mutate({ goalId: updateGoalDialog.id, data: updateGoalForm })}
                                >
                                    {updateGoalMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                    Save Changes
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Extend PIP Dialog */}
            <Dialog open={extendDialog} onOpenChange={setExtendDialog}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Extend PIP Duration</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        {formError && (
                            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{formError}</p>
                        )}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">New End Date *</label>
                            <input
                                type="date"
                                value={extendForm.new_end_date}
                                onChange={(e) => setExtendForm((f) => ({ ...f, new_end_date: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Reason for Extension</label>
                            <textarea
                                value={extendForm.extension_reason}
                                onChange={(e) => setExtendForm((f) => ({ ...f, extension_reason: e.target.value }))}
                                placeholder="Explain why the PIP duration needs to be extended..."
                                rows={3}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setExtendDialog(false)}>
                                Cancel
                            </Button>
                            <Button
                                disabled={extendMutation.isPending || !extendForm.new_end_date}
                                onClick={() => extendMutation.mutate(extendForm)}
                            >
                                {extendMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Extend PIP
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>

            {/* Complete PIP Dialog */}
            <Dialog open={completeDialog} onOpenChange={setCompleteDialog}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Complete PIP</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        {formError && (
                            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{formError}</p>
                        )}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Outcome</label>
                            <select
                                value={completeForm.outcome}
                                onChange={(e) => setCompleteForm((f) => ({ ...f, outcome: e.target.value }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            >
                                <option value="improved">Employee Improved</option>
                                <option value="not_improved">Did Not Improve</option>
                                <option value="terminated">Employment Terminated</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700">Completion Notes</label>
                            <textarea
                                value={completeForm.notes}
                                onChange={(e) => setCompleteForm((f) => ({ ...f, notes: e.target.value }))}
                                placeholder="Summary of the PIP outcome..."
                                rows={3}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCompleteDialog(false)}>
                                Cancel
                            </Button>
                            <Button
                                disabled={completeMutation.isPending}
                                onClick={() => completeMutation.mutate(completeForm)}
                            >
                                {completeMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Complete PIP
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
