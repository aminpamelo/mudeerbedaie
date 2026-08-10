import { useState } from 'react';
import { Send, CheckCircle, XCircle, Clock, Shield } from 'lucide-react';
import { workspaceSend } from '@/workspace/lib/api';
import { cn } from '@/workspace/lib/utils';

const STATUS_MAP = {
    pending_review: { label: 'Pending Review', cls: 'bg-amber-100 text-amber-700', icon: Clock },
    approved: { label: 'Approved', cls: 'bg-emerald-100 text-emerald-700', icon: CheckCircle },
    rejected: { label: 'Rejected', cls: 'bg-red-100 text-red-700', icon: XCircle },
};

export default function ApprovalActions({ task, isAdmin = false, onUpdate }) {
    const [loading, setLoading] = useState(null);

    const perform = async (action) => {
        setLoading(action);
        try {
            await workspaceSend(`/workspace/tasks/${task.id}/${action}`, { method: 'POST' });
            onUpdate?.();
        } catch (err) {
            console.error(`Failed to ${action}:`, err);
        } finally {
            setLoading(null);
        }
    };

    const status = task.approval_status;
    const meta = STATUS_MAP[status];

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="flex items-center gap-2 mb-3">
                <Shield className="h-4 w-4 text-slate-400" />
                <h4 className="text-[12px] font-semibold uppercase tracking-wider text-slate-400">Approval</h4>
            </div>

            {/* Current status */}
            {meta && (
                <div className="mb-3">
                    <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase', meta.cls)}>
                        <meta.icon className="h-3.5 w-3.5" />
                        {meta.label}
                    </span>
                    {status === 'approved' && task.approved_by_user && (
                        <p className="mt-1.5 text-[11.5px] text-slate-400">
                            Approved by {task.approved_by_user.name}
                        </p>
                    )}
                </div>
            )}

            {/* Actions */}
            <div className="flex flex-wrap gap-2">
                {/* Anyone can submit for review */}
                {(!status || status === 'rejected') && (
                    <button
                        onClick={() => perform('submit-review')}
                        disabled={loading === 'submit-review'}
                        className="flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-[12px] font-semibold text-indigo-700 transition hover:bg-indigo-100 disabled:opacity-50"
                    >
                        <Send className="h-3.5 w-3.5" />
                        {loading === 'submit-review' ? 'Submitting...' : 'Submit for Review'}
                    </button>
                )}

                {/* Admin-only approve/reject */}
                {isAdmin && status === 'pending_review' && (
                    <>
                        <button
                            onClick={() => perform('approve')}
                            disabled={loading === 'approve'}
                            className="flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[12px] font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-50"
                        >
                            <CheckCircle className="h-3.5 w-3.5" />
                            {loading === 'approve' ? 'Approving...' : 'Approve'}
                        </button>
                        <button
                            onClick={() => perform('reject')}
                            disabled={loading === 'reject'}
                            className="flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-[12px] font-semibold text-red-700 transition hover:bg-red-100 disabled:opacity-50"
                        >
                            <XCircle className="h-3.5 w-3.5" />
                            {loading === 'reject' ? 'Rejecting...' : 'Reject'}
                        </button>
                    </>
                )}
            </div>

            {/* No actions when already approved */}
            {status === 'approved' && (
                <p className="text-[11.5px] text-slate-400 mt-1">This task has been approved.</p>
            )}
        </div>
    );
}
