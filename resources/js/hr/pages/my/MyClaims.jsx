import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Trash2,
    Receipt,
    Loader2,
    ChevronLeft,
    ChevronRight,
    Upload,
    Paperclip,
    Car,
    MapPin,
    Route,
    Calculator,
    Send,
} from 'lucide-react';
import {
    fetchMyClaims,
    fetchMyClaimLimits,
    createMyClaim,
    submitMyClaim,
    deleteMyClaim,
    fetchClaimTypes,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import { Button } from '../../components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';

// ── Design tokens ────────────────────────────────────────────────────────────

const PALETTE = [
    { bg: '#fef2f2', accent: '#ef4444', text: '#dc2626', track: '#fecaca' },
    { bg: '#eff6ff', accent: '#3b82f6', text: '#2563eb', track: '#bfdbfe' },
    { bg: '#f5f3ff', accent: '#8b5cf6', text: '#7c3aed', track: '#ddd6fe' },
    { bg: '#fff7ed', accent: '#f97316', text: '#ea580c', track: '#fed7aa' },
    { bg: '#f0fdf4', accent: '#22c55e', text: '#16a34a', track: '#bbf7d0' },
    { bg: '#fffbeb', accent: '#f59e0b', text: '#d97706', track: '#fde68a' },
    { bg: '#ecfeff', accent: '#06b6d4', text: '#0891b2', track: '#a5f3fc' },
    { bg: '#fdf4ff', accent: '#d946ef', text: '#c026d3', track: '#f0abfc' },
];

const STATUS_CONFIG = {
    draft:    { label: 'Draft',    dot: '#94a3b8', bg: '#f8fafc', text: '#64748b' },
    pending:  { label: 'Pending',  dot: '#f59e0b', bg: '#fffbeb', text: '#b45309' },
    approved: { label: 'Approved', dot: '#22c55e', bg: '#f0fdf4', text: '#15803d' },
    rejected: { label: 'Rejected', dot: '#ef4444', bg: '#fef2f2', text: '#dc2626' },
    paid:     { label: 'Paid',     dot: '#3b82f6', bg: '#eff6ff', text: '#1d4ed8' },
};

const EMPTY_FORM = {
    claim_type_id: '',
    amount: '',
    claim_date: new Date().toISOString().split('T')[0],
    description: '',
    receipt: null,
    vehicle_rate_id: '',
    distance_km: '',
    origin: '',
    destination: '',
    trip_purpose: '',
};

// ── Helpers ──────────────────────────────────────────────────────────────────

function formatDate(dateStr) {
    if (!dateStr) { return '-'; }
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatCurrency(amount) {
    if (amount === null || amount === undefined) { return '-'; }
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

// ── Sub-components ───────────────────────────────────────────────────────────

function BudgetCard({ limit, index }) {
    const palette = PALETTE[index % PALETTE.length];
    const usedPercent = limit.monthly_limit
        ? Math.min(100, (limit.used_this_month / limit.monthly_limit) * 100)
        : 0;
    const remaining = limit.monthly_limit
        ? Math.max(0, limit.monthly_limit - limit.used_this_month)
        : null;
    const isDanger  = usedPercent >= 90;
    const isWarning = usedPercent >= 70;
    const barColor  = isDanger ? '#ef4444' : isWarning ? '#f59e0b' : palette.accent;

    return (
        <div
            className="flex flex-col gap-2 rounded-2xl p-4"
            style={{ backgroundColor: palette.bg }}
        >
            {/* header row */}
            <div className="flex items-center justify-between">
                <div className="h-2 w-2 rounded-full" style={{ backgroundColor: palette.accent }} />
                {limit.monthly_limit && (
                    <span
                        className="text-xs font-semibold tabular-nums"
                        style={{ color: isDanger ? '#ef4444' : '#9ca3af' }}
                    >
                        {Math.round(usedPercent)}%
                    </span>
                )}
            </div>

            {/* category name */}
            <p className="text-xs font-medium leading-tight text-slate-500">{limit.name}</p>

            {/* remaining amount */}
            {remaining !== null ? (
                <>
                    <div>
                        <p
                            className="text-lg font-bold tabular-nums leading-none"
                            style={{ color: palette.text }}
                        >
                            {formatCurrency(remaining)}
                        </p>
                        <p className="mt-0.5 text-xs text-slate-400">remaining</p>
                    </div>

                    {/* progress bar */}
                    <div
                        className="h-1.5 w-full overflow-hidden rounded-full"
                        style={{ backgroundColor: palette.track }}
                    >
                        <div
                            className="h-1.5 rounded-full transition-all duration-700"
                            style={{ width: `${usedPercent}%`, backgroundColor: barColor }}
                        />
                    </div>

                    <p className="text-xs text-slate-400">
                        {formatCurrency(limit.used_this_month)} of {formatCurrency(limit.monthly_limit)}
                    </p>
                </>
            ) : (
                <p className="text-xs italic text-slate-400">No monthly limit</p>
            )}
        </div>
    );
}

function ClaimRow({ claim, onSubmit, onDelete }) {
    const statusCfg = STATUS_CONFIG[claim.status] || STATUS_CONFIG.draft;

    return (
        <div className="flex items-start gap-3 px-4 py-4">
            {/* status dot */}
            <div
                className="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                style={{ backgroundColor: statusCfg.dot }}
            />

            {/* info */}
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <p className="truncate text-sm font-semibold text-slate-800">
                        {claim.claim_type?.name || '-'}
                    </p>
                    {claim.receipt_url && (
                        <a
                            href={claim.receipt_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="shrink-0 text-slate-400 hover:text-blue-500"
                            title="View receipt"
                        >
                            <Paperclip className="h-3 w-3" />
                        </a>
                    )}
                </div>
                <p className="mt-0.5 text-xs text-slate-400">
                    {formatDate(claim.claim_date)}
                    {claim.description && <span> · {claim.description}</span>}
                </p>
                {claim.distance_km && (
                    <p className="mt-1 flex items-center gap-1 text-xs text-blue-500">
                        <Car className="h-3 w-3 shrink-0" />
                        {claim.distance_km} km · {claim.origin} → {claim.destination}
                    </p>
                )}
                <p className="mt-0.5 font-mono text-xs text-slate-300">{claim.claim_number}</p>
            </div>

            {/* amount + actions */}
            <div className="shrink-0 text-right">
                <p className="text-sm font-bold tabular-nums text-slate-800">
                    {formatCurrency(claim.amount)}
                </p>
                <span
                    className="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                    style={{ backgroundColor: statusCfg.bg, color: statusCfg.text }}
                >
                    {statusCfg.label}
                </span>
                {claim.status === 'draft' && (
                    <div className="mt-2 flex items-center justify-end gap-1.5">
                        <button
                            onClick={() => onSubmit(claim)}
                            className="flex items-center gap-1 rounded-full bg-slate-800 px-2.5 py-1 text-xs font-medium text-white transition-colors hover:bg-slate-700 active:bg-slate-900"
                        >
                            <Send className="h-2.5 w-2.5" />
                            Submit
                        </button>
                        <button
                            onClick={() => onDelete(claim)}
                            className="rounded-full p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

// ── Input style helper ────────────────────────────────────────────────────────

const inputCls =
    'w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm placeholder:text-slate-400 transition focus:border-slate-400 focus:bg-white focus:outline-none';

// ── Main component ───────────────────────────────────────────────────────────

export default function MyClaims() {
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [statusFilter, setStatusFilter] = useState('all');
    const [formOpen, setFormOpen] = useState(false);
    const [form, setForm] = useState(EMPTY_FORM);
    const [errors, setErrors] = useState({});
    const [deleteDialog, setDeleteDialog] = useState({ open: false, claim: null });
    const [submitDialog, setSubmitDialog] = useState({ open: false, claim: null });

    const { data: claimsData, isLoading } = useQuery({
        queryKey: ['my-claims', { page, statusFilter }],
        queryFn: () =>
            fetchMyClaims({
                page,
                per_page: 10,
                status: statusFilter !== 'all' ? statusFilter : undefined,
            }),
    });

    const { data: limitsData } = useQuery({
        queryKey: ['my-claim-limits'],
        queryFn: fetchMyClaimLimits,
    });

    const { data: claimTypesData } = useQuery({
        queryKey: ['hr', 'claims', 'types', 'list'],
        queryFn: () => fetchClaimTypes({ per_page: 100 }),
    });

    const createMutation = useMutation({
        mutationFn: (formData) => createMyClaim(formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-claims'] });
            queryClient.invalidateQueries({ queryKey: ['my-claim-limits'] });
            setFormOpen(false);
            setForm(EMPTY_FORM);
            setErrors({});
        },
        onError: (err) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            }
        },
    });

    const submitMutation = useMutation({
        mutationFn: (id) => submitMyClaim(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-claims'] });
            setSubmitDialog({ open: false, claim: null });
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => deleteMyClaim(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['my-claims'] });
            setDeleteDialog({ open: false, claim: null });
        },
    });

    const claims     = claimsData?.data || [];
    const meta       = claimsData?.meta || {};
    const limits     = limitsData?.data || [];
    const claimTypes = claimTypesData?.data || [];

    const selectedClaimType = useMemo(
        () => claimTypes.find((t) => String(t.id) === String(form.claim_type_id)) || null,
        [claimTypes, form.claim_type_id],
    );
    const isMileageType = selectedClaimType?.is_mileage_type === true;
    const vehicleRates  = selectedClaimType?.vehicle_rates?.filter((r) => r.is_active) || [];

    const selectedRate = useMemo(
        () => vehicleRates.find((r) => String(r.id) === String(form.vehicle_rate_id)) || null,
        [vehicleRates, form.vehicle_rate_id],
    );
    const calculatedAmount = useMemo(() => {
        if (!isMileageType || !selectedRate || !form.distance_km) { return null; }
        const km = parseFloat(form.distance_km);
        if (isNaN(km) || km <= 0) { return null; }
        return Math.round(km * parseFloat(selectedRate.rate_per_km) * 100) / 100;
    }, [isMileageType, selectedRate, form.distance_km]);

    function handleClaimTypeChange(v) {
        setForm((f) => ({
            ...f,
            claim_type_id: v,
            amount: '',
            vehicle_rate_id: '',
            distance_km: '',
            origin: '',
            destination: '',
            trip_purpose: '',
        }));
    }

    function handleSubmitForm(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('claim_type_id', form.claim_type_id);
        formData.append('claim_date', form.claim_date);
        formData.append('description', form.description);
        if (form.receipt) {
            formData.append('receipt', form.receipt);
        }
        if (isMileageType) {
            formData.append('vehicle_rate_id', form.vehicle_rate_id);
            formData.append('distance_km', form.distance_km);
            formData.append('origin', form.origin);
            formData.append('destination', form.destination);
            formData.append('trip_purpose', form.trip_purpose);
        } else {
            formData.append('amount', form.amount);
        }
        createMutation.mutate(formData);
    }

    return (
        <div className="space-y-6 pb-4">

            {/* ── Budget section ─────────────────────────────────────────── */}
            {limits.length > 0 && (
                <section>
                    <div className="mb-3 flex items-center justify-between">
                        <p className="text-xs font-semibold uppercase tracking-widest text-slate-400">
                            Monthly Budget
                        </p>
                        <span className="text-xs text-slate-400">
                            {limits.filter((l) => l.monthly_limit).length} active limits
                        </span>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        {limits.map((limit, i) => (
                            <BudgetCard key={limit.claim_type_id} limit={limit} index={i} />
                        ))}
                    </div>
                </section>
            )}

            {/* ── Claims section ─────────────────────────────────────────── */}
            <section>
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <p className="text-xs font-semibold uppercase tracking-widest text-slate-400">
                            My Claims
                        </p>
                        {meta.total > 0 && (
                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">
                                {meta.total}
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        <Select
                            value={statusFilter}
                            onValueChange={(v) => {
                                setStatusFilter(v);
                                setPage(1);
                            }}
                        >
                            <SelectTrigger className="h-7 w-28 rounded-full border-slate-200 bg-slate-50 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="approved">Approved</SelectItem>
                                <SelectItem value="rejected">Rejected</SelectItem>
                                <SelectItem value="paid">Paid</SelectItem>
                            </SelectContent>
                        </Select>
                        <button
                            onClick={() => {
                                setFormOpen(true);
                                setForm(EMPTY_FORM);
                                setErrors({});
                            }}
                            className="flex h-7 items-center gap-1 rounded-full bg-slate-800 px-3 text-xs font-medium text-white transition-colors hover:bg-slate-700 active:bg-slate-900"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            New
                        </button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100/80">
                    {isLoading ? (
                        <div className="flex justify-center py-14">
                            <Loader2 className="h-5 w-5 animate-spin text-slate-300" />
                        </div>
                    ) : claims.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <div className="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50">
                                <Receipt className="h-6 w-6 text-slate-300" />
                            </div>
                            <p className="text-sm font-semibold text-slate-500">No claims yet</p>
                            <p className="mt-0.5 text-xs text-slate-400">
                                Your submitted expense claims will appear here.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-50">
                            {claims.map((claim) => (
                                <ClaimRow
                                    key={claim.id}
                                    claim={claim}
                                    onSubmit={(c) => setSubmitDialog({ open: true, claim: c })}
                                    onDelete={(c) => setDeleteDialog({ open: true, claim: c })}
                                />
                            ))}
                        </div>
                    )}

                    {meta.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-50 px-4 py-3">
                            <span className="text-xs text-slate-400">
                                Page {meta.current_page} of {meta.last_page}
                            </span>
                            <div className="flex gap-1.5">
                                <button
                                    disabled={page <= 1}
                                    onClick={() => setPage((p) => p - 1)}
                                    className="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition-colors hover:border-slate-300 disabled:opacity-30"
                                >
                                    <ChevronLeft className="h-3.5 w-3.5" />
                                </button>
                                <button
                                    disabled={page >= meta.last_page}
                                    onClick={() => setPage((p) => p + 1)}
                                    className="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition-colors hover:border-slate-300 disabled:opacity-30"
                                >
                                    <ChevronRight className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </section>

            {/* ── New Claim Dialog ──────────────────────────────────────── */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className={cn('gap-0 p-0', isMileageType ? 'max-w-lg' : 'max-w-md')}>
                    <div className="border-b border-slate-100 px-6 py-5">
                        <DialogTitle className="text-base font-semibold text-slate-800">
                            New Expense Claim
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-xs text-slate-400">
                            Fill in the details below. Saved as draft until you submit.
                        </DialogDescription>
                    </div>

                    <form onSubmit={handleSubmitForm} className="space-y-4 px-6 py-5">
                        {/* Claim type */}
                        <div>
                            <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Claim Type *
                            </label>
                            <Select value={form.claim_type_id} onValueChange={handleClaimTypeChange}>
                                <SelectTrigger className="rounded-xl border-slate-200 bg-slate-50 text-sm">
                                    <SelectValue placeholder="Select type…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {claimTypes
                                        .filter((t) => t.is_active)
                                        .map((t) => (
                                            <SelectItem key={t.id} value={String(t.id)}>
                                                <span className="flex items-center gap-2">
                                                    {t.is_mileage_type && (
                                                        <Car className="h-3.5 w-3.5 text-blue-500" />
                                                    )}
                                                    {t.name}
                                                </span>
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            {errors.claim_type_id && (
                                <p className="mt-1 text-xs text-red-500">{errors.claim_type_id[0]}</p>
                            )}
                        </div>

                        {/* Mileage fields */}
                        {isMileageType ? (
                            <div className="space-y-4 rounded-xl border border-blue-100 bg-blue-50/40 p-4">
                                <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-blue-600">
                                    <Route className="h-3.5 w-3.5" />
                                    Mileage Details
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Vehicle Type *
                                    </label>
                                    <Select
                                        value={form.vehicle_rate_id}
                                        onValueChange={(v) =>
                                            setForm((f) => ({ ...f, vehicle_rate_id: v }))
                                        }
                                    >
                                        <SelectTrigger className="rounded-xl border-slate-200 bg-white text-sm">
                                            <SelectValue placeholder="Select vehicle…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {vehicleRates.length === 0 ? (
                                                <SelectItem value="__none__" disabled>
                                                    No vehicle rates configured
                                                </SelectItem>
                                            ) : (
                                                vehicleRates.map((r) => (
                                                    <SelectItem key={r.id} value={String(r.id)}>
                                                        <span className="flex items-center justify-between gap-3">
                                                            <span>{r.name}</span>
                                                            <span className="text-xs text-slate-500">
                                                                RM {parseFloat(r.rate_per_km).toFixed(2)}/km
                                                            </span>
                                                        </span>
                                                    </SelectItem>
                                                ))
                                            )}
                                        </SelectContent>
                                    </Select>
                                    {errors.vehicle_rate_id && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.vehicle_rate_id[0]}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Distance (km) *
                                    </label>
                                    <input
                                        type="number"
                                        step="0.1"
                                        min="0.1"
                                        value={form.distance_km}
                                        onChange={(e) =>
                                            setForm((f) => ({ ...f, distance_km: e.target.value }))
                                        }
                                        placeholder="e.g. 45.5"
                                        className={cn(inputCls, 'bg-white')}
                                        required
                                    />
                                    {errors.distance_km && (
                                        <p className="mt-1 text-xs text-red-500">{errors.distance_km[0]}</p>
                                    )}
                                </div>

                                {calculatedAmount !== null && (
                                    <div className="flex items-center gap-2.5 rounded-xl border border-blue-200 bg-white px-4 py-3">
                                        <Calculator className="h-4 w-4 shrink-0 text-blue-400" />
                                        <p className="text-sm text-slate-600">
                                            <span className="tabular-nums">
                                                {parseFloat(form.distance_km).toFixed(1)} km
                                            </span>
                                            <span className="mx-1.5 text-slate-400">×</span>
                                            <span className="tabular-nums">
                                                RM {parseFloat(selectedRate.rate_per_km).toFixed(2)}/km
                                            </span>
                                            <span className="mx-1.5 text-slate-400">=</span>
                                            <span className="font-bold tabular-nums text-blue-700">
                                                RM {calculatedAmount.toFixed(2)}
                                            </span>
                                        </p>
                                    </div>
                                )}

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1.5 flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <MapPin className="h-3 w-3 text-green-500" /> Origin *
                                        </label>
                                        <input
                                            type="text"
                                            value={form.origin}
                                            onChange={(e) =>
                                                setForm((f) => ({ ...f, origin: e.target.value }))
                                            }
                                            placeholder="e.g. Kuala Lumpur"
                                            className={cn(inputCls, 'bg-white')}
                                            required
                                        />
                                        {errors.origin && (
                                            <p className="mt-1 text-xs text-red-500">{errors.origin[0]}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="mb-1.5 flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <MapPin className="h-3 w-3 text-red-500" /> Destination *
                                        </label>
                                        <input
                                            type="text"
                                            value={form.destination}
                                            onChange={(e) =>
                                                setForm((f) => ({ ...f, destination: e.target.value }))
                                            }
                                            placeholder="e.g. Petaling Jaya"
                                            className={cn(inputCls, 'bg-white')}
                                            required
                                        />
                                        {errors.destination && (
                                            <p className="mt-1 text-xs text-red-500">
                                                {errors.destination[0]}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Trip Purpose *
                                    </label>
                                    <input
                                        type="text"
                                        value={form.trip_purpose}
                                        onChange={(e) =>
                                            setForm((f) => ({ ...f, trip_purpose: e.target.value }))
                                        }
                                        placeholder="e.g. Client visit at ABC Sdn Bhd"
                                        className={cn(inputCls, 'bg-white')}
                                        required
                                    />
                                    {errors.trip_purpose && (
                                        <p className="mt-1 text-xs text-red-500">{errors.trip_purpose[0]}</p>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div>
                                <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Amount (MYR) *
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={form.amount}
                                    onChange={(e) =>
                                        setForm((f) => ({ ...f, amount: e.target.value }))
                                    }
                                    className={inputCls}
                                    placeholder="0.00"
                                    required={!isMileageType}
                                />
                                {errors.amount && (
                                    <p className="mt-1 text-xs text-red-500">{errors.amount[0]}</p>
                                )}
                            </div>
                        )}

                        {/* Claim date */}
                        <div>
                            <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Claim Date *
                            </label>
                            <input
                                type="date"
                                value={form.claim_date}
                                onChange={(e) =>
                                    setForm((f) => ({ ...f, claim_date: e.target.value }))
                                }
                                className={inputCls}
                                required
                            />
                            {errors.claim_date && (
                                <p className="mt-1 text-xs text-red-500">{errors.claim_date[0]}</p>
                            )}
                        </div>

                        {/* Description */}
                        <div>
                            <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Description *
                            </label>
                            <textarea
                                value={form.description}
                                onChange={(e) =>
                                    setForm((f) => ({ ...f, description: e.target.value }))
                                }
                                className={inputCls}
                                rows={2}
                                placeholder="Brief description of the expense"
                                required
                            />
                            {errors.description && (
                                <p className="mt-1 text-xs text-red-500">{errors.description[0]}</p>
                            )}
                        </div>

                        {/* Receipt upload */}
                        {!isMileageType && (
                            <div>
                                <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Receipt
                                </label>
                                <label className="flex cursor-pointer items-center gap-2.5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500 transition hover:border-slate-400 hover:bg-slate-100">
                                    <Upload className="h-4 w-4 shrink-0" />
                                    <span className="truncate">
                                        {form.receipt
                                            ? form.receipt.name
                                            : 'Upload receipt (PDF, JPG, PNG)'}
                                    </span>
                                    <input
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        className="hidden"
                                        onChange={(e) =>
                                            setForm((f) => ({
                                                ...f,
                                                receipt: e.target.files[0] || null,
                                            }))
                                        }
                                    />
                                </label>
                                {errors.receipt && (
                                    <p className="mt-1 text-xs text-red-500">{errors.receipt[0]}</p>
                                )}
                            </div>
                        )}

                        {/* Footer */}
                        <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                            <Button
                                type="button"
                                variant="ghost"
                                className="rounded-xl text-slate-600"
                                onClick={() => setFormOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={createMutation.isPending}
                                className="rounded-xl bg-slate-800 px-5 text-white hover:bg-slate-700"
                            >
                                {createMutation.isPending && (
                                    <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                )}
                                Save as Draft
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ── Submit Dialog ──────────────────────────────────────────── */}
            <Dialog
                open={submitDialog.open}
                onOpenChange={() => setSubmitDialog({ open: false, claim: null })}
            >
                <DialogContent className="max-w-sm gap-0 p-0">
                    <div className="border-b border-slate-100 px-6 py-5">
                        <DialogTitle className="text-base font-semibold">Submit Claim</DialogTitle>
                        <DialogDescription className="mt-0.5 text-xs text-slate-400">
                            You won't be able to edit it after submission.
                        </DialogDescription>
                    </div>
                    {submitDialog.claim && (
                        <div className="px-6 py-5">
                            <div className="rounded-xl bg-slate-50 px-4 py-3">
                                <p className="text-sm font-semibold text-slate-800">
                                    {submitDialog.claim.claim_type?.name}
                                </p>
                                <p className="mt-0.5 text-sm text-slate-500">
                                    {formatCurrency(submitDialog.claim.amount)} ·{' '}
                                    {formatDate(submitDialog.claim.claim_date)}
                                </p>
                            </div>
                        </div>
                    )}
                    <div className="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                        <Button
                            variant="ghost"
                            className="rounded-xl text-slate-600"
                            onClick={() => setSubmitDialog({ open: false, claim: null })}
                        >
                            Cancel
                        </Button>
                        <Button
                            className="rounded-xl bg-slate-800 text-white hover:bg-slate-700"
                            onClick={() => submitMutation.mutate(submitDialog.claim.id)}
                            disabled={submitMutation.isPending}
                        >
                            {submitMutation.isPending && (
                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                            )}
                            Submit for Approval
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            {/* ── Delete Dialog ──────────────────────────────────────────── */}
            <Dialog
                open={deleteDialog.open}
                onOpenChange={() => setDeleteDialog({ open: false, claim: null })}
            >
                <DialogContent className="max-w-sm gap-0 p-0">
                    <div className="border-b border-slate-100 px-6 py-5">
                        <DialogTitle className="text-base font-semibold">Delete Draft</DialogTitle>
                        <DialogDescription className="mt-0.5 text-xs text-slate-400">
                            This action cannot be undone.
                        </DialogDescription>
                    </div>
                    <div className="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                        <Button
                            variant="ghost"
                            className="rounded-xl text-slate-600"
                            onClick={() => setDeleteDialog({ open: false, claim: null })}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            className="rounded-xl"
                            onClick={() => deleteMutation.mutate(deleteDialog.claim.id)}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending && (
                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                            )}
                            Delete
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
