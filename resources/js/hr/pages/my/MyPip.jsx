import { useQuery } from '@tanstack/react-query';
import {
    TrendingUp,
    Loader2,
    CheckCircle2,
    Circle,
    Clock,
    AlertTriangle,
    CalendarDays,
} from 'lucide-react';
import { fetchMyPip } from '../../lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { cn } from '../../lib/utils';

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function isOverdue(dateStr) {
    if (!dateStr) return false;
    return new Date(dateStr) < new Date();
}

const GOAL_STATUS_CONFIG = {
    pending: {
        label: 'Pending',
        icon: Circle,
        className: 'text-zinc-400',
        badgeClass: 'bg-zinc-100 text-zinc-600',
    },
    in_progress: {
        label: 'In Progress',
        icon: Clock,
        className: 'text-blue-500',
        badgeClass: 'bg-blue-100 text-blue-700',
    },
    completed: {
        label: 'Completed',
        icon: CheckCircle2,
        className: 'text-emerald-500',
        badgeClass: 'bg-emerald-100 text-emerald-700',
    },
    missed: {
        label: 'Missed',
        icon: AlertTriangle,
        className: 'text-red-500',
        badgeClass: 'bg-red-100 text-red-700',
    },
};

const PIP_STATUS_CONFIG = {
    active: { label: 'Active', className: 'bg-amber-100 text-amber-700' },
    completed: { label: 'Completed', className: 'bg-emerald-100 text-emerald-700' },
    extended: { label: 'Extended', className: 'bg-blue-100 text-blue-700' },
    terminated: { label: 'Terminated', className: 'bg-red-100 text-red-700' },
};

