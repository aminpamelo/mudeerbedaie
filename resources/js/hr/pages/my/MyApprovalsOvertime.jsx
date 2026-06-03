import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Check, X, AlertCircle, Clock, Calendar, Building2, Briefcase, ChevronRight, Timer, FileText } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '../../components/ui/dialog';
import api from '../../lib/api';
import TierProgressBar from '../../components/TierProgressBar';

const TABS = [
    { key: 'pending',   label: 'Pending' },
    { key: 'approved',  label: 'Approved' },
    { key: 'rejected',  label: 'Rejected' },
    { key: 'completed', label: 'Completed' },
    { key: 'all',       label: 'All' },
];

const STATUS_CONFIG = {
    pending:   { color: 'text-amber-700 bg-amber-50 border-amber-200 dark:text-amber-300 dark:bg-amber-500/15 dark:border-amber-500/25',  dot: 'bg-amber-500',  border: 'border-l-amber-400' },
    approved:  { color: 'text-emerald-700 bg-emerald-50 border-emerald-200 dark:text-emerald-300 dark:bg-emerald-500/15 dark:border-emerald-500/25', dot: 'bg-emerald-500', border: 'border-l-emerald-400' },
    rejected:  { color: 'text-red-700 bg-red-50 border-red-200 dark:text-red-300 dark:bg-red-500/15 dark:border-red-500/25',        dot: 'bg-red-500',    border: 'border-l-red-400' },
    completed: { color: 'text-blue-700 bg-blue-50 border-blue-200 dark:text-blue-300 dark:bg-blue-500/15 dark:border-blue-500/25',     dot: 'bg-blue-500',   border: 'border-l-blue-400' },
    cancelled: { color: 'text-slate-600 bg-slate-100 border-slate-200 dark:text-slate-300 dark:bg-white/[0.08] dark:border-white/[0.07]',    dot: 'bg-slate-400',   border: 'border-l-slate-300' },
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(timeStr) {
    if (!timeStr) return '--:--';
    return timeStr.length === 5 ? timeStr : new Date(`2000-01-01T${timeStr}`).toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit' });
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

const AVATAR_COLORS = [
    'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
    'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
    'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
];

function avatarColor(name) {
    if (!name) return AVATAR_COLORS[0];
    const code = name.charCodeAt(0) % AVATAR_COLORS.length;
    return AVATAR_COLORS[code];
}

function fetchOvertimeApprovals(status) {
    const params = status !== 'all' ? `?status=${status}` : '';
    return api.get(`/my-approvals/overtime${params}`).then((r) => r.data);
}

function SkeletonCard() {
    return (
        <div className="rounded-2xl bg-white dark:bg-[#0F1626] border border-slate-100 dark:border-white/[0.07] p-4 shadow-sm animate-pulse">
            <div className="flex items-start gap-3">
                <div className="h-10 w-10 rounded-full bg-slate-100 dark:bg-white/[0.08] shrink-0" />
                <div className="flex-1 space-y-2">
                    <div className="h-4 w-36 rounded bg-slate-100 dark:bg-white/[0.08]" />
                    <div className="h-3 w-24 rounded bg-slate-100 dark:bg-white/[0.08]" />
                </div>
                <div className="h-5 w-16 rounded-full bg-slate-100 dark:bg-white/[0.08]" />
            </div>
            <div className="mt-3 grid grid-cols-2 gap-2">
                <div className="h-8 rounded-lg bg-slate-50 dark:bg-white/[0.04]" />
                <div className="h-8 rounded-lg bg-slate-50 dark:bg-white/[0.04]" />
            </div>
        </div>
    );
}

function OTCard({ req, onApprove, onReject }) {
    const cfg = STATUS_CONFIG[req.status] ?? STATUS_CONFIG.cancelled;
    const name = req.employee?.full_name ?? 'Unknown';

    return (
        <div
            className={`rounded-2xl bg-white dark:bg-[#0F1626] border border-slate-100 dark:border-white/[0.07] border-l-4 ${cfg.border} shadow-sm overflow-hidden transition-shadow hover:shadow-md`}
            style={{ animation: 'fadeSlideUp 0.3s ease both' }}
        >
            <div className="p-4">
                {/* Header row */}
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${avatarColor(name)}`}>
                            {getInitials(name)}
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-slate-900 dark:text-white truncate">{name}</p>
                            <div className="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                {req.employee?.department?.name && (
                                    <span className="flex items-center gap-0.5 text-xs text-slate-400 dark:text-slate-500">
                                        <Building2 className="h-3 w-3" />
                                        {req.employee.department.name}
                                    </span>
                                )}
                                {req.employee?.position?.title && (
                                    <span className="flex items-center gap-0.5 text-xs text-slate-400 dark:text-slate-500">
                                        <span className="text-slate-300 dark:text-slate-600">·</span>
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

                {/* Info grid */}
                <div className="mt-3 grid grid-cols-2 gap-2">
                    <div className="flex items-center gap-2 rounded-xl bg-slate-50 dark:bg-white/[0.04] px-3 py-2">
                        <Calendar className="h-3.5 w-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                        <span className="text-xs text-slate-700 dark:text-slate-200 font-medium">{formatDate(req.requested_date)}</span>
                    </div>
                    <div className="flex items-center gap-2 rounded-xl bg-slate-50 dark:bg-white/[0.04] px-3 py-2">
                        <Timer className="h-3.5 w-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                        <span className="text-xs text-slate-700 dark:text-slate-200 font-medium">{req.estimated_hours}h estimated</span>
                    </div>
                    {req.start_time && (
                        <div className="col-span-2 flex items-center gap-2 rounded-xl bg-slate-50 dark:bg-white/[0.04] px-3 py-2">
                            <Clock className="h-3.5 w-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                            <span className="text-xs text-slate-700 dark:text-slate-200 font-medium">
                                {formatTime(req.start_time)} – {formatTime(req.end_time)}
                            </span>
                        </div>
                    )}
                </div>

                {/* Reason */}
                {req.reason && (
                    <div className="mt-2.5 flex items-start gap-2 rounded-xl bg-slate-50 dark:bg-white/[0.04] px-3 py-2">
                        <FileText className="h-3.5 w-3.5 text-slate-400 dark:text-slate-500 shrink-0 mt-0.5" />
                        <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">{req.reason}</p>
                    </div>
                )}

                {/* Rejection reason */}
                {req.rejection_reason && (
                    <div className="mt-2.5 flex items-start gap-2 rounded-xl bg-red-50 dark:bg-red-500/15 px-3 py-2">
                        <AlertCircle className="h-3.5 w-3.5 text-red-400 dark:text-red-300 shrink-0 mt-0.5" />
                        <p className="text-xs text-red-600 dark:text-red-300">{req.rejection_reason}</p>
                    </div>
                )}

                {/* Tier Progress */}
                <TierProgressBar
                    maxTier={req.max_tier}
                    currentTier={req.current_approval_tier}
                    approvalLogs={req.approval_logs || []}
                    tierApprovers={req.tier_approvers || {}}
                    status={req.status}
                />

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
                            className="flex items-center justify-center gap-1.5 rounded-xl border border-red-200 dark:border-red-500/25 bg-red-50 dark:bg-red-500/15 px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-300 transition-all hover:bg-red-100 dark:hover:bg-red-500/25 active:scale-95"
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

function ClaimCard({ claim, onApprove, onReject }) {
    const cfg = STATUS_CONFIG[claim.status] ?? STATUS_CONFIG.cancelled;
    const name = claim.employee?.full_name ?? 'Unknown';

    function formatClaimDuration(minutes) {
        if (!minutes) return '-';
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        if (h === 0) return `${m}min`;
        if (m === 0) return `${h}h`;
        return `${h}h ${m}min`;
    }

    return (
        <div
            className={`rounded-2xl bg-white dark:bg-[#0F1626] border border-slate-100 dark:border-white/[0.07] border-l-4 ${cfg.border} shadow-sm overflow-hidden transition-shadow hover:shadow-md`}
            style={{ animation: 'fadeSlideUp 0.3s ease both' }}
        >
            <div className="p-4">
                {/* Header row */}
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${avatarColor(name)}`}>
                            {getInitials(name)}
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-slate-900 dark:text-white truncate">{name}</p>
                            <div className="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                {claim.employee?.department?.name && (
                                    <span className="flex items-center gap-0.5 text-xs text-slate-400 dark:text-slate-500">
                                        <Building2 className="h-3 w-3" />
                                        {claim.employee.department.name}
                                    </span>
                                )}
                                {claim.employee?.position?.title && (
                                    <span className="flex items-center gap-0.5 text-xs text-slate-400 dark:text-slate-500">
                                        <span className="text-slate-300 dark:text-slate-600">·</span>
                                        <Briefcase className="h-3 w-3" />
                                        {claim.employee.position.title}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                    <span className={`shrink-0 inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium capitalize ${cfg.color}`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${cfg.dot}`} />
                        {claim.status}
                    </span>
                </div>

                {/* Info grid */}
                <div className="mt-3 grid grid-cols-2 gap-2">
                    <div className="flex items-center gap-2 rounded-xl bg-slate-50 dark:bg-white/[0.04] px-3 py-2">
                        <Calendar className="h-3.5 w-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                        <span className="text-xs text-slate-700 dark:text-slate-200 font-medium">{formatDate(claim.claim_date)}</span>
                    </div>
                    <div className="flex items-center gap-2 rounded-xl bg-slate-50 dark:bg-white/[0.04] px-3 py-2">
                        <Timer className="h-3.5 w-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                        <span className="text-xs text-slate-700 dark:text-slate-200 font-medium">{formatClaimDuration(claim.duration_minutes)}</span>
                    </div>
                    {claim.start_time && (
                        <div className="col-span-2 flex items-center gap-2 rounded-xl bg-slate-50 dark:bg-white/[0.04] px-3 py-2">
                            <Clock className="h-3.5 w-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                            <span className="text-xs text-slate-700 dark:text-slate-200 font-medium">From {formatTime(claim.start_time)}</span>
                        </div>
                    )}
                </div>

                {/* Notes */}
                {claim.notes && (
                    <div className="mt-2.5 flex items-start gap-2 rounded-xl bg-slate-50 dark:bg-white/[0.04] px-3 py-2">
                        <FileText className="h-3.5 w-3.5 text-slate-400 dark:text-slate-500 shrink-0 mt-0.5" />
                        <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">{claim.notes}</p>
                    </div>
                )}

                {/* Rejection reason */}
                {claim.rejection_reason && (
                    <div className="mt-2.5 flex items-start gap-2 rounded-xl bg-red-50 dark:bg-red-500/15 px-3 py-2">
                        <AlertCircle className="h-3.5 w-3.5 text-red-400 dark:text-red-300 shrink-0 mt-0.5" />
                        <p className="text-xs text-red-600 dark:text-red-300">{claim.rejection_reason}</p>
                    </div>
                )}

                {/* Tier Progress */}
                <TierProgressBar
                    maxTier={claim.max_tier}
                    currentTier={claim.current_approval_tier}
                    approvalLogs={claim.approval_logs || []}
                    tierApprovers={claim.tier_approvers || {}}
                    status={claim.status}
                />

                {/* Actions */}
                {claim.status === 'pending' && (
                    <div className="mt-3 grid grid-cols-2 gap-2">
                        <button
                            onClick={() => onApprove(claim)}
                            className="flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition-all hover:bg-emerald-700 active:scale-95"
                        >
                            <Check className="h-4 w-4" />
                            Approve
                        </button>
                        <button
                            onClick={() => onReject(claim)}
                            className="flex items-center justify-center gap-1.5 rounded-xl border border-red-200 dark:border-red-500/25 bg-red-50 dark:bg-red-500/15 px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-300 transition-all hover:bg-red-100 dark:hover:bg-red-500/25 active:scale-95"
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

export default function MyApprovalsOvertime() {
    const navigate = useNavigate();
    const qc = useQueryClient();
    const [tab, setTab] = useState('pending');
    const [type, setType] = useState('requests'); // 'requests' | 'claims'
    const [approveDialog, setApproveDialog] = useState(null);
    const [rejectDialog, setRejectDialog] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [actionError, setActionError] = useState('');

    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['my-approvals-overtime', tab],
        queryFn: () => fetchOvertimeApprovals(tab),
    });

    const approveMut = useMutation({
        mutationFn: (id) => api.patch(`/my-approvals/overtime/${id}/approve`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-overtime'] });
            qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
            setApproveDialog(null);
        },
    });

    const rejectMut = useMutation({
        mutationFn: ({ id, reason }) =>
            api.patch(`/my-approvals/overtime/${id}/reject`, { rejection_reason: reason }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-overtime'] });
            qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
            setRejectDialog(null);
            setRejectReason('');
            setActionError('');
        },
        onError: (err) => setActionError(err.response?.data?.message ?? 'Failed to reject.'),
    });

    const { data: claimsData, isLoading: claimsLoading, isError: isClaimsError, error: claimsError } = useQuery({
        queryKey: ['my-approvals-overtime-claims', tab],
        queryFn: () => {
            const params = tab !== 'all' ? `?status=${tab}` : '';
            return api.get(`/my-approvals/overtime-claims${params}`).then((r) => r.data);
        },
        enabled: type === 'claims',
    });

    const claimApproveMut = useMutation({
        mutationFn: (id) => api.patch(`/my-approvals/overtime-claims/${id}/approve`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-overtime-claims'] });
            qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
            setApproveDialog(null);
        },
    });

    const claimRejectMut = useMutation({
        mutationFn: ({ id, reason }) =>
            api.patch(`/my-approvals/overtime-claims/${id}/reject`, { rejection_reason: reason }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-overtime-claims'] });
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

            <div className="flex flex-col h-full bg-slate-50 dark:bg-[#080C16]">
                {/* Header */}
                <div className="sticky top-0 z-10 bg-white dark:bg-[#0F1626] border-b border-slate-100 dark:border-white/[0.07] px-4 pt-4 pb-0 shadow-sm">
                    <div className="flex items-center gap-3 mb-4">
                        <button
                            onClick={() => navigate('/my/approvals')}
                            className="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 dark:border-white/[0.07] bg-white dark:bg-[#0F1626] text-slate-500 dark:text-slate-400 transition hover:bg-slate-50 dark:hover:bg-white/[0.06] hover:text-slate-800 dark:hover:text-slate-200"
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </button>
                        <div>
                            <h1 className="text-base font-bold text-slate-900 dark:text-white">Overtime Approvals</h1>
                            <p className="text-xs text-slate-400 dark:text-slate-500">{requests.length} {tab === 'all' ? 'total' : tab} request{requests.length !== 1 ? 's' : ''}</p>
                        </div>
                    </div>

                    {/* Type switcher */}
                    <div className="flex rounded-lg border border-slate-200 dark:border-white/[0.07] p-0.5 bg-slate-50 dark:bg-white/[0.04] w-fit mb-3">
                        <button
                            onClick={() => setType('requests')}
                            className={`px-3 py-1 rounded-md text-xs font-medium transition-colors ${
                                type === 'requests' ? 'bg-white dark:bg-[#0F1626] text-slate-900 dark:text-white shadow-sm' : 'text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300'
                            }`}
                        >
                            OT Requests
                        </button>
                        <button
                            onClick={() => setType('claims')}
                            className={`px-3 py-1 rounded-md text-xs font-medium transition-colors ${
                                type === 'claims' ? 'bg-white dark:bg-[#0F1626] text-slate-900 dark:text-white shadow-sm' : 'text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300'
                            }`}
                        >
                            OT Claims
                        </button>
                    </div>

                    {/* Tabs */}
                    <div className="flex gap-0 overflow-x-auto scrollbar-none -mb-px">
                        {TABS.map((t) => (
                            <button
                                key={t.key}
                                onClick={() => setTab(t.key)}
                                className={`shrink-0 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
                                    tab === t.key
                                        ? 'border-pink-500 text-pink-600 dark:text-pink-400'
                                        : 'border-transparent text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300'
                                }`}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto p-4 space-y-3">
                    {type === 'requests' ? (
                        isLoading ? (
                            <>
                                <SkeletonCard />
                                <SkeletonCard />
                                <SkeletonCard />
                            </>
                        ) : isError ? (
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 dark:bg-red-500/15 mb-3">
                                    <AlertCircle className="h-6 w-6 text-red-400 dark:text-red-300" />
                                </div>
                                <p className="text-sm font-semibold text-slate-700 dark:text-slate-200">Failed to load requests</p>
                                <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">{error?.response?.data?.message || error?.message || 'Something went wrong'}</p>
                            </div>
                        ) : requests.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-white/[0.04] mb-3">
                                    <Timer className="h-6 w-6 text-slate-400 dark:text-slate-500" />
                                </div>
                                <p className="text-sm font-semibold text-slate-700 dark:text-slate-200">No {tab === 'all' ? '' : tab} requests</p>
                                <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">Nothing to review right now</p>
                            </div>
                        ) : (
                            requests.map((req, i) => (
                                <div key={req.id} style={{ animationDelay: `${i * 0.05}s` }}>
                                    <OTCard
                                        req={req}
                                        onApprove={(req) => setApproveDialog({ id: req.id, name: req.employee?.full_name, date: req.requested_date, hours: req.estimated_hours })}
                                        onReject={(req) => {
                                            setRejectDialog({ id: req.id, name: req.employee?.full_name });
                                            setRejectReason('');
                                            setActionError('');
                                        }}
                                        approving={approveMut.isPending}
                                    />
                                </div>
                            ))
                        )
                    ) : (
                        claimsLoading ? (
                            <>
                                <SkeletonCard />
                                <SkeletonCard />
                            </>
                        ) : isClaimsError ? (
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 dark:bg-red-500/15 mb-3">
                                    <AlertCircle className="h-6 w-6 text-red-400 dark:text-red-300" />
                                </div>
                                <p className="text-sm font-semibold text-slate-700 dark:text-slate-200">Failed to load claims</p>
                                <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">{claimsError?.response?.data?.message || claimsError?.message || 'Something went wrong'}</p>
                            </div>
                        ) : (claimsData?.data ?? []).length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-white/[0.04] mb-3">
                                    <Timer className="h-6 w-6 text-slate-400 dark:text-slate-500" />
                                </div>
                                <p className="text-sm font-semibold text-slate-700 dark:text-slate-200">No {tab === 'all' ? '' : tab} claims</p>
                                <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">Nothing to review right now</p>
                            </div>
                        ) : (
                            (claimsData?.data ?? []).map((claim, i) => (
                                <div key={claim.id} style={{ animationDelay: `${i * 0.05}s` }}>
                                    <ClaimCard
                                        claim={claim}
                                        onApprove={(c) => setApproveDialog({ id: c.id, name: c.employee?.full_name, date: c.claim_date, duration: c.duration_minutes })}
                                        onReject={(c) => {
                                            setRejectDialog({ id: c.id, name: c.employee?.full_name });
                                            setRejectReason('');
                                            setActionError('');
                                        }}
                                    />
                                </div>
                            ))
                        )
                    )}
                </div>
            </div>

            {/* Approve Confirmation Dialog */}
            <Dialog open={!!approveDialog} onOpenChange={() => setApproveDialog(null)}>
                <DialogContent className="rounded-2xl">
                    <DialogHeader>
                        <DialogTitle className="text-base">Approve Overtime Request</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            You're about to approve the overtime request from{' '}
                            <span className="font-semibold text-slate-800 dark:text-slate-100">{approveDialog?.name}</span>.
                        </p>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="rounded-xl bg-slate-50 dark:bg-white/[0.04] px-3 py-2.5">
                                <p className="text-[10px] uppercase tracking-wide text-slate-400 dark:text-slate-500 font-medium mb-0.5">Date</p>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">{formatDate(approveDialog?.date)}</p>
                            </div>
                            <div className="rounded-xl bg-slate-50 dark:bg-white/[0.04] px-3 py-2.5">
                                <p className="text-[10px] uppercase tracking-wide text-slate-400 dark:text-slate-500 font-medium mb-0.5">Duration</p>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {approveDialog?.duration ? `${approveDialog.duration}min` : `${approveDialog?.hours}h estimated`}
                                </p>
                            </div>
                        </div>
                    </div>
                    <DialogFooter className="gap-2">
                        <button
                            onClick={() => setApproveDialog(null)}
                            className="flex-1 rounded-xl border border-slate-200 dark:border-white/[0.10] px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/[0.06] transition"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={() => {
                                if (type === 'claims') {
                                    claimApproveMut.mutate(approveDialog.id);
                                } else {
                                    approveMut.mutate(approveDialog.id);
                                }
                            }}
                            disabled={approveMut.isPending || claimApproveMut.isPending}
                            className="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40 transition active:scale-95"
                        >
                            {(approveMut.isPending || claimApproveMut.isPending) ? 'Approving…' : 'Confirm Approve'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog open={!!rejectDialog} onOpenChange={() => setRejectDialog(null)}>
                <DialogContent className="rounded-2xl">
                    <DialogHeader>
                        <DialogTitle className="text-base">Reject Overtime Request</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                        Rejecting request from <span className="font-semibold text-slate-800 dark:text-slate-100">{rejectDialog?.name}</span>. Please provide a reason.
                    </p>
                    <textarea
                        className="mt-1 w-full rounded-xl border border-slate-200 dark:border-white/[0.10] bg-slate-50 dark:bg-white/[0.05] p-3 text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:border-transparent resize-none transition"
                        rows={3}
                        placeholder="Reason for rejection (min 5 characters)…"
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                    />
                    {actionError && (
                        <p className="flex items-center gap-1.5 text-sm text-red-600 dark:text-red-400">
                            <AlertCircle className="h-4 w-4 shrink-0" /> {actionError}
                        </p>
                    )}
                    <DialogFooter className="gap-2">
                        <button
                            onClick={() => setRejectDialog(null)}
                            className="flex-1 rounded-xl border border-slate-200 dark:border-white/[0.10] px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/[0.06] transition"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={() => {
                                if (type === 'claims') {
                                    claimRejectMut.mutate({ id: rejectDialog.id, reason: rejectReason });
                                } else {
                                    rejectMut.mutate({ id: rejectDialog.id, reason: rejectReason });
                                }
                            }}
                            disabled={rejectReason.length < 5 || rejectMut.isPending || claimRejectMut.isPending}
                            className="flex-1 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-40 transition active:scale-95"
                        >
                            {(rejectMut.isPending || claimRejectMut.isPending) ? 'Rejecting…' : 'Confirm Reject'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
