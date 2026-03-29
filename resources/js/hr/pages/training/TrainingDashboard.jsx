import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    CalendarClock,
    CheckCircle2,
    DollarSign,
    ShieldAlert,
    Plus,
    Loader2,
    GraduationCap,
} from 'lucide-react';
import { fetchTrainingDashboard, fetchTrainingBudgets } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';

const STAT_CARDS = [
    { key: 'upcoming_trainings', label: 'Upcoming Trainings', icon: CalendarClock, color: 'text-blue-600', bg: 'bg-blue-50' },
    { key: 'completed_this_year', label: 'Completed This Year', icon: CheckCircle2, color: 'text-emerald-600', bg: 'bg-emerald-50' },
    { key: 'total_spend', label: 'Total Spend (MYR)', icon: DollarSign, color: 'text-purple-600', bg: 'bg-purple-50', isCurrency: true },
    { key: 'expiring_certifications', label: 'Expiring Certifications', icon: ShieldAlert, color: 'text-amber-600', bg: 'bg-amber-50' },
];

function formatCurrency(amount) {
    if (amount === null || amount === undefined) return '-';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

function SkeletonCards() {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, i) => (
                <Card key={i}>
                    <CardContent className="p-6">
                        <div className="flex items-center gap-4">
                            <div className="h-12 w-12 animate-pulse rounded-lg bg-zinc-200" />
                            <div className="flex-1 space-y-2">
                                <div className="h-3 w-24 animate-pulse rounded bg-zinc-200" />
                                <div className="h-6 w-12 animate-pulse rounded bg-zinc-200" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

export default function TrainingDashboard() {
    const navigate = useNavigate();
    const currentYear = new Date().getFullYear();

    const { data: stats, isLoading: statsLoading } = useQuery({
        queryKey: ['hr', 'training', 'dashboard'],
        queryFn: fetchTrainingDashboard,
    });

    const { data: budgetsData, isLoading: budgetsLoading } = useQuery({
        queryKey: ['hr', 'training', 'budgets', currentYear],
        queryFn: () => fetchTrainingBudgets({ year: currentYear }),
    });

    const budgets = budgetsData?.data || [];

    return (
        <div>
            <PageHeader
                title="Training Dashboard"
                description="Overview of training programs, certifications, and budget utilization."
                action={
                    <Button onClick={() => navigate('/hr/training/programs')}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        New Program
                    </Button>
                }
            />

            {statsLoading ? (
                <SkeletonCards />
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {STAT_CARDS.map((card) => {
                        const Icon = card.icon;
                        const rawValue = stats?.[card.key] ?? 0;
                        const displayValue = card.isCurrency ? formatCurrency(rawValue) : rawValue;
                        return (
                            <Card key={card.key}>
                                <CardContent className="p-6">
                                    <div className="flex items-center gap-4">
                                        <div className={cn('flex h-12 w-12 items-center justify-center rounded-lg', card.bg)}>
                                            <Icon className={cn('h-6 w-6', card.color)} />
                                        </div>
                                        <div>
                                            <p className="text-sm text-zinc-500">{card.label}</p>
                                            <p className="text-2xl font-bold text-zinc-900">{displayValue}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            {/* Budget Utilization */}
            <Card className="mt-6">
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-semibold text-zinc-900">Budget Utilization ({currentYear})</h3>
                    {budgetsLoading ? (
                        <div className="flex justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : budgets.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <GraduationCap className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-600">No budgets configured</p>
                            <p className="mt-1 text-xs text-zinc-400">Set up training budgets by department to track spending.</p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {budgets.map((budget) => {
                                const allocated = parseFloat(budget.allocated_amount) || 0;
                                const spent = parseFloat(budget.spent_amount) || 0;
                                const utilization = allocated > 0 ? Math.min((spent / allocated) * 100, 100) : 0;
                                const barColor = utilization > 90 ? 'bg-red-500' : utilization > 70 ? 'bg-amber-500' : 'bg-emerald-500';

                                return (
                                    <div key={budget.id} className="space-y-1">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="font-medium text-zinc-700">
                                                {budget.department?.name || 'Unknown Department'}
                                            </span>
                                            <span className="text-zinc-500">
                                                {formatCurrency(spent)} / {formatCurrency(allocated)} ({utilization.toFixed(0)}%)
                                            </span>
                                        </div>
                                        <div className="h-2 rounded-full bg-zinc-100">
                                            <div
                                                className={cn('h-2 rounded-full transition-all', barColor)}
                                                style={{ width: `${utilization}%` }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