export default function MyPip() {
    const { data, isLoading } = useQuery({
        queryKey: ['my-pip'],
        queryFn: fetchMyPip,
    });

    const pip = data?.data ?? null;

    if (isLoading) {
        return (
            <div className="flex justify-center py-16">
                <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (!pip) {
        return (
            <div className="space-y-4">
                <div>
                    <h1 className="text-xl font-bold text-zinc-900">My Improvement Plan</h1>
                    <p className="text-sm text-zinc-500 mt-0.5">Performance Improvement Plan (PIP)</p>
                </div>
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <div className="rounded-full bg-emerald-50 p-4 mb-4">
                            <CheckCircle2 className="h-8 w-8 text-emerald-500" />
                        </div>
                        <p className="text-sm font-medium text-zinc-700">No active improvement plan</p>
                        <p className="mt-1 text-xs text-zinc-400">
                            You currently have no active Performance Improvement Plan.
                        </p>
                    </CardContent>
                </Card>
            </div>
        );
    }

    const goals = pip.goals ?? [];
    const totalGoals = goals.length;
    const completedGoals = goals.filter((g) => g.status === 'completed').length;
    const progressPct = totalGoals > 0 ? Math.round((completedGoals / totalGoals) * 100) : 0;
    const pipStatusCfg = PIP_STATUS_CONFIG[pip.status] || { label: pip.status, className: 'bg-zinc-100 text-zinc-600' };

    const startDate = pip.start_date ? new Date(pip.start_date) : null;
    const endDate = pip.end_date ? new Date(pip.end_date) : null;
    const today = new Date();
    let daysRemaining = null;
    if (endDate) {
        const diff = Math.ceil((endDate - today) / (1000 * 60 * 60 * 24));
        daysRemaining = diff;
    }

    return (
        <div className="space-y-4">
            {/* Header */}
            <div>
                <h1 className="text-xl font-bold text-zinc-900">My Improvement Plan</h1>
                <p className="text-sm text-zinc-500 mt-0.5">Performance Improvement Plan (PIP)</p>
            </div>

            {/* PIP Overview Card */}
            <Card>
                <CardContent className="py-4 px-4 space-y-4">
                    {/* Title + Status */}
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2 flex-wrap">
                                <div className="rounded-lg bg-amber-50 p-1.5 shrink-0">
                                    <TrendingUp className="h-4 w-4 text-amber-600" />
                                </div>
                                <p className="text-sm font-semibold text-zinc-900">
                                    {pip.title || 'Performance Improvement Plan'}
                                </p>
                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${pipStatusCfg.className}`}>
                                    {pipStatusCfg.label}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Reason */}
                    {pip.reason && (
                        <div className="rounded-lg bg-zinc-50 p-3">
                            <p className="text-[10px] uppercase tracking-wide text-zinc-400 font-medium mb-1">Reason</p>
                            <p className="text-sm text-zinc-700">{pip.reason}</p>
                        </div>
                    )}

                    {/* Period */}
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <p className="text-[10px] uppercase tracking-wide text-zinc-400 font-medium">Start Date</p>
                            <div className="flex items-center gap-1.5 mt-1">
                                <CalendarDays className="h-3.5 w-3.5 text-zinc-400" />
                                <p className="text-sm text-zinc-900">{formatDate(pip.start_date)}</p>
                            </div>
                        </div>
                        <div>
                            <p className="text-[10px] uppercase tracking-wide text-zinc-400 font-medium">End Date</p>
                            <div className="flex items-center gap-1.5 mt-1">
                                <CalendarDays className="h-3.5 w-3.5 text-zinc-400" />
                                <p className="text-sm text-zinc-900">{formatDate(pip.end_date)}</p>
                            </div>
                        </div>
                    </div>

                    {/* Days remaining */}
                    {daysRemaining !== null && pip.status === 'active' && (
                        <div className={cn(
                            'rounded-lg px-3 py-2 text-sm font-medium',
                            daysRemaining < 0 ? 'bg-red-50 text-red-700' :
                            daysRemaining <= 7 ? 'bg-amber-50 text-amber-700' :
                            'bg-blue-50 text-blue-700'
                        )}>
                            {daysRemaining < 0
                                ? `Plan ended ${Math.abs(daysRemaining)} day${Math.abs(daysRemaining) !== 1 ? 's' : ''} ago`
                                : daysRemaining === 0
                                ? 'Plan ends today'
                                : `${daysRemaining} day${daysRemaining !== 1 ? 's' : ''} remaining`}
                        </div>
                    )}

                    {/* Progress */}
                    {totalGoals > 0 && (
                        <div>
                            <div className="flex items-center justify-between mb-1.5">
                                <p className="text-xs font-medium text-zinc-700">Goal Progress</p>
                                <p className="text-xs text-zinc-500">{completedGoals}/{totalGoals} completed</p>
                            </div>
                            <div className="h-2 rounded-full bg-zinc-100 overflow-hidden">
                                <div
                                    className={cn(
                                        'h-full rounded-full transition-all',
                                        progressPct >= 80 ? 'bg-emerald-500' :
                                        progressPct >= 50 ? 'bg-amber-500' : 'bg-red-500'
                                    )}
                                    style={{ width: `${progressPct}%` }}
                                />
                            </div>
                            <p className="text-xs text-zinc-400 mt-1">{progressPct}% complete</p>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Goals List */}
            {goals.length > 0 && (
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm">Goals & Targets</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {goals.map((goal, index) => {
                            const goalCfg = GOAL_STATUS_CONFIG[goal.status] || GOAL_STATUS_CONFIG.pending;
                            const GoalIcon = goalCfg.icon;
                            const overdue = goal.status !== 'completed' && isOverdue(goal.target_date);

                            return (
                                <div
                                    key={goal.id ?? index}
                                    className="flex items-start gap-3 rounded-lg border border-zinc-100 p-3"
                                >
                                    <GoalIcon className={cn('mt-0.5 h-4 w-4 shrink-0', goalCfg.className)} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <p className="text-sm font-medium text-zinc-900">{goal.title || goal.description}</p>
                                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${goalCfg.badgeClass}`}>
                                                {goalCfg.label}
                                            </span>
                                            {overdue && (
                                                <span className="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-medium text-red-600">
                                                    Overdue
                                                </span>
                                            )}
                                        </div>
                                        {goal.description && goal.title && (
                                            <p className="text-xs text-zinc-500 mt-0.5">{goal.description}</p>
                                        )}
                                        {goal.target_date && (
                                            <div className="flex items-center gap-1 mt-1">
                                                <CalendarDays className="h-3 w-3 text-zinc-400" />
                                                <p className={cn(
                                                    'text-xs',
                                                    overdue ? 'text-red-500 font-medium' : 'text-zinc-400'
                                                )}>
                                                    Target: {formatDate(goal.target_date)}
                                                </p>
                                            </div>
                                        )}
                                        {goal.notes && (
                                            <p className="text-xs text-zinc-400 mt-1 italic">{goal.notes}</p>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            )}

            {/* Reviewer / manager notes */}
            {pip.manager_notes && (
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm">Manager Notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-zinc-700">{pip.manager_notes}</p>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
