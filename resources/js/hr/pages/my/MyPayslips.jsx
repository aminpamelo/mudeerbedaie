import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    Download,
    FileText,
    Loader2,
    DollarSign,
    TrendingDown,
    Wallet,
    ChevronRight,
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
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';
import { cn } from '../../lib/utils';
import { fetchMyPayslips, fetchMyPayslip, downloadMyPayslipPdf, fetchMyPayslipYtd } from '../../lib/api';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

function formatCurrency(amount) {
    if (amount == null) return 'RM 0.00';
    return `RM ${parseFloat(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function downloadBlob(data, filename) {
    const url = window.URL.createObjectURL(new Blob([data], { type: 'application/pdf' }));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}

function SummaryCard({ title, value, icon: Icon, iconColor, iconBg }) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center gap-3">
                    <div className={cn('flex h-10 w-10 items-center justify-center rounded-lg', iconBg)}>
                        <Icon className={cn('h-5 w-5', iconColor)} />
                    </div>
                    <div>
                        <p className="text-xs font-medium text-zinc-500">{title}</p>
                        <p className="text-lg font-bold text-zinc-900">{value}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function MyPayslips() {
    const currentYear = new Date().getFullYear();
    const [filterYear, setFilterYear] = useState(currentYear);
    const [selectedPayslip, setSelectedPayslip] = useState(null);
    const [detailDialog, setDetailDialog] = useState(false);
    const [downloadingId, setDownloadingId] = useState(null);

    const { data: ytdData } = useQuery({
        queryKey: ['hr', 'me', 'payslips', 'ytd'],
        queryFn: fetchMyPayslipYtd,
    });

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'me', 'payslips', filterYear],
        queryFn: () => fetchMyPayslips({ year: filterYear }),
    });

    const { data: detailData, isLoading: detailLoading } = useQuery({
        queryKey: ['hr', 'me', 'payslip', selectedPayslip?.id],
        queryFn: () => fetchMyPayslip(selectedPayslip.id),
        enabled: !!selectedPayslip && detailDialog,
    });

    const payslips = data?.data || [];
    const ytd = ytdData?.data || {};
    const detail = detailData?.data;

    async function handleDownload(payslip) {
        setDownloadingId(payslip.id);
        try {
            const blob = await downloadMyPayslipPdf(payslip.id);
            downloadBlob(blob, `payslip-${MONTHS[payslip.month - 1]}-${payslip.year}.pdf`);
        } catch (e) {
            console.error('Download failed', e);
        } finally {
            setDownloadingId(null);
        }
    }

    function openDetail(payslip) {
        setSelectedPayslip(payslip);
        setDetailDialog(true);
    }

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-zinc-900">My Payslips</h1>
                <p className="text-sm text-zinc-500">View and download your monthly payslips</p>
            </div>

            {/* YTD Summary */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <SummaryCard
                    title={`YTD Gross (${currentYear})`}
                    value={formatCurrency(ytd.ytd_gross)}
                    icon={DollarSign}
                    iconColor="text-blue-600"
                    iconBg="bg-blue-50"
                />
                <SummaryCard
                    title="YTD Deductions"
                    value={formatCurrency(ytd.ytd_deductions)}
                    icon={TrendingDown}
                    iconColor="text-red-600"
                    iconBg="bg-red-50"
                />
                <SummaryCard
                    title="YTD Net Pay"
                    value={formatCurrency(ytd.ytd_net)}
                    icon={Wallet}
                    iconColor="text-emerald-600"
                    iconBg="bg-emerald-50"
                />
            </div>

            {/* Filter */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center gap-3">
                        <label className="text-sm font-medium text-zinc-700">Year:</label>
                        <select
                            value={filterYear}
                            onChange={(e) => setFilterYear(parseInt(e.target.value))}
                            className="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm focus:border-zinc-400 focus:outline-none"
                        >
                            {[currentYear - 2, currentYear - 1, currentYear].map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                    </div>
                </CardContent>
            </Card>

            {/* Payslips Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Payslips — {filterYear}</CardTitle>
                    <CardDescription>{payslips.length} payslip(s)</CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="space-y-3 p-6">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-2">
                                    <div className="h-4 w-28 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                                    <div className="flex-1" />
                                    <div className="h-8 w-24 animate-pulse rounded bg-zinc-200" />
                                </div>
                            ))}
                        </div>
                    ) : payslips.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <FileText className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No payslips for {filterYear}</p>
                            <p className="text-xs text-zinc-400">Payslips appear after payroll is finalized</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Period</TableHead>
                                    <TableHead>Gross Pay</TableHead>
                                    <TableHead>Deductions</TableHead>
                                    <TableHead>Net Pay</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payslips.map((payslip) => (
                                    <TableRow
                                        key={payslip.id}
                                        className="cursor-pointer hover:bg-zinc-50"
                                        onClick={() => openDetail(payslip)}
                                    >
                                        <TableCell className="font-medium">
                                            {MONTHS[payslip.month - 1]} {payslip.year}
                                        </TableCell>
                                        <TableCell>{formatCurrency(payslip.gross_salary)}</TableCell>
                                        <TableCell className="text-red-600">{formatCurrency(payslip.total_deductions)}</TableCell>
                                        <TableCell className="font-semibold text-emerald-600">{formatCurrency(payslip.net_salary)}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1" onClick={(e) => e.stopPropagation()}>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleDownload(payslip)}
                                                    disabled={downloadingId === payslip.id}
                                                >
                                                    {downloadingId === payslip.id ? (
                                                        <Loader2 className="h-4 w-4 animate-spin" />
                                                    ) : (
                                                        <Download className="h-4 w-4" />
                                                    )}
                                                </Button>
                                                <ChevronRight className="h-4 w-4 text-zinc-400" />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Payslip Detail Dialog */}
            <Dialog open={detailDialog} onOpenChange={setDetailDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {selectedPayslip && `${MONTHS[selectedPayslip.month - 1]} ${selectedPayslip.year} Payslip`}
                        </DialogTitle>
                        <DialogDescription>
                            Detailed breakdown of your salary components.
                        </DialogDescription>
                    </DialogHeader>

                    {detailLoading ? (
                        <div className="space-y-2">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <div key={i} className="h-4 w-full animate-pulse rounded bg-zinc-200" />
                            ))}
                        </div>
                    ) : detail ? (
                        <div className="space-y-4">
                            {/* Earnings */}
                            <div>
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Earnings</p>
                                <div className="space-y-1">
                                    {(detail.items || [])
                                        .filter((item) => item.type === 'earning')
                                        .map((item, i) => (
                                            <div key={i} className="flex justify-between text-sm">
                                                <span className="text-zinc-700">{item.component_name}</span>
                                                <span className="font-medium">{formatCurrency(item.amount)}</span>
                                            </div>
                                        ))}
                                    <div className="flex justify-between border-t border-zinc-200 pt-1 text-sm font-semibold">
                                        <span>Gross Pay</span>
                                        <span>{formatCurrency(detail.gross_salary)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Statutory Deductions */}
                            <div>
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Statutory Deductions</p>
                                <div className="space-y-1 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-zinc-700">EPF (Employee 11%)</span>
                                        <span className="text-red-600">{formatCurrency(detail.epf_employee)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-zinc-700">SOCSO</span>
                                        <span className="text-red-600">{formatCurrency(detail.socso_employee)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-zinc-700">EIS</span>
                                        <span className="text-red-600">{formatCurrency(detail.eis_employee)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-zinc-700">PCB (Income Tax)</span>
                                        <span className="text-red-600">{formatCurrency(detail.pcb_amount)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Other Deductions */}
                            {(detail.items || []).filter((item) => item.type === 'deduction' && !item.is_statutory).length > 0 && (
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Other Deductions</p>
                                    <div className="space-y-1 text-sm">
                                        {(detail.items || [])
                                            .filter((item) => item.type === 'deduction' && !item.is_statutory)
                                            .map((item, i) => (
                                                <div key={i} className="flex justify-between">
                                                    <span className="text-zinc-700">{item.component_name}</span>
                                                    <span className="text-red-600">{formatCurrency(item.amount)}</span>
                                                </div>
                                            ))}
                                    </div>
                                </div>
                            )}

                            {/* Net Pay */}
                            <div className="rounded-lg bg-emerald-50 p-3">
                                <div className="flex justify-between text-base font-bold text-emerald-800">
                                    <span>Net Pay</span>
                                    <span>{formatCurrency(detail.net_salary)}</span>
                                </div>
                            </div>

                            {/* Employer Contributions */}
                            <div className="rounded-lg bg-zinc-50 p-3">
                                <p className="mb-2 text-xs font-semibold text-zinc-500">Employer Contributions (Not deducted from you)</p>
                                <div className="space-y-1 text-sm text-zinc-600">
                                    <div className="flex justify-between">
                                        <span>EPF (Employer 13%)</span>
                                        <span>{formatCurrency(detail.epf_employer)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>SOCSO (Employer)</span>
                                        <span>{formatCurrency(detail.socso_employer)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>EIS (Employer)</span>
                                        <span>{formatCurrency(detail.eis_employer)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ) : null}

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDetailDialog(false)}>Close</Button>
                        {selectedPayslip && (
                            <Button
                                onClick={() => handleDownload(selectedPayslip)}
                                disabled={downloadingId === selectedPayslip?.id}
                            >
                                {downloadingId === selectedPayslip?.id ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <Download className="mr-2 h-4 w-4" />
                                )}
                                Download PDF
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
