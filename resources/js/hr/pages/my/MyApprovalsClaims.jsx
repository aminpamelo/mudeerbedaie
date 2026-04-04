import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Check, X, AlertCircle, Calendar, Building2, Briefcase, Receipt, FileText, Tag, ArrowRight } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '../../components/ui/dialog';
import api from '../../lib/api';

const TABS = [
    { key: 'pending',  label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'all',      label: 'All' },
];

const STATUS_CONFIG = {
    pending:  { color: 'text-amber-700 bg-amber-50 border-amber-200',   dot: 'bg-amber-500',  border: 'border-l-amber-400' },
    approved: { color: 'text-emerald-700 bg-emerald-50 border-emerald-200', dot: 'bg-emerald-500', border: 'border-l-emerald-400' },
    rejected: { color: 'text-red-700 bg-red-50 border-red-200',         dot: 'bg-red-500',    border: 'border-l-red-400' },
    paid:     { color: 'text-blue-700 bg-blue-50 border-blue-200',      dot: 'bg-blue-500',   border: 'border-l-blue-400' },
    draft:    { color: 'text-zinc-600 bg-zinc-100 border-zinc-200',     dot: 'bg-zinc-400',   border: 'border-l-zinc-300' },
};

const AVATAR_COLORS = [
    'bg-violet-100 text-violet-700',
    'bg-sky-100 text-sky-700',
    'bg-rose-100 text-rose-700',
    'bg-amber-100 text-amber-700',
    'bg-emerald-100 text-emerald-700',
    'bg-indigo-100 text-indigo-700',
];

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatRM(val) {
    return `RM ${Number(val ?? 0).toFixed(2)}`;
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

function avatarColor(name) {
    if (!name) return AVATAR_COLORS[0];
    return AVATAR_COLORS[name.charCodeAt(0) % AVATAR_COLORS.length];
}

function fetchClaimsApprovals(status) {
    const params = status !== 'all' ? `?status=${status}` : '';
    return api.get(`/my-approvals/claims${params}`).then((r) => r.data);
}

function SkeletonCard() {
    return (
        <div className="rounded-2xl bg-white border border-zinc-100 p-4 shadow-sm animate-pulse">
            <div className="flex items-start gap-3">
                <div className="h-10 w-10 rounded-full bg-zinc-100 shrink-0" />
                <div className="flex-1 space-y-2">
                    <div className="h-4 w-36 rounded bg-zinc-100" />
                    <div className="h-3 w-24 rounded bg-zinc-100" />
                </div>
                <div className="h-5 w-16 rounded-full bg-zinc-100" />
            </div>
            <div className="mt-3 grid grid-cols-2 gap-2">
                <div className="h-8 rounded-xl bg-zinc-50" />
                <div className="h-8 rounded-xl bg-zinc-50" />
            </div>
            <div className="mt-2 grid grid-cols-2 gap-2">
                <div className="h-10 rounded-xl bg-zinc-50" />
                <div className="h-10 rounded-xl bg-zinc-50" />
            </div>
        </div>
    );
}

function ClaimCard({ req, onApprove, onReject }) {
    const cfg = STATUS_CONFIG[req.status] ?? STATUS_CONFIG.draft;
    const name = req.employee?.full_name ?? 'Unknown';
    const hasApprovedAmount = req.approved_amount != null;
    const amountDiffers = hasApprovedAmount && Number(req.approved_amount) !== Number(req.amount);

    return (
        <div
            className={`rounded-2xl bg-white border border-zinc-100 border-l-4 ${cfg.border} shadow-sm overflow-hidden transition-shadow hover:shadow-md`}
            style={{ animation: 'fadeSlideUp 0.3s ease both' }}
        >
            <div className="p-4">
                {/* Header */}
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${avatarColor(name)}`}>
                            {getInitials(name)}
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-zinc-900 truncate">{name}</p>
                            <div className="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                {req.employee?.department?.name && (
                                    <span className="flex items-center gap-0.5 text-xs text-zinc-400">
                                        <Building2 className="h-3 w-3" />
                                        {req.employee.department.name}
                                    </span>
                                )}
                                {req.employee?.position?.title && (
                                    <span className="flex items-center gap-0.5 text-xs text-zinc-400">
                                        <span className="text-zinc-300">·</span>
                                        <Briefcase className="h-3 w-3" />
                                        {req.employee.position.title}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                    <span className={`shrink-0 inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium capitalize ${cfg.color}`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${cfg.dot}`} />
                        {req.status}
                    </span>
                </div>

                {/* Amount — hero element */}
                <div className="mt-3 rounded-xl bg-zinc-50 px-3 py-2.5 flex items-center justify-between">
                    <div>
                        <p className="text-[10px] uppercase tracking-wide text-zinc-400 font-medium">Requested</p>
                        <p className="text-base font-bold text-zinc-900">{formatRM(req.amount)}</p>
                    </div>
                    {hasApprovedAmount && (
                        <>
                            <ArrowRight className={`h-4 w-4 shrink-0 ${amountDiffers ? 'text-amber-500' : 'text-emerald-400'}`} />
                            <div className="text-right">
                                <p className="text-[10px] uppercase tracking-wide text-zinc-400 font-medium">Approved</p>
                                <p className={`text-base font-bold ${amountDiffers ? 'text-amber-600' : 'text-emerald-600'}`}>
                                    {formatRM(req.approved_amount)}
                                </p>
                            </div>
                        </>
                    )}
                </div>

                {/* Info chips */}
                <div className="mt-2 grid grid-cols-2 gap-2">
                    <div className="flex items-center gap-2 rounded-xl bg-zinc-50 px-3 py-2">
                        <Calendar className="h-3.5 w-3.5 text-zinc-400 shrink-0" />
                        <span className="text-xs text-zinc-700 font-medium">{formatDate(req.claim_date)}</span>
                    </div>
                    {req.claim_type?.name && (
                        <div className="flex items-center gap-2 rounded-xl bg-zinc-50 px-3 py-2">
                            <Tag className="h-3.5 w-3.5 text-zinc-400 shrink-0" />
                            <span className="text-xs text-zinc-700 font-medium truncate">{req.claim_type.name}</span>
                        </div>
                    )}
                </div>

                {/* Description */}
                {req.description && (
                    <div className="mt-2 flex items-start gap-2 rounded-xl bg-zinc-50 px-3 py-2">
                        <FileText className="h-3.5 w-3.5 text-zinc-400 shrink-0 mt-0.5" />
                        <p className="text-xs text-zinc-500 line-clamp-2">{req.description}</p>
                    </div>
                )}

                {/* Rejection reason */}
                {req.rejected_reason && (
                    <div className="mt-2 flex items-start gap-2 rounded-xl bg-red-50 px-3 py-2">
                        <AlertCircle className="h-3.5 w-3.5 text-red-400 shrink-0 mt-0.5" />
                        <p className="text-xs text-red-600">{req.rejected_reason}</p>
                    </div>
                )}

                {/* Actions */}
                {req.status === 'pending' && (
                    <div className="mt-3 grid grid-cols-2 gap-2">
                        <button
                            onClick={() => onApprove(req)}
                            className="flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition-all hover:bg-emerald-700 active:scale-95"
                        >
                            <Check className="h-4 w-4" />
                            Approve
                        </button>
                        <button
                            onClick={() => onReject(req)}
                            className="flex items-center justify-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-600 transition-all hover:bg-red-100 active:scale-95"
                        >
                            <X className="h-4 w-4" />
                            Reject
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function MyApprovalsClaims() {
    const navigate = useNavigate();
    const qc = useQueryClient();
    const [tab, setTab] = useState('pending');
    const [approveDialog, setApproveDialog] = useState(null);
    const [approvedAmount, setApprovedAmount] = useState('');
    const [rejectDialog, setRejectDialog] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [actionError, setActionError] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['my-approvals-claims', tab],
        queryFn: () => fetchClaimsApprovals(tab),
    });

    const approveMut = useMutation({
        mutationFn: ({ id, amount }) =>
            api.patch(`/my-approvals/claims/${id}/approve`, { approved_amount: amount }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-claims'] });
            qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
            setApproveDialog(null);
            setApprovedAmount('');
            setActionError('');
        },
        onError: (err) => setActionError(err.response?.data?.message ?? 'Failed to approve.'),
    });

    const rejectMut = useMutation({
        mutationFn: ({ id, reason }) =>
            api.patch(`/my-approvals/claims/${id}/reject`, { rejected_reason: reason }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-claims'] });
            qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
            setRejectDialog(null);
            setRejectReason('');
            setActionError('');
        },
        onError: (err) => setActionError(err.response?.data?.message ?? 'Failed to reject.'),
    });

    const requests = data?.data ?? [];

    return (
        <>
            <style>{`
                @keyframes fadeSlideUp {
                    from { opacity: 0; transform: translateY(8px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
            `}</style>

            <div className="flex flex-col h-full bg-zinc-50">
                {/* Sticky header */}
                <div className="sticky top-0 z-10 bg-white border-b border-zinc-100 px-4 pt-4 pb-0 shadow-sm">
                    <div className="flex items-center gap-3 mb-4">
                        <button
                            onClick={() => navigate('/my/approvals')}
                            className="flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800"
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </button>
                        <div>
                            <h1 className="text-base font-bold text-zinc-900">Claims Approvals</h1>
                            <p className="text-xs text-zinc-400">
                                {requests.length} {tab === 'all' ? 'total' : tab} claim{requests.length !== 1 ? 's' : ''}
                            </p>
                        </div>
                    </div>

                    {/* Tabs */}
                    <div className="flex gap-0 overflow-x-auto scrollbar-none -mb-px">
                        {TABS.map((t) => (
                            <button
                                key={t.key}
                                onClick={() => setTab(t.key)}
                                className={`shrink-0 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
                                    tab === t.key
                                        ? 'border-zinc-900 text-zinc-900'
                                        : 'border-transparent text-zinc-400 hover:text-zinc-600'
                                }`}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto p-4 space-y-3">
                    {isLoading ? (
                        <>
                            <SkeletonCard />
                            <SkeletonCard />
                            <SkeletonCard />
                        </>
                    ) : requests.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 mb-3">
                                <Receipt className="h-6 w-6 text-zinc-400" />
                            </div>
                            <p className="text-sm font-semibold text-zinc-700">No {tab === 'all' ? '' : tab} claims</p>
                            <p className="text-xs text-zinc-400 mt-1">Nothing to review right now</p>
                        </div>
                    ) : (
                        requests.map((req, i) => (
                            <div key={req.id} style={{ animationDelay: `${i * 0.05}s` }}>
                                <ClaimCard
                                    req={req}
                                    onApprove={(req) => {
                                        setApproveDialog({ id: req.id, name: req.employee?.full_name, amount: req.amount });
                                        setApprovedAmount(String(req.amount));
                                        setActionError('');
                                    }}
                                    onReject={(req) => {
                                        setRejectDialog({ id: req.id, name: req.employee?.full_name });
                                        setRejectReason('');
                                        setActionError('');
                                    }}
                                />
                            </div>
                        ))
                    )}
                </div>
            </div>

            {/* Approve Dialog */}
            <Dialog open={!!approveDialog} onOpenChange={() => setApproveDialog(null)}>
                <DialogContent className="rounded-2xl">
                    <DialogHeader>
                        <DialogTitle className="text-base">Approve Claim</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-zinc-500">
                        Approving claim from <span className="font-semibold text-zinc-800">{approveDialog?.name}</span>.
                    </p>
                    <div className="rounded-xl bg-zinc-50 px-3 py-2.5 flex items-center justify-between">
                        <span className="text-xs text-zinc-500">Requested amount</span>
                        <span className="text-sm font-bold text-zinc-800">{formatRM(approveDialog?.amount)}</span>
                    </div>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-zinc-700">Approved Amount (RM)</label>
                        <div className="relative">
                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-zinc-400">RM</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                className="w-full rounded-xl border border-zinc-200 bg-zinc-50 py-2.5 pl-10 pr-3 text-sm text-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:border-transparent transition"
                                value={approvedAmount}
                                onChange={(e) => setApprovedAmount(e.target.value)}
                            />
                        </div>
                        {approvedAmount && Number(approvedAmount) < Number(approveDialog?.amount) && (
                            <p className="mt-1.5 flex items-center gap-1 text-xs text-amber-600">
                                <AlertCircle className="h-3.5 w-3.5 shrink-0" />
                                Amount is less than requested — partial approval
                            </p>
                        )}
                    </div>
                    {actionError && (
                        <p className="flex items-center gap-1.5 text-sm text-red-600">
                            <AlertCircle className="h-4 w-4 shrink-0" /> {actionError}
                        </p>
                    )}
                    <DialogFooter className="gap-2">
                        <button
                            onClick={() => setApproveDialog(null)}
                            className="flex-1 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-50 transition"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={() => approveMut.mutate({ id: approveDialog.id, amount: parseFloat(approvedAmount) })}
                            disabled={!approvedAmount || parseFloat(approvedAmount) <= 0 || approveMut.isPending}
                            className="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40 transition active:scale-95"
                        >
                            {approveMut.isPending ? 'Approving…' : 'Confirm Approve'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog open={!!rejectDialog} onOpenChange={() => setRejectDialog(null)}>
                <DialogContent className="rounded-2xl">
                    <DialogHeader>
                        <DialogTitle className="text-base">Reject Claim</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-zinc-500">
                        Rejecting claim from <span className="font-semibold text-zinc-800">{rejectDialog?.name}</span>. Please provide a reason.
                    </p>
                    <textarea
                        className="w-full rounded-xl border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:border-transparent resize-none transition"
                        rows={3}
                        placeholder="Reason for rejection (min 5 characters)…"
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                    />
                    {actionError && (
                        <p className="flex items-center gap-1.5 text-sm text-red-600">
                            <AlertCircle className="h-4 w-4 shrink-0" /> {actionError}
                        </p>
                    )}
                    <DialogFooter className="gap-2">
                        <button
                            onClick={() => setRejectDialog(null)}
                            className="flex-1 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-50 transition"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={() => rejectMut.mutate({ id: rejectDialog.id, reason: rejectReason })}
                            disabled={rejectReason.length < 5 || rejectMut.isPending}
                            className="flex-1 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-40 transition active:scale-95"
                        >
                            {rejectMut.isPending ? 'Rejecting…' : 'Confirm Reject'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
