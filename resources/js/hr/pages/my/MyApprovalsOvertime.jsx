import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Check, X, AlertCircle } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '../../components/ui/dialog';
import api from '../../lib/api';

const TABS = ['all', 'pending', 'approved', 'rejected', 'completed'];

const STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    completed: 'bg-blue-100 text-blue-800',
    cancelled: 'bg-slate-100 text-slate-600',
};

function fetchOvertimeApprovals(status) {
    const params = status !== 'all' ? `?status=${status}` : '';
    return api.get(`/my-approvals/overtime${params}`).then((r) => r.data);
}

export default function MyApprovalsOvertime() {
    const navigate = useNavigate();
    const qc = useQueryClient();
    const [tab, setTab] = useState('pending');
    const [rejectDialog, setRejectDialog] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [actionError, setActionError] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['my-approvals-overtime', tab],
        queryFn: () => fetchOvertimeApprovals(tab),
    });

    const approveMut = useMutation({
        mutationFn: (id) => api.patch(`/my-approvals/overtime/${id}/approve`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-overtime'] });
            qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
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
        },
        onError: (err) => setActionError(err.response?.data?.message ?? 'Failed to reject.'),
    });

    const requests = data?.data ?? [];

    return (
        <div className="mx-auto max-w-4xl p-4 lg:p-6">
            <div className="mb-5 flex items-center gap-3">
                <button onClick={() => navigate('/my/approvals')} className="text-slate-400 hover:text-slate-600">
                    <ArrowLeft className="h-5 w-5" />
                </button>
                <h1 className="text-xl font-bold text-slate-800">Overtime Approvals</h1>
            </div>

            <div className="mb-4 flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1">
                {TABS.map((t) => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={`flex-shrink-0 rounded-md px-3 py-1.5 text-sm font-medium capitalize transition-colors ${
                            tab === t ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        {t}
                    </button>
                ))}
            </div>

            {isLoading ? (
                <div className="flex h-32 items-center justify-center">
                    <div className="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600" />
                </div>
            ) : requests.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-200 py-12 text-center text-slate-400">
                    No {tab === 'all' ? '' : tab} overtime requests
                </div>
            ) : (
                <div className="space-y-3">
                    {requests.map((req) => (
                        <div key={req.id} className="rounded-xl border border-slate-200 bg-white p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-semibold text-slate-800">{req.employee?.name}</p>
                                    <p className="text-sm text-slate-500">
                                        {req.employee?.position?.name} · {req.employee?.department?.name}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-600">
                                        {req.requested_date} · {req.estimated_hours}h estimated
                                    </p>
                                    {req.reason && (
                                        <p className="mt-1 text-sm text-slate-500 line-clamp-2">{req.reason}</p>
                                    )}
                                    {req.rejection_reason && (
                                        <p className="mt-1 text-sm text-red-500">Reason: {req.rejection_reason}</p>
                                    )}
                                </div>
                                <span
                                    className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium capitalize ${STATUS_COLORS[req.status] ?? 'bg-slate-100 text-slate-600'}`}
                                >
                                    {req.status}
                                </span>
                            </div>

                            {req.status === 'pending' && (
                                <div className="mt-3 flex gap-2">
                                    <button
                                        onClick={() => approveMut.mutate(req.id)}
                                        disabled={approveMut.isPending}
                                        className="flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                                    >
                                        <Check className="h-4 w-4" /> Approve
                                    </button>
                                    <button
                                        onClick={() => {
                                            setRejectDialog({ id: req.id, name: req.employee?.name });
                                            setRejectReason('');
                                            setActionError('');
                                        }}
                                        className="flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                                    >
                                        <X className="h-4 w-4" /> Reject
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            <Dialog open={!!rejectDialog} onOpenChange={() => setRejectDialog(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Overtime Request</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-slate-500">
                        Rejecting request from <strong>{rejectDialog?.name}</strong>. Please provide a reason.
                    </p>
                    <textarea
                        className="mt-2 w-full rounded-lg border border-slate-200 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        rows={3}
                        placeholder="Reason for rejection (min 5 characters)"
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                    />
                    {actionError && (
                        <p className="flex items-center gap-1 text-sm text-red-500">
                            <AlertCircle className="h-4 w-4" /> {actionError}
                        </p>
                    )}
                    <DialogFooter>
                        <button
                            onClick={() => setRejectDialog(null)}
                            className="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={() => rejectMut.mutate({ id: rejectDialog.id, reason: rejectReason })}
                            disabled={rejectReason.length < 5 || rejectMut.isPending}
                            className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            {rejectMut.isPending ? 'Rejecting...' : 'Confirm Reject'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
