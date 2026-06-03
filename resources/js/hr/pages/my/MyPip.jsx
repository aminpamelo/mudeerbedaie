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
import { EmployeePageHeader } from '../../components/ui/employee-page-header';

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
        className: 'text-slate-400 dark:text-slate-500',
        badgeClass: 'bg-slate-100 text-slate-600 dark:bg-white/[0.08] dark:text-slate-300',
    },
    in_progress: {
        label: 'In Progress',
        icon: Clock,
        className: 'text-blue-500 dark:text-blue-400',
        badgeClass: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
    },
    completed: {
        label: 'Completed',
        icon: CheckCircle2,
        className: 'text-emerald-500 dark:text-emerald-400',
        badgeClass: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    },
    missed: {
        label: 'Missed',
        icon: AlertTriangle,
        className: 'text-red-500 dark:text-red-400',
        badgeClass: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
    },
};

const PIP_STATUS_CONFIG = {
    active: { label: 'Active', className: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' },
    completed: { label: 'Completed', className: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' },
    extended: { label: 'Extended', className: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300' },
    terminated: { label: 'Terminated', className: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' },
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
                <Loader2 className="h-6 w-6 animate-spin text-slate-400 dark:text-slate-500" />
            </div>
        );
    }

    if (!pip) {
        return (
            <div className="space-y-4">
                <EmployeePageHeader
                    icon={TrendingUp}
                    accent="slate"
                    title="My Improvement Plan"
                />
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <div className="rounded-full bg-emerald-50 p-4 mb-4 dark:bg-emerald-500/15">
                            <CheckCircle2 className="h-8 w-8 text-emerald-500 dark:text-emerald-400" />
                        </div>
                        <p className="text-sm font-medium text-slate-700 dark:text-slate-200">No active improvement plan</p>
                        <p className="mt-1 text-xs text-slate-400 dark:text-slate-500">
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
    const pipStatusCfg = PIP_STATUS_CONFIG[pip.status] || { label: pip.status, className: 'bg-slate-100 text-slate-600 dark:bg-white/[0.08] dark:text-slate-300' };

    const startDate = pip.start_date ? new Date(pip.start_date) : null;
    const endDate = pip.end_date ? new Date(pip.end_date) : null;
    const today = new Date();
    let daysRemaining = null;
    if (endDate) {
        const diff = Math.ceil((endDate - today) / (1000 * 60 * 60 * 24));
        daysRemaining = diff;
    }

    return (
        <div className="space-y-4 pb-4">
            <EmployeePageHeader
                icon={TrendingUp}
                accent="slate"
                title="My Improvement Plan"
            />

            {/* PIP Overview Card */}
            <Card>
                <CardContent className="py-4 px-4 space-y-4">
                    {/* Title + Status */}
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2 flex-wrap">
                                <div className="rounded-lg bg-amber-50 p-1.5 shrink-0 dark:bg-amber-500/15">
                                    <TrendingUp className="h-4 w-4 text-amber-600 dark:text-amber-300" />
                                </div>
                                <p className="text-sm font-semibold text-slate-900 dark:text-white">
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
                        <div className="rounded-lg bg-slate-50 p-3 dark:bg-white/[0.04]">
                            <p className="text-[10px] uppercase tracking-wide text-slate-400 font-medium mb-1 dark:text-slate-500">Reason</p>
                            <p className="text-sm text-slate-700 dark:text-slate-200">{pip.reason}</p>
                        </div>
                    )}

                    {/* Period */}
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <p className="text-[10px] uppercase tracking-wide text-slate-400 font-medium dark:text-slate-500">Start Date</p>
                            <div className="flex items-center gap-1.5 mt-1">
                                <CalendarDays className="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" />
                                <p className="text-sm text-slate-900 dark:text-white">{formatDate(pip.start_date)}</p>
                            </div>
                        </div>
                        <div>
                            <p className="text-[10px] uppercase tracking-wide text-slate-400 font-medium dark:text-slate-500">End Date</p>
                            <div className="flex items-center gap-1.5 mt-1">
                                <CalendarDays className="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" />
                                <p className="text-sm text-slate-900 dark:text-white">{formatDate(pip.end_date)}</p>
                            </div>
                        </div>
                    </div>

                    {/* Days remaining */}
                    {daysRemaining !== null && pip.status === 'active' && (
                        <div className={cn(
                            'rounded-lg px-3 py-2 text-sm font-medium',
                            daysRemaining < 0 ? 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300' :
                            daysRemaining <= 7 ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' :
                            'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300'
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
                                <p className="text-xs font-medium text-slate-700 dark:text-slate-200">Goal Progress</p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">{completedGoals}/{totalGoals} completed</p>
                            </div>
                            <div className="h-2 rounded-full bg-slate-100 overflow-hidden dark:bg-white/[0.08]">
                                <div
                                    className={cn(
                                        'h-full rounded-full transition-all',
                                        progressPct >= 80 ? 'bg-emerald-500' :
                                        progressPct >= 50 ? 'bg-amber-500' : 'bg-red-500'
                                    )}
                                    style={{ width: `${progressPct}%` }}
                                />
                            </div>
                            <p className="text-xs text-slate-400 mt-1 dark:text-slate-500">{progressPct}% complete</p>
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
                                    className="flex items-start gap-3 rounded-lg border border-slate-100 p-3 dark:border-white/[0.07]"
                                >
                                    <GoalIcon className={cn('mt-0.5 h-4 w-4 shrink-0', goalCfg.className)} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <p className="text-sm font-medium text-slate-900 dark:text-white">{goal.title || goal.description}</p>
                                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${goalCfg.badgeClass}`}>
                                                {goalCfg.label}
                                            </span>
                                            {overdue && (
                                                <span className="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-medium text-red-600 dark:bg-red-500/15 dark:text-red-300">
                                                    Overdue
                                                </span>
                                            )}
                                        </div>
                                        {goal.description && goal.title && (
                                            <p className="text-xs text-slate-500 mt-0.5 dark:text-slate-400">{goal.description}</p>
                                        )}
                                        {goal.target_date && (
                                            <div className="flex items-center gap-1 mt-1">
                                                <CalendarDays className="h-3 w-3 text-slate-400 dark:text-slate-500" />
                                                <p className={cn(
                                                    'text-xs',
                                                    overdue ? 'text-red-500 font-medium dark:text-red-400' : 'text-slate-400 dark:text-slate-500'
                                                )}>
                                                    Target: {formatDate(goal.target_date)}
                                                </p>
                                            </div>
                                        )}
                                        {goal.notes && (
                                            <p className="text-xs text-slate-400 mt-1 italic dark:text-slate-500">{goal.notes}</p>
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
                        <p className="text-sm text-slate-700 dark:text-slate-200">{pip.manager_notes}</p>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
