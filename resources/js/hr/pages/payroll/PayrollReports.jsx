import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    Download,
    BarChart3,
    FileText,
    Loader2,
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
import {
    fetchPayrollMonthlySummary,
    fetchPayrollStatutoryReport,
    fetchPayrollBankPayment,
    fetchPayrollYtd,
} from '../../lib/api';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const REPORT_TABS = [
    { key: 'monthly_summary', label: 'Monthly Summary', icon: BarChart3 },
    { key: 'statutory', label: 'Statutory Contributions', icon: FileText },
    { key: 'bank_payment', label: 'Bank Payment List', icon: FileText },
    { key: 'ytd', label: 'Year-to-Date', icon: BarChart3 },
];

function formatCurrency(amount) {
    if (amount == null) return 'RM 0.00';
    return `RM ${parseFloat(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function downloadBlob(data, filename) {
    const url = window.URL.createObjectURL(new Blob([data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}

export default function PayrollReports() {
    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().getMonth() + 1;

    const [activeTab, setActiveTab] = useState('monthly_summary');
    const [filterYear, setFilterYear] = useState(currentYear);
    const [filterMonth, setFilterMonth] = useState(currentMonth);
    const [downloadLoading, setDownloadLoading] = useState(false);

    const params = { year: filterYear, month: filterMonth };

    const monthlySummaryQuery = useQuery({
        queryKey: ['hr', 'payroll', 'reports', 'monthly', filterYear, filterMonth],
        queryFn: () => fetchPayrollMonthlySummary(params),
        enabled: activeTab === 'monthly_summary',
    });

    const statutoryQuery = useQuery({
        queryKey: ['hr', 'payroll', 'reports', 'statutory', filterYear, filterMonth],
        queryFn: () => fetchPayrollStatutoryReport(params),
        enabled: activeTab === 'statutory',
    });

    const bankPaymentQuery = useQuery({
        queryKey: ['hr', 'payroll', 'reports', 'bank', filterYear, filterMonth],
        queryFn: () => fetchPayrollBankPayment(params),
        enabled: activeTab === 'bank_payment',
    });

    const ytdQuery = useQuery({
        queryKey: ['hr', 'payroll', 'reports', 'ytd', filterYear],
        queryFn: () => fetchPayrollYtd({ year: filterYear }),
        enabled: activeTab === 'ytd',
    });

    const activeQuery = {
        monthly_summary: monthlySummaryQuery,
        statutory: statutoryQuery,
        bank_payment: bankPaymentQuery,
        ytd: ytdQuery,
    }[activeTab];

    const reportData = activeQuery?.data?.data || [];
    const isLoading = activeQuery?.isLoading;

    async function handleExport() {
        setDownloadLoading(true);
        try {
            const exportFn = {
                monthly_summary: () => fetchPayrollMonthlySummary({ ...params, format: 'csv' }),
                statutory: () => fetchPayrollStatutoryReport({ ...params, format: 'csv' }),
                bank_payment: () => fetchPayrollBankPayment({ ...params, format: 'csv' }),
                ytd: () => fetchPayrollYtd({ year: filterYear, format: 'csv' }),
            }[activeTab];
            const data = await exportFn();
            downloadBlob(data, `payroll-${activeTab}-${filterYear}-${filterMonth}.csv`);
        } catch (e) {
            console.error('Export failed', e);
        } finally {
            setDownloadLoading(false);
        }
    }

    function renderTable() {
        if (isLoading) {
            return (
                <div className="space-y-3 p-6">
                    {Array.from({ length: 6 }).map((_, i) => (
                        <div key={i} className="flex items-center gap-4 py-2">
                            <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                            <div className="h-4 w-28 animate-pulse rounded bg-zinc-200" />
                            <div className="flex-1" />
                            <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                        </div>
                    ))}
                </div>
            );
        }

        if (reportData.length === 0) {
            return (
                <div className="flex flex-col items-center justify-center py-12 text-center">
                    <BarChart3 className="mb-3 h-10 w-10 text-zinc-300" />
                    <p className="text-sm font-medium text-zinc-500">No data available for the selected period</p>
                </div>
            );
        }

        if (activeTab === 'monthly_summary') {
            return (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Employee</TableHead>
                            <TableHead>Department</TableHead>
                            <TableHead>Gross Pay</TableHead>
                            <TableHead>EPF (EE)</TableHead>
                            <TableHead>SOCSO (EE)</TableHead>
                            <TableHead>EIS (EE)</TableHead>
                            <TableHead>PCB</TableHead>
                            <TableHead>Net Pay</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {reportData.map((row, i) => (
                            <TableRow key={i}>
                                <TableCell className="font-medium">{row.employee_name}</TableCell>
                                <TableCell className="text-sm text-zinc-600">{row.department}</TableCell>
                                <TableCell>{formatCurrency(row.gross_pay)}</TableCell>
                                <TableCell>{formatCurrency(row.epf_employee)}</TableCell>
                                <TableCell>{formatCurrency(row.socso_employee)}</TableCell>
                                <TableCell>{formatCurrency(row.eis_employee)}</TableCell>
                                <TableCell>{formatCurrency(row.pcb)}</TableCell>
                                <TableCell className="font-medium text-emerald-600">{formatCurrency(row.net_pay)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            );
        }

        if (activeTab === 'statutory') {
            return (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Employee</TableHead>
                            <TableHead>EPF (EE)</TableHead>
                            <TableHead>EPF (ER)</TableHead>
                            <TableHead>SOCSO (EE)</TableHead>
                            <TableHead>SOCSO (ER)</TableHead>
                            <TableHead>EIS (EE)</TableHead>
                            <TableHead>EIS (ER)</TableHead>
                            <TableHead>PCB</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {reportData.map((row, i) => (
                            <TableRow key={i}>
                                <TableCell className="font-medium">{row.employee_name}</TableCell>
                                <TableCell>{formatCurrency(row.epf_employee)}</TableCell>
                                <TableCell>{formatCurrency(row.epf_employer)}</TableCell>
                                <TableCell>{formatCurrency(row.socso_employee)}</TableCell>
                                <TableCell>{formatCurrency(row.socso_employer)}</TableCell>
                                <TableCell>{formatCurrency(row.eis_employee)}</TableCell>
                                <TableCell>{formatCurrency(row.eis_employer)}</TableCell>
                                <TableCell>{formatCurrency(row.pcb)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            );
        }

        if (activeTab === 'bank_payment') {
            return (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Employee</TableHead>
                            <TableHead>Bank</TableHead>
                            <TableHead>Account Number</TableHead>
                            <TableHead>Net Pay</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {reportData.map((row, i) => (
                            <TableRow key={i}>
                                <TableCell className="font-medium">{row.employee_name}</TableCell>
                                <TableCell className="text-sm text-zinc-600">{row.bank_name || '-'}</TableCell>
                                <TableCell className="font-mono text-sm">{row.account_number || '-'}</TableCell>
                                <TableCell className="font-medium text-emerald-600">{formatCurrency(row.net_pay)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            );
        }

        if (activeTab === 'ytd') {
            return (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Employee</TableHead>
                            <TableHead>YTD Gross</TableHead>
                            <TableHead>YTD EPF (EE)</TableHead>
                            <TableHead>YTD SOCSO</TableHead>
                            <TableHead>YTD PCB</TableHead>
                            <TableHead>YTD Net</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {reportData.map((row, i) => (
                            <TableRow key={i}>
                                <TableCell className="font-medium">{row.employee_name}</TableCell>
                                <TableCell>{formatCurrency(row.ytd_gross)}</TableCell>
                                <TableCell>{formatCurrency(row.ytd_epf_employee)}</TableCell>
                                <TableCell>{formatCurrency(row.ytd_socso_employee)}</TableCell>
                                <TableCell>{formatCurrency(row.ytd_pcb)}</TableCell>
                                <TableCell className="font-medium text-emerald-600">{formatCurrency(row.ytd_net)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            );
        }

        return null;
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Payroll Reports"
                description="Generate and export payroll reports by period"
            />

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <select
                            value={filterYear}
                            onChange={(e) => setFilterYear(parseInt(e.target.value))}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            {[currentYear - 1, currentYear, currentYear + 1].map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                        {activeTab !== 'ytd' && (
                            <select
                                value={filterMonth}
                                onChange={(e) => setFilterMonth(parseInt(e.target.value))}
                                className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                            >
                                {MONTHS.map((m, i) => (
                                    <option key={i} value={i + 1}>{m}</option>
                                ))}
                            </select>
                        )}
                        <Button variant="outline" size="sm" onClick={handleExport} disabled={downloadLoading}>
                            {downloadLoading ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Download className="mr-2 h-4 w-4" />
                            )}
                            Export CSV
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Report Tabs */}
            <div className="border-b border-zinc-200">
                <nav className="flex gap-4">
                    {REPORT_TABS.map((tab) => (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key)}
                            className={`pb-3 text-sm font-medium transition-colors ${
                                activeTab === tab.key
                                    ? 'border-b-2 border-zinc-900 text-zinc-900'
                                    : 'text-zinc-500 hover:text-zinc-700'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Report Table */}
            <Card>
                <CardHeader>
                    <CardTitle>{REPORT_TABS.find((t) => t.key === activeTab)?.label}</CardTitle>
                    <CardDescription>
                        {activeTab === 'ytd' ? `Year ${filterYear}` : `${MONTHS[filterMonth - 1]} ${filterYear}`}
                    </CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    {renderTable()}
                </CardContent>
            </Card>
        </div>
    );
}
