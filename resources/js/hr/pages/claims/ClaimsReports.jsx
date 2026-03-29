import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Download, BarChart3, Loader2 } from 'lucide-react';
import {
    fetchClaimsReport,
    exportClaimsReport,
    fetchEmployees,
    fetchClaimTypes,
} from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';

const CURRENT_YEAR = new Date().getFullYear();
const YEARS = Array.from({ length: 5 }, (_, i) => CURRENT_YEAR - i);
const MONTHS = [
    { value: 'all', label: 'All Months' },
    { value: '1', label: 'January' },
    { value: '2', label: 'February' },
    { value: '3', label: 'March' },
    { value: '4', label: 'April' },
    { value: '5', label: 'May' },
    { value: '6', label: 'June' },
    { value: '7', label: 'July' },
    { value: '8', label: 'August' },
    { value: '9', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

function formatCurrency(amount) {
    if (amount === null || amount === undefined) return 'MYR 0.00';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

export default function ClaimsReports() {
    const [year, setYear] = useState(String(CURRENT_YEAR));
    const [month, setMonth] = useState('all');
    const [employeeId, setEmployeeId] = useState('all');
    const [claimTypeId, setClaimTypeId] = useState('all');

    const params = {
        year: year || undefined,
        month: month !== 'all' ? month : undefined,
        employee_id: employeeId !== 'all' ? employeeId : undefined,
        claim_type_id: claimTypeId !== 'all' ? claimTypeId : undefined,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'claims', 'reports', params],
        queryFn: () => fetchClaimsReport(params),
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'list'],
        queryFn: () => fetchEmployees({ per_page: 200 }),
    });

    const { data: claimTypesData } = useQuery({
        queryKey: ['hr', 'claims', 'types', 'list'],
        queryFn: () => fetchClaimTypes({ per_page: 100 }),
    });

    const exportMutation = useMutation({
        mutationFn: () => exportClaimsReport(params),
        onSuccess: (blob) => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `claims-report-${year}${month ? `-${month}` : ''}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        },
    });

    const report = data?.data || {};
    const summaryByType = report.by_type || [];
    const summaryByEmployee = report.by_employee || [];
    const totals = report.totals || {};
    const employees = employeesData?.data || [];
    const claimTypes = claimTypesData?.data || [];

    return (
        <div>
            <PageHeader
                title="Claims Reports"
                description="Analyse expense claims by type, employee, and time period."
            />

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-end gap-4">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-zinc-600">Year</label>
                            <Select value={year} onValueChange={setYear}>
                                <SelectTrigger className="w-28">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {YEARS.map((y) => (
                                        <SelectItem key={y} value={String(y)}>{y}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-zinc-600">Month</label>
                            <Select value={month} onValueChange={setMonth}>
                                <SelectTrigger className="w-36">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {MONTHS.map((m) => (
                                        <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-zinc-600">Employee</label>
                            <Select value={employeeId} onValueChange={setEmployeeId}>
                                <SelectTrigger className="w-48">
                                    <SelectValue placeholder="All Employees" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Employees</SelectItem>
                                    {employees.map((emp) => (
                                        <SelectItem key={emp.id} value={String(emp.id)}>
                                            {emp.full_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-zinc-600">Claim Type</label>
                            <Select value={claimTypeId} onValueChange={setClaimTypeId}>
                                <SelectTrigger className="w-44">
                                    <SelectValue placeholder="All Types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    {claimTypes.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>{t.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => exportMutation.mutate()}
                            disabled={exportMutation.isPending}
                        >
                            {exportMutation.isPending ? (
                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                            ) : (
                                <Download className="mr-1.5 h-4 w-4" />
                            )}
                            Export CSV
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {isLoading ? (
                <div className="flex justify-center py-16">
                    <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                </div>
            ) : (
                <div className="space-y-6">
                    {/* Summary Cards */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <Card>
                            <CardContent className="p-5">
                                <p className="text-sm text-zinc-500">Total Claims</p>
                                <p className="mt-1 text-2xl font-bold text-zinc-900">{totals.count ?? 0}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-5">
                                <p className="text-sm text-zinc-500">Total Amount</p>
                                <p className="mt-1 text-2xl font-bold text-zinc-900">{formatCurrency(totals.amount)}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="p-5">
                                <p className="text-sm text-zinc-500">Approved Amount</p>
                                <p className="mt-1 text-2xl font-bold text-emerald-700">{formatCurrency(totals.approved_amount)}</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* By Type */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 text-base font-semibold text-zinc-900">By Claim Type</h3>
                            {summaryByType.length === 0 ? (
                                <div className="flex items-center justify-center py-8 text-sm text-zinc-400">
                                    No data for selected period
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Count</TableHead>
                                            <TableHead>Total Amount</TableHead>
                                            <TableHead>Approved</TableHead>
                                            <TableHead>Pending</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {summaryByType.map((row, i) => (
                                            <TableRow key={i}>
                                                <TableCell className="font-medium">{row.name}</TableCell>
                                                <TableCell>{row.count}</TableCell>
                                                <TableCell>{formatCurrency(row.total_amount)}</TableCell>
                                                <TableCell className="text-emerald-700">{formatCurrency(row.approved_amount)}</TableCell>
                                                <TableCell className="text-amber-700">{formatCurrency(row.pending_amount)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    {/* By Employee */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 text-base font-semibold text-zinc-900">By Employee</h3>
                            {summaryByEmployee.length === 0 ? (
                                <div className="flex items-center justify-center py-8 text-sm text-zinc-400">
                                    No data for selected period
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Employee</TableHead>
                                            <TableHead>Department</TableHead>
                                            <TableHead>Claims Count</TableHead>
                                            <TableHead>Total Amount</TableHead>
                                            <TableHead>Approved Amount</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {summaryByEmployee.map((row, i) => (
                                            <TableRow key={i}>
                                                <TableCell className="font-medium">{row.employee_name}</TableCell>
                                                <TableCell className="text-sm text-zinc-500">{row.department}</TableCell>
                                                <TableCell>{row.count}</TableCell>
                                                <TableCell>{formatCurrency(row.total_amount)}</TableCell>
                                                <TableCell className="text-emerald-700">{formatCurrency(row.approved_amount)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}
        </div>
    );
}
