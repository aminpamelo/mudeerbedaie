import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    PieChart,
    Pie,
    Cell,
    Legend,
} from 'recharts';
import {
    DollarSign,
    TrendingDown,
    Wallet,
    Building2,
    Plus,
    Play,
    Eye,
    Loader2,
    CheckCircle,
    Clock,
    AlertCircle,
} from 'lucide-react';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
} from '../../components/ui/card';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';
import PageHeader from '../../components/PageHeader';
import { cn } from '../../lib/utils';
import {
    fetchPayrollDashboardStats,
    fetchPayrollTrend,
    fetchStatutoryBreakdown,
    fetchPayrollRuns,
    createPayrollRun,
} from '../../lib/api';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const STATUTORY_COLORS = ['#2563eb', '#10b981', '#f59e0b', '#ef4444'];

const STATUS_CONFIG = {
    draft: { label: 'Draft', bg: 'bg-zinc-100', text: 'text-zinc-700' },
    calculating: { label: 'Calculating', bg: 'bg-blue-100', text: 'text-blue-700' },
    pending_review: { label: 'Pending Review', bg: 'bg-amber-100', text: 'text-amber-700' },
    approved: { label: 'Approved', bg: 'bg-emerald-100', text: 'text-emerald-700' },
    finalized: { label: 'Finalized', bg: 'bg-purple-100', text: 'text-purple-700' },
};

