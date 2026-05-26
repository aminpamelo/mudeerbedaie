import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    Download,
    FileText,
    Loader2,
    Wallet,
    Receipt,
    ChevronRight,
    Sparkles,
} from 'lucide-react';
import {
    Card,
    CardContent,
} from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';
import { EmployeePageHeader } from '../../components/ui/employee-page-header';
import { RecordCard, RecordList } from '../../components/ui/record-card';
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

function formatCurrencyShort(amount) {
    if (amount == null) return 'RM 0';
    const n = parseFloat(amount);
    return `RM ${n.toLocaleString('en-MY', { maximumFractionDigits: 0 })}`;
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

    const latestPayslip = payslips[0];

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
        <div className="space-y-5 pb-4">
            <EmployeePageHeader
                icon={Wallet}
                accent="emerald"
                title="My Payslips"
                context={`YTD ${currentYear}`}
            />

            {/* Hero: Latest payslip card with celebratory gradient */}
            {latestPayslip ? (
                <div className="relative overflow-hidden rounded-3xl border border-emerald-200/60 bg-gradient-to-br from-emerald-50 via-teal-50 to-sky-50 p-5 shadow-md shadow-emerald-200/20">
                    <div className="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-emerald-300/30 blur-3xl hr-float" aria-hidden />
                    <div className="absolute -left-12 -bottom-12 h-36 w-36 rounded-full bg-sky-300/30 blur-3xl hr-float-delayed" aria-hidden />
                    <Sparkles className="absolute right-3 top-3 h-4 w-4 text-amber-400 hr-twinkle" aria-hidden />

                    <div className="relative">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-emerald-700">
                            Latest payslip · {MONTHS[latestPayslip.month - 1]} {latestPayslip.year}
                        </p>
                        <p className="mt-2 text-[40px] font-bold tabular-nums leading-none tracking-tight text-slate-900">
                            {formatCurrency(latestPayslip.net_salary)}
                        </p>
                        <p className="mt-1 text-xs font-medium text-slate-600">Net pay</p>

                        <div className="mt-4 flex flex-wrap items-center gap-2">
                            <div className="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 text-[11px] font-semibold text-slate-700 backdrop-blur-sm ring-1 ring-white/80">
                                Gross <span className="tabular-nums">{formatCurrencyShort(latestPayslip.gross_salary)}</span>
                            </div>
                            <div className="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 text-[11px] font-semibold text-rose-700 backdrop-blur-sm ring-1 ring-white/80">
                                – <span className="tabular-nums">{formatCurrencyShort(latestPayslip.total_deductions)}</span> deductions
                            </div>
                            <button
                                onClick={() => handleDownload(latestPayslip)}
                                disabled={downloadingId === latestPayslip.id}
                                className="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white shadow-md shadow-pink-500/30 transition-all hover:shadow-lg disabled:opacity-60"
                            >
                                {downloadingId === latestPayslip.id ? (
                                    <Loader2 className="h-3 w-3 animate-spin" />
                                ) : (
                                    <Download className="h-3 w-3" strokeWidth={2.5} />
                                )}
                                Download PDF
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}

            {/* YTD Stats */}
            <div className="grid grid-cols-3 gap-2">
                <div className="rounded-2xl border border-sky-100 bg-gradient-to-br from-sky-50 to-sky-50/40 p-3 text-center">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-sky-700">YTD Gross</p>
                    <p className="mt-1 text-sm font-bold tabular-nums text-slate-900">{formatCurrencyShort(ytd.ytd_gross)}</p>
                </div>
                <div className="rounded-2xl border border-rose-100 bg-gradient-to-br from-rose-50 to-rose-50/40 p-3 text-center">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-rose-700">YTD Deductions</p>
                    <p className="mt-1 text-sm font-bold tabular-nums text-slate-900">{formatCurrencyShort(ytd.ytd_deductions)}</p>
                </div>
                <div className="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-emerald-50/40 p-3 text-center">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-emerald-700">YTD Net</p>
                    <p className="mt-1 text-sm font-bold tabular-nums text-slate-900">{formatCurrencyShort(ytd.ytd_net)}</p>
                </div>
            </div>

            {/* Year filter as segmented pill */}
            <div className="flex items-center justify-center">
                <div className="inline-flex rounded-full border border-slate-200 bg-white p-1 shadow-sm">
                    {[currentYear - 2, currentYear - 1, currentYear].map((y) => (
                        <button
                            key={y}
                            onClick={() => setFilterYear(y)}
                            aria-pressed={filterYear === y}
                            className={cn(
                                'rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-wider transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                                filterYear === y
                                    ? 'bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 text-white shadow-md shadow-pink-500/30'
                                    : 'text-slate-500 hover:text-slate-700'
                            )}
                        >
                            {y}
                        </button>
                    ))}
                </div>
            </div>

            {/* Payslips list */}
            <div>
                <div className="mb-2 flex items-center justify-between px-1">
                    <h3 className="text-sm font-bold text-slate-900">Payslips · {filterYear}</h3>
                    <span className="text-[11px] font-semibold text-slate-500">
                        <span className="tabular-nums text-slate-800">{payslips.length}</span> total
                    </span>
                </div>
                <RecordList
                    items={payslips}
                    isLoading={isLoading}
                    emptyIcon={FileText}
                    emptyAccent="slate"
                    emptyTitle={`No payslips for ${filterYear}`}
                    emptyDescription="Payslips appear after payroll is finalized"
                    renderItem={(payslip) => (
                        <RecordCard
                            key={payslip.id}
                            icon={Receipt}
                            accent="emerald"
                            title={`${MONTHS[payslip.month - 1]} ${payslip.year}`}
                            subtitle={`Net ${formatCurrency(payslip.net_salary)}`}
                            meta={`Gross ${formatCurrencyShort(payslip.gross_salary)} · Deductions ${formatCurrencyShort(payslip.total_deductions)}`}
                            onClick={() => openDetail(payslip)}
                        >
                            <div className="mt-2 flex justify-end gap-1.5">
                                <button
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        handleDownload(payslip);
                                    }}
                                    disabled={downloadingId === payslip.id}
                                    className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-700 ring-1 ring-slate-200 transition-colors hover:bg-slate-200 disabled:opacity-50"
                                >
                                    {downloadingId === payslip.id ? (
                                        <Loader2 className="h-3 w-3 animate-spin" />
                                    ) : (
                                        <Download className="h-3 w-3" strokeWidth={2.5} />
                                    )}
                                    PDF
                                </button>
                            </div>
                        </RecordCard>
                    )}
                />
            </div>

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
                                <div key={i} className="h-4 w-full animate-pulse rounded bg-slate-200" />
                            ))}
                        </div>
                    ) : detail ? (
                        <div className="space-y-4">
                            {/* Earnings */}
                            <div>
                                <p className="mb-2 text-xs font-bold uppercase tracking-wider text-emerald-700">Earnings</p>
                                <div className="space-y-1">
                                    {(detail.items || [])
                                        .filter((item) => item.type === 'earning')
                                        .map((item, i) => (
                                            <div key={i} className="flex justify-between text-sm">
                                                <span className="text-slate-700">{item.component_name}</span>
                                                <span className="font-medium tabular-nums">{formatCurrency(item.amount)}</span>
                                            </div>
                                        ))}
                                    <div className="flex justify-between border-t border-slate-200 pt-1 text-sm font-bold">
                                        <span>Gross Pay</span>
                                        <span className="tabular-nums">{formatCurrency(detail.gross_salary)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Statutory Deductions */}
                            <div>
                                <p className="mb-2 text-xs font-bold uppercase tracking-wider text-rose-700">Statutory Deductions</p>
                                <div className="space-y-1 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-slate-700">EPF (Employee 11%)</span>
                                        <span className="text-rose-600 tabular-nums">{formatCurrency(detail.epf_employee)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-700">SOCSO</span>
                                        <span className="text-rose-600 tabular-nums">{formatCurrency(detail.socso_employee)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-700">EIS</span>
                                        <span className="text-rose-600 tabular-nums">{formatCurrency(detail.eis_employee)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-700">PCB (Income Tax)</span>
                                        <span className="text-rose-600 tabular-nums">{formatCurrency(detail.pcb_amount)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Other Deductions */}
                            {(detail.items || []).filter((item) => item.type === 'deduction' && !item.is_statutory).length > 0 && (
                                <div>
                                    <p className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-600">Other Deductions</p>
                                    <div className="space-y-1 text-sm">
                                        {(detail.items || [])
                                            .filter((item) => item.type === 'deduction' && !item.is_statutory)
                                            .map((item, i) => (
                                                <div key={i} className="flex justify-between">
                                                    <span className="text-slate-700">{item.component_name}</span>
                                                    <span className="text-rose-600 tabular-nums">{formatCurrency(item.amount)}</span>
                                                </div>
                                            ))}
                                    </div>
                                </div>
                            )}

                            {/* Net Pay highlight */}
                            <div className="rounded-2xl bg-gradient-to-br from-emerald-100 to-emerald-50 p-4 ring-1 ring-emerald-200">
                                <div className="flex items-baseline justify-between">
                                    <span className="text-sm font-bold uppercase tracking-wider text-emerald-800">Net Pay</span>
                                    <span className="text-xl font-bold tabular-nums text-emerald-900">{formatCurrency(detail.net_salary)}</span>
                                </div>
                            </div>

                            {/* Employer Contributions */}
                            <div className="rounded-2xl bg-slate-50 p-3">
                                <p className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">Employer Contributions (not from you)</p>
                                <div className="space-y-1 text-sm text-slate-600">
                                    <div className="flex justify-between">
                                        <span>EPF (Employer 13%)</span>
                                        <span className="tabular-nums">{formatCurrency(detail.epf_employer)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>SOCSO (Employer)</span>
                                        <span className="tabular-nums">{formatCurrency(detail.socso_employer)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>EIS (Employer)</span>
                                        <span className="tabular-nums">{formatCurrency(detail.eis_employer)}</span>
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
