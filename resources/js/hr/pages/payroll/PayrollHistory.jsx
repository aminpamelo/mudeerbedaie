import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    Eye,
    DollarSign,
    Filter,
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
import PageHeader from '../../components/PageHeader';
import { cn } from '../../lib/utils';
import { fetchPayrollRuns } from '../../lib/api';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

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

function PayrollStatusBadge({ status }) {
    const config = STATUS_CONFIG[status] || { label: status, bg: 'bg-zinc-100', text: 'text-zinc-700' };
    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', config.bg, config.text)}>
            {config.label}
        </span>
    );
}

export default function PayrollHistory() {
    const currentYear = new Date().getFullYear();
    const [filterYear, setFilterYear] = useState(currentYear);
    const [filterStatus, setFilterStatus] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'payroll', 'runs', 'history', filterYear, filterStatus],
        queryFn: () => fetchPayrollRuns({
            year: filterYear || undefined,
            status: filterStatus || undefined,
            per_page: 50,
        }),
    });

    const runs = data?.data || [];

    return (
        <div className="space-y-6">
            <PageHeader
                title="Payroll History"
                description="All payroll runs by year and status"
            />

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <Filter className="h-4 w-4 text-zinc-400" />
                        <select
                            value={filterYear}
                            onChange={(e) => setFilterYear(parseInt(e.target.value))}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            <option value="">All Years</option>
                            {[currentYear - 2, currentYear - 1, currentYear, currentYear + 1].map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                        <select
                            value={filterStatus}
                            onChange={(e) => setFilterStatus(e.target.value)}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            <option value="">All Statuses</option>
                            {Object.entries(STATUS_CONFIG).map(([key, val]) => (
                                <option key={key} value={key}>{val.label}</option>
                            ))}
                        </select>
                        {(filterStatus) && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setFilterStatus('')}
                            >
                                Clear
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Runs Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Payroll Runs</CardTitle>
                    <CardDescription>{runs.length} run(s) found</CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="space-y-3">
                            {Array.from({ length: 8 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-3">
                                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-16 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-28 animate-pulse rounded bg-zinc-200" />
                                    <div className="flex-1" />
                                    <div className="h-6 w-20 animate-pulse rounded-full bg-zinc-200" />
                                </div>
                            ))}
                        </div>
                    ) : runs.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <DollarSign className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No payroll runs found</p>
                            <p className="text-xs text-zinc-400">Try adjusting your filters</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Period</TableHead>
                                    <TableHead>Employees</TableHead>
                                    <TableHead>Total Gross</TableHead>
                                    <TableHead>Total Deductions</TableHead>
                                    <TableHead>Total Net</TableHead>
                                    <TableHead>Employer Cost</TableHead>
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
                                        <TableCell>{formatCurrency(run.total_deductions)}</TableCell>
                                        <TableCell className="font-medium text-emerald-600">{formatCurrency(run.total_net)}</TableCell>
                                        <TableCell>{formatCurrency(run.total_employer_cost)}</TableCell>
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
        </div>
    );
}
