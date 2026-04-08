import { Check, X, Clock } from 'lucide-react';

function formatLogDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' });
}

/**
 * Displays tier approval progress for a request.
 *
 * Props:
 * - maxTier: number — total tiers configured for this department+type
 * - currentTier: number — the request's current_approval_tier
 * - approvalLogs: array — [{tier, action, approver: {id, full_name}, created_at}]
 * - tierApprovers: object — {1: [{id, full_name}], 2: [{id, full_name}]} — configured approvers per tier
 * - status: string — request status (pending, approved, completed, rejected)
 *
 * Only renders if maxTier > 1 (multi-tier setup).
 */
export default function TierProgressBar({ maxTier, currentTier, approvalLogs = [], tierApprovers = {}, status }) {
    if (!maxTier || maxTier <= 1) return null;

    const tiers = [];
    for (let t = 1; t <= maxTier; t++) {
        // Find the log entry for this tier (approved or rejected)
        const log = approvalLogs.find((l) => l.tier === t && (l.action === 'approved' || l.action === 'rejected'));
        const configuredApprovers = tierApprovers[t] || [];

        let tierStatus = 'waiting'; // waiting, current, approved, rejected
        if (log?.action === 'approved') {
            tierStatus = 'approved';
        } else if (log?.action === 'rejected') {
            tierStatus = 'rejected';
        } else if (t === currentTier && (status === 'pending')) {
            tierStatus = 'current';
        }

        tiers.push({
            tier: t,
            status: tierStatus,
            log,
            approvers: configuredApprovers,
        });
    }

    return (
        <div className="mt-3 rounded-xl bg-zinc-50 px-3 py-2.5">
            <p className="text-[10px] uppercase tracking-wide text-zinc-400 font-medium mb-2">Approval Progress</p>
            <div className="flex items-center gap-1">
                {tiers.map((t, i) => (
                    <div key={t.tier} className="flex items-center gap-1 flex-1 min-w-0">
                        {/* Tier node */}
                        <div className="flex items-center gap-1.5 min-w-0">
                            {/* Icon */}
                            {t.status === 'approved' && (
                                <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100">
                                    <Check className="h-3 w-3 text-emerald-600" />
                                </div>
                            )}
                            {t.status === 'rejected' && (
                                <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-100">
                                    <X className="h-3 w-3 text-red-600" />
                                </div>
                            )}
                            {t.status === 'current' && (
                                <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-100 animate-pulse">
                                    <Clock className="h-3 w-3 text-amber-600" />
                                </div>
                            )}
                            {t.status === 'waiting' && (
                                <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-zinc-200">
                                    <span className="text-[9px] font-bold text-zinc-400">{t.tier}</span>
                                </div>
                            )}

                            {/* Label */}
                            <div className="min-w-0">
                                <p className={`text-[10px] font-semibold truncate ${
                                    t.status === 'approved' ? 'text-emerald-700' :
                                    t.status === 'rejected' ? 'text-red-700' :
                                    t.status === 'current' ? 'text-amber-700' :
                                    'text-zinc-400'
                                }`}>
                                    T{t.tier}
                                </p>
                                <p className="text-[10px] text-zinc-500 truncate">
                                    {t.log?.approver?.full_name
                                        ? `${t.log.approver.full_name}`
                                        : t.approvers.length > 0
                                            ? t.approvers.map((a) => a.full_name).join(', ')
                                            : '—'
                                    }
                                </p>
                                {t.log?.created_at && (
                                    <p className="text-[9px] text-zinc-400">{formatLogDate(t.log.created_at)}</p>
                                )}
                            </div>
                        </div>

                        {/* Connector line */}
                        {i < tiers.length - 1 && (
                            <div className={`flex-1 h-px min-w-3 mx-1 ${
                                t.status === 'approved' ? 'bg-emerald-300' :
                                t.status === 'rejected' ? 'bg-red-300' :
                                'bg-zinc-200'
                            }`} />
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
