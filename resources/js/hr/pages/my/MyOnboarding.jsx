import { useQuery } from '@tanstack/react-query';
import {
    CheckCircle2,
    Circle,
    Clock,
    Loader2,
    ListChecks,
    CalendarDays,
    User,
} from 'lucide-react';
import { fetchMyOnboarding } from '../../lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { cn } from '../../lib/utils';

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function isOverdue(dateStr, status) {
    if (!dateStr || status === 'completed') return false;
    return new Date(dateStr) < new Date();
}

const TASK_STATUS_CONFIG = {
    pending: {
        label: 'Pending',
        icon: Circle,
        rowClass: '',
        iconClass: 'text-zinc-300',
    },
    in_progress: {
        label: 'In Progress',
        icon: Clock,
        rowClass: 'bg-blue-50/40',
        iconClass: 'text-blue-400',
    },
    completed: {
        label: 'Completed',
        icon: CheckCircle2,
        rowClass: 'bg-emerald-50/40',
        iconClass: 'text-emerald-500',
    },
    skipped: {
        label: 'Skipped',
        icon: Circle,
        rowClass: '',
        iconClass: 'text-zinc-200',
    },
};

export default function MyOnboarding() {
    const { data, isLoading } = useQuery({
        queryKey: ['my-onboarding'],
        queryFn: fetchMyOnboarding,
    });

    const onboarding = data?.data ?? null;

    if (isLoading) {
        return (
            <div className="flex justify-center py-16">
                <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (!onboarding) {
        return (
            <div className="space-y-4">
                <div>
                    <h1 className="text-xl font-bold text-zinc-900">My Onboarding</h1>
                    <p className="text-sm text-zinc-500 mt-0.5">Your onboarding checklist and tasks</p>
                </div>
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <div className="rounded-full bg-zinc-100 p-4 mb-4">
                            <ListChecks className="h-8 w-8 text-zinc-400" />
                        </div>
                        <p className="text-sm font-medium text-zinc-600">No onboarding checklist</p>
                        <p className="mt-1 text-xs text-zinc-400">
                            Your onboarding tasks will appear here when assigned.
                        </p>
                    </CardContent>
                </Card>
            </div>
        );
    }

    const tasks = onboarding.tasks ?? [];
    const totalTasks = tasks.length;
    const completedTasks = tasks.filter((t) => t.status === 'completed').length;
    const progressPct = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0;

    return (
        <div className="space-y-4">
            {/* Header */}
            <div>
                <h1 className="text-xl font-bold text-zinc-900">My Onboarding</h1>
                <p className="text-sm text-zinc-500 mt-0.5">Your onboarding checklist and tasks</p>
            </div>

            {/* Progress Overview */}
            <Card>
                <CardContent className="py-4 px-4 space-y-3">
                    <div className="flex items-center gap-3">
                        <div className={cn(
                            'rounded-full p-3',
                            progressPct === 100 ? 'bg-emerald-100' : 'bg-blue-50'
                        )}>
                            {progressPct === 100
                                ? <CheckCircle2 className="h-5 w-5 text-emerald-600" />
                                : <ListChecks className="h-5 w-5 text-blue-600" />
                            }
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold text-zinc-900">
                                {onboarding.template?.name || 'Onboarding Checklist'}
                            </p>
                            {progressPct === 100 ? (
                                <p className="text-xs text-emerald-600 font-medium">All tasks completed!</p>
                            ) : (
                                <p className="text-xs text-zinc-500">
                                    {completedTasks} of {totalTasks} tasks completed
                                </p>
                            )}
                        </div>
                        <div className="shrink-0 text-right">
                            <span className={cn(
                                'text-2xl font-bold',
                                progressPct === 100 ? 'text-emerald-600' :
                                progressPct >= 50 ? 'text-blue-600' : 'text-zinc-700'
                            )}>
                                {progressPct}%
                            </span>
                        </div>
                    </div>

                    {/* Progress bar */}
                    <div className="h-2.5 rounded-full bg-zinc-100 overflow-hidden">
                        <div
                            className={cn(
                                'h-full rounded-full transition-all duration-500',
                                progressPct === 100 ? 'bg-emerald-500' :
                                progressPct >= 50 ? 'bg-blue-500' : 'bg-amber-500'
                            )}
                            style={{ width: `${progressPct}%` }}
                        />
                    </div>

                    {/* Stats row */}
                    <div className="grid grid-cols-3 gap-2 text-center">
                        <div className="rounded-lg bg-zinc-50 py-2">
                            <p className="text-lg font-bold text-zinc-900">{totalTasks}</p>
                            <p className="text-[10px] text-zinc-400">Total</p>
                        </div>
                        <div className="rounded-lg bg-emerald-50 py-2">
                            <p className="text-lg font-bold text-emerald-700">{completedTasks}</p>
                            <p className="text-[10px] text-emerald-500">Done</p>
                        </div>
                        <div className="rounded-lg bg-amber-50 py-2">
                            <p className="text-lg font-bold text-amber-700">{totalTasks - completedTasks}</p>
                            <p className="text-[10px] text-amber-500">Remaining</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Tasks Checklist */}
            {tasks.length > 0 && (
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm">Tasks</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {tasks.map((task, index) => {
                            const cfg = TASK_STATUS_CONFIG[task.status] || TASK_STATUS_CONFIG.pending;
                            const TaskIcon = cfg.icon;
                            const overdue = isOverdue(task.due_date, task.status);

                            return (
                                <div
                                    key={task.id ?? index}
                                    className={cn(
                                        'flex items-start gap-3 rounded-lg border border-zinc-100 p-3 transition-colors',
                                        cfg.rowClass
                                    )}
                                >
                                    <TaskIcon className={cn('mt-0.5 h-4.5 w-4.5 shrink-0', cfg.iconClass)} />
                                    <div className="min-w-0 flex-1">
                                        <p className={cn(
                                            'text-sm font-medium',
                                            task.status === 'completed' ? 'text-zinc-500 line-through' : 'text-zinc-900'
                                        )}>
                                            {task.title}
                                        </p>
                                        {task.description && (
                                            <p className="text-xs text-zinc-400 mt-0.5">{task.description}</p>
                                        )}
                                        <div className="mt-1.5 flex flex-wrap items-center gap-3">
                                            {task.due_date && (
                                                <div className="flex items-center gap-1">
                                                    <CalendarDays className="h-3 w-3 text-zinc-400" />
                                                    <span className={cn(
                                                        'text-xs',
                                                        overdue ? 'text-red-500 font-medium' : 'text-zinc-400'
                                                    )}>
                                                        Due: {formatDate(task.due_date)}
                                                        {overdue && ' (Overdue)'}
                                                    </span>
                                                </div>
                                            )}
                                            {(task.assigned_to_name || task.assigned_to?.name) && (
                                                <div className="flex items-center gap-1">
                                                    <User className="h-3 w-3 text-zinc-400" />
                                                    <span className="text-xs text-zinc-400">
                                                        {task.assigned_to_name || task.assigned_to?.name}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    {/* Status badge */}
                                    <div className="shrink-0">
                                        <span className={cn(
                                            'rounded-full px-2 py-0.5 text-[10px] font-medium',
                                            task.status === 'completed'
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : task.status === 'in_progress'
                                                ? 'bg-blue-100 text-blue-700'
                                                : overdue
                                                ? 'bg-red-100 text-red-600'
                                                : 'bg-zinc-100 text-zinc-600'
                                        )}>
                                            {overdue && task.status !== 'completed' ? 'Overdue' : cfg.label}
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
