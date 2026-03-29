import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    DollarSign,
    Users,
    UserCheck,
    BarChart3,
    Loader2,
} from 'lucide-react';
import { fetchTrainingReports } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Card, CardContent } from '../../components/ui/card';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';

function formatCurrency(amount) {
    if (amount === null || amount === undefined) return '-';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

function SkeletonCards() {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            {Array.from({ length: 3 }).map((_, i) => (
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

export default function TrainingReports() {
    const currentYear = new Date().getFullYear();
    const [filterYear, setFilterYear] = useState(currentYear);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'training', 'reports', filterYear],
        queryFn: () => fetchTrainingReports({ year: filterYear }),
    });

    const report = data?.data || data || {};
    const summary = report.summary || {};
    const departments = report.by_department || [];

    const STAT_CARDS = [
        {
            label: 'Total Training Cost',
            value: formatCurrency(summary.total_cost ?? 0),
            icon: DollarSign,
            color: 'text-purple-600',
            bg: 'bg-purple-50',
        },
        {
            label: 'Total Enrollments',
            value: summary.total_enrollments ?? 0,
            icon: Users,
            color: 'text-blue-600',
            bg: 'bg-blue-50',
        },
        {
            label: 'Attendance Rate',
            value: summary.attendance_rate != null ? `${parseFloat(summary.attendance_rate).toFixed(1)}%` : '-',
            icon: UserCheck,
            color: 'text-emerald-600',
            bg: 'bg-emerald-50',
        },
    ];

    return (
        <div className="space-y-6">
            <PageHeader
                title="Training Reports"
                description="Analyse training spending, participation, and department breakdown."
            />

            {/* Year Filter */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center gap-3">
                        <label className="text-sm font-medium text-zinc-700">Year</label>
                        <select
                            value={filterYear}
                            onChange={(e) => setFilterYear(parseInt(e.target.value))}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            {[currentYear - 2, currentYear - 1, currentYear, currentYear + 1].map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                    </div>
                </CardContent>
            </Card>

            {/* Summary Cards */}
            {isLoading ? (
                <SkeletonCards />
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    {STAT_CARDS.map((card) => {
                        const Icon = card.icon;
                        return (
                            <Card key={card.label}>
                                <CardContent className="p-6">
                                    <div className="flex items-center gap-4">
                                        <div className={cn('flex h-12 w-12 items-center justify-center rounded-lg', card.bg)}>
                                            <Icon className={cn('h-6 w-6', card.color)} />
                                        </div>
                                        <div>
                                            <p className="text-sm text-zinc-500">{card.label}</p>
                                            <p className="text-2xl font-bold text-zinc-900">{card.value}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            {/* Department Breakdown */}
            <Card>
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-semibold text-zinc-900">Training by Department</h3>
                    {isLoading ? (
                        <div className="space-y-3">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-2">
                                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                                    <div className="flex-1" />
                                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                                </div>
                            ))}
                        </div>
                    ) : departments.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <BarChart3 className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No training data for {filterYear}</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Programs</TableHead>
                                    <TableHead>Enrollments</TableHead>
                                    <TableHead>Attended</TableHead>
                                    <TableHead>Attendance Rate</TableHead>
                                    <TableHead>Total Cost (MYR)</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {departments.map((dept, i) => {
                                    const rate = dept.attendance_rate != null
                                        ? parseFloat(dept.attendance_rate).toFixed(1)
                                        : '-';
                                    const rateColor = parseFloat(rate) >= 80
                                        ? 'text-emerald-600'
                                        : parseFloat(rate) >= 60
                                        ? 'text-amber-600'
                                        : 'text-red-600';

                                    return (
                                        <TableRow key={i}>
                                            <TableCell className="font-medium">{dept.department_name || '-'}</TableCell>
                                            <TableCell className="text-sm">{dept.programs_count ?? 0}</TableCell>
                                            <TableCell className="text-sm">{dept.enrollments_count ?? 0}</TableCell>
                                            <TableCell className="text-sm">{dept.attended_count ?? 0}</TableCell>
                                            <TableCell>
                                                {rate !== '-' ? (
                                                    <span className={cn('text-sm font-medium', rateColor)}>
                                                        {rate}%
                                                    </span>
                                                ) : (
                                                    <span className="text-sm text-zinc-400">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {formatCurrency(dept.total_cost)}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