function formatCurrency(amount) {
    if (amount == null) return 'RM 0.00';
    return `RM ${parseFloat(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' });
}

function StatCard({ title, value, icon: Icon, iconColor, iconBg, subtitle }) {
    return (
        <Card className="transition-shadow hover:shadow-md">
            <CardContent className="p-6">
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <p className="text-sm font-medium text-zinc-500">{title}</p>
                        <p className="text-2xl font-bold tracking-tight text-zinc-900">{value}</p>
                        {subtitle && <p className="text-xs text-zinc-400">{subtitle}</p>}
                    </div>
                    <div className={cn('flex h-12 w-12 items-center justify-center rounded-lg', iconBg)}>
                        <Icon className={cn('h-6 w-6', iconColor)} />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function SkeletonCard() {
    return (
        <Card>
            <CardContent className="p-6">
                <div className="flex items-center justify-between">
                    <div className="space-y-3">
                        <div className="h-3 w-24 animate-pulse rounded bg-zinc-200" />
                        <div className="h-8 w-32 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-12 w-12 animate-pulse rounded-lg bg-zinc-200" />
                </div>
            </CardContent>
        </Card>
    );
}

function PayrollStatusBadge({ status }) {
    const config = STATUS_CONFIG[status] || { label: status, bg: 'bg-zinc-100', text: 'text-zinc-700' };
    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', config.bg, config.text)}>
            {config.label}
        </span>
    );
}

function CustomTooltip({ active, payload, label }) {
    if (!active || !payload?.length) return null;
    return (
        <div className="rounded-lg border border-zinc-200 bg-white px-3 py-2 shadow-lg">
            <p className="text-sm font-medium text-zinc-900">{label}</p>
            {payload.map((entry) => (
                <p key={entry.name} className="text-sm text-zinc-500">
                    {entry.name}: {formatCurrency(entry.value)}
                </p>
            ))}
        </div>
    );
}

export default function PayrollDashboard() {
    const queryClient = useQueryClient();
    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().getMonth() + 1;

    const [createDialog, setCreateDialog] = useState(false);
    const [newRun, setNewRun] = useState({ month: currentMonth, year: currentYear });

    const { data: statsData, isLoading: statsLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'dashboard', 'stats'],
        queryFn: fetchPayrollDashboardStats,
    });

    const { data: trendData, isLoading: trendLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'dashboard', 'trend'],
        queryFn: fetchPayrollTrend,
    });

    const { data: statutoryData } = useQuery({
        queryKey: ['hr', 'payroll', 'dashboard', 'statutory'],
        queryFn: fetchStatutoryBreakdown,
    });

    const { data: runsData, isLoading: runsLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'runs'],
        queryFn: () => fetchPayrollRuns({ per_page: 10 }),
    });

    const createMutation = useMutation({
        mutationFn: createPayrollRun,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'payroll'] });
            setCreateDialog(false);
        },
    });

    const stats = statsData?.data || {};
    const trend = trendData?.data || [];
    const statutory = statutoryData?.data || [];
    const runs = runsData?.data || [];

    return (
        <div className="space-y-6">
            <PageHeader
                title="Payroll Dashboard"
                description="Overview of payroll runs, costs, and statutory contributions"
                action={
                    <Button onClick={() => setCreateDialog(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        New Payroll Run
                    </Button>
                }
            />

            {/* Stats Cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {statsLoading ? (
                    <>
                        <SkeletonCard />
                        <SkeletonCard />
                        <SkeletonCard />
                        <SkeletonCard />
                    </>
                ) : (
                    <>
                        <StatCard
                            title="Total Gross (Last Run)"
                            value={formatCurrency(stats.last_total_gross)}
                            subtitle={stats.last_run_period}
                            icon={DollarSign}
                            iconColor="text-blue-600"
                            iconBg="bg-blue-50"
                        />
                        <StatCard
                            title="Total Deductions"
                            value={formatCurrency(stats.last_total_deductions)}
                            subtitle="statutory + other"
                            icon={TrendingDown}
                            iconColor="text-red-600"
                            iconBg="bg-red-50"
                        />
                        <StatCard
                            title="Total Net Pay"
                            value={formatCurrency(stats.last_total_net)}
                            subtitle="take-home pay"
                            icon={Wallet}
                            iconColor="text-emerald-600"
                            iconBg="bg-emerald-50"
                        />
                        <StatCard
                            title="Employer Cost"
                            value={formatCurrency(stats.last_total_employer_cost)}
                            subtitle="net + employer contributions"
                            icon={Building2}
                            iconColor="text-purple-600"
                            iconBg="bg-purple-50"
                        />
                    </>
                )}
            </div>

            {/* Charts */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {trendLoading ? (
                    <Card className="lg:col-span-2">
                        <CardContent className="p-6">
                            <div className="h-[280px] animate-pulse rounded bg-zinc-100" />
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Monthly Payroll Trend</CardTitle>
                            <CardDescription>Gross, deductions, and net pay over time</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {trend.length === 0 ? (
                                <div className="flex h-[280px] items-center justify-center text-sm text-zinc-400">
                                    No trend data available
                                </div>
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <BarChart data={trend} margin={{ top: 5, right: 20, left: 20, bottom: 5 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
                                        <XAxis dataKey="period" tick={{ fontSize: 11, fill: '#71717a' }} />
                                        <YAxis tick={{ fontSize: 11, fill: '#71717a' }} tickFormatter={(v) => `RM${(v / 1000).toFixed(0)}k`} />
                                        <Tooltip content={<CustomTooltip />} />
                                        <Legend />
                                        <Bar dataKey="total_gross" name="Gross" fill="#2563eb" radius={[2, 2, 0, 0]} />
                                        <Bar dataKey="total_deductions" name="Deductions" fill="#ef4444" radius={[2, 2, 0, 0]} />
                                        <Bar dataKey="total_net" name="Net" fill="#10b981" radius={[2, 2, 0, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Statutory Breakdown</CardTitle>
                        <CardDescription>Employee contributions by type</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {statutory.length === 0 ? (
                            <div className="flex h-[280px] items-center justify-center text-sm text-zinc-400">
                                No statutory data available
                            </div>
                        ) : (
                            <ResponsiveContainer width="100%" height={280}>
                                <PieChart>
                                    <Pie
                                        data={statutory}
                                        cx="50%"
                                        cy="45%"
                                        outerRadius={90}
                                        dataKey="amount"
                                        nameKey="label"
                                        label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                                        labelLine={false}
                                    >
                                        {statutory.map((entry, i) => (
                                            <Cell key={entry.label} fill={STATUTORY_COLORS[i % STATUTORY_COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(v) => formatCurrency(v)} />
                                </PieChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Recent Runs Table */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle>Recent Payroll Runs</CardTitle>
                            <CardDescription>Latest payroll processing history</CardDescription>
                        </div>
                        <Link to="/payroll/history">
                            <Button variant="outline" size="sm">View All</Button>
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    {runsLoading ? (
                        <div className="space-y-3">
                            {Array.from({ length: 4 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-3">
                                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                                    <div className="flex-1" />
                                    <div className="h-6 w-20 animate-pulse rounded-full bg-zinc-200" />
                                </div>
                            ))}
                        </div>
                    ) : runs.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <DollarSign className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No payroll runs yet</p>
                            <p className="text-xs text-zinc-400">Create a new payroll run to get started</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Period</TableHead>
                                    <TableHead>Employees</TableHead>
                                    <TableHead>Total Gross</TableHead>
                                    <TableHead>Total Net</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {runs.map((run) => (
                                    <TableRow key={run.id}>
                                        <TableCell className="font-medium">
                                            {MONTHS[run.month - 1]} {run.year}
                                        </TableCell>
                                        <TableCell>{run.employee_count ?? '-'}</TableCell>
                                        <TableCell>{formatCurrency(run.total_gross)}</TableCell>
                                        <TableCell>{formatCurrency(run.total_net)}</TableCell>
                                        <TableCell>
                                            <PayrollStatusBadge status={run.status} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Link to={`/payroll/run/${run.id}`}>
                                                <Button variant="ghost" size="sm">
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Create Payroll Run Dialog */}
            <Dialog open={createDialog} onOpenChange={setCreateDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New Payroll Run</DialogTitle>
                        <DialogDescription>
                            Select the month and year for the payroll run.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Month</label>
                            <select
                                value={newRun.month}
                                onChange={(e) => setNewRun((p) => ({ ...p, month: parseInt(e.target.value) }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            >
                                {MONTHS.map((m, i) => (
                                    <option key={i} value={i + 1}>{m}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">Year</label>
                            <select
                                value={newRun.year}
                                onChange={(e) => setNewRun((p) => ({ ...p, year: parseInt(e.target.value) }))}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            >
                                {[currentYear - 1, currentYear, currentYear + 1].map((y) => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCreateDialog(false)}>Cancel</Button>
                        <Button
                            onClick={() => createMutation.mutate(newRun)}
                            disabled={createMutation.isPending}
                        >
                            {createMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            Create Run
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
