import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Timer, CalendarOff, Receipt, ChevronRight, ShieldCheck, DoorOpen } from 'lucide-react';
import api from '../../lib/api';

function fetchApprovalSummary() {
    return api.get('/my-approvals/summary').then((r) => r.data);
}

const MODULES = [
    {
        key: 'overtime',
        label: 'Overtime',
        description: 'Review extra hours requests',
        icon: Timer,
        route: '/my/approvals/overtime',
        iconBg: 'bg-amber-50',
        iconColor: 'text-amber-600',
        badgeColor: 'bg-amber-500',
        borderColor: 'border-l-amber-400',
        pendingKey: 'overtime',
    },
    {
        key: 'leave',
        label: 'Leave',
        description: 'Manage time-off requests',
        icon: CalendarOff,
        route: '/my/approvals/leave',
        iconBg: 'bg-blue-50',
        iconColor: 'text-blue-600',
        badgeColor: 'bg-blue-500',
        borderColor: 'border-l-blue-400',
        pendingKey: 'leave',
    },
    {
        key: 'claims',
        label: 'Claims',
        description: 'Approve expense claims',
        icon: Receipt,
        route: '/my/approvals/claims',
        iconBg: 'bg-emerald-50',
        iconColor: 'text-emerald-600',
        badgeColor: 'bg-emerald-500',
        borderColor: 'border-l-emerald-400',
        pendingKey: 'claims',
    },
    {
        key: 'exit_permission',
        label: 'Exit Permissions',
        description: 'Review office exit requests',
        icon: DoorOpen,
        route: '/my/approvals/exit-permissions',
        iconBg: 'bg-purple-50',
        iconColor: 'text-purple-600',
        badgeColor: 'bg-purple-500',
        borderColor: 'border-l-purple-400',
        pendingKey: 'exit_permission',
    },
];

function ModuleCard({ module, pending, isAssigned, myTiers, tierBreakdown, onClick }) {
    const Icon = module.icon;
    const hasTiers = myTiers && myTiers.length > 0;
    const hasBreakdown = tierBreakdown && Object.keys(tierBreakdown).length > 1;

    return (
        <button
            onClick={onClick}
            disabled={!isAssigned}
            className={`group w-full text-left rounded-2xl bg-white border border-zinc-100 border-l-4 ${module.borderColor} shadow-sm transition-all ${
                isAssigned
                    ? 'hover:shadow-md hover:-translate-y-0.5 cursor-pointer active:scale-[0.98]'
                    : 'opacity-50 cursor-not-allowed'
            }`}
        >
            <div className="flex items-center gap-4 p-4">
                <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl ${module.iconBg}`}>
                    <Icon className={`h-5 w-5 ${module.iconColor}`} />
                </div>

                <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-zinc-900">{module.label}</p>
                    <p className="text-xs text-zinc-400 mt-0.5">{module.description}</p>
                    {isAssigned && hasTiers && hasBreakdown && (
                        <div className="flex items-center gap-1.5 mt-1.5 flex-wrap">
                            <span className="inline-flex items-center rounded-md bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-600">
                                You: Tier {myTiers.join(', ')}
                            </span>
                            {Object.entries(tierBreakdown).map(([tier, count]) => (
                                <span
                                    key={tier}
                                    className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-medium ${
                                        count > 0
                                            ? 'bg-amber-50 text-amber-700'
                                            : 'bg-zinc-50 text-zinc-400'
                                    }`}
                                >
                                    T{tier}: {count}
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex items-center gap-2 shrink-0">
                    {isAssigned ? (
                        pending > 0 ? (
                            <span className={`flex h-6 min-w-6 items-center justify-center rounded-full ${module.badgeColor} px-1.5 text-xs font-bold text-white`}>
                                {pending}
                            </span>
                        ) : (
                            <span className="text-xs text-zinc-400">All clear</span>
                        )
                    ) : (
                        <span className="text-xs text-zinc-400">Not assigned</span>
                    )}
                    <ChevronRight className={`h-4 w-4 text-zinc-300 transition-transform ${isAssigned ? 'group-hover:translate-x-0.5' : ''}`} />
                </div>
            </div>
        </button>
    );
}

function SkeletonCard() {
    return (
        <div className="rounded-2xl bg-white border border-zinc-100 p-4 shadow-sm animate-pulse">
            <div className="flex items-center gap-4">
                <div className="h-11 w-11 rounded-2xl bg-zinc-100 shrink-0" />
                <div className="flex-1 space-y-2">
                    <div className="h-4 w-24 rounded bg-zinc-100" />
                    <div className="h-3 w-36 rounded bg-zinc-100" />
                </div>
                <div className="h-6 w-6 rounded-full bg-zinc-100" />
            </div>
        </div>
    );
}

export default function MyApprovals() {
    const navigate = useNavigate();
    const { data, isLoading } = useQuery({
        queryKey: ['my-approvals-summary'],
        queryFn: fetchApprovalSummary,
    });

    const totalPending = MODULES.reduce((sum, m) => {
        const mod = data?.[m.pendingKey];
        return sum + (mod?.isAssigned ? (mod?.pending ?? 0) : 0);
    }, 0);

    return (
        <div className="flex flex-col min-h-full bg-zinc-50">
            {/* Header */}
            <div className="bg-white border-b border-zinc-100 px-4 py-5 shadow-sm">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-zinc-900">
                        <ShieldCheck className="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h1 className="text-base font-bold text-zinc-900">My Approvals</h1>
                        <p className="text-xs text-zinc-400">Review requests from your team</p>
                    </div>
                    {!isLoading && totalPending > 0 && (
                        <span className="ml-auto flex h-6 min-w-6 items-center justify-center rounded-full bg-zinc-900 px-2 text-xs font-bold text-white">
                            {totalPending}
                        </span>
                    )}
                </div>
            </div>

            {/* Cards */}
            <div className="p-4 space-y-3">
                {isLoading ? (
                    <>
                        <SkeletonCard />
                        <SkeletonCard />
                        <SkeletonCard />
                    </>
                ) : (
                    MODULES.map((module, i) => {
                        const mod = data?.[module.pendingKey];
                        return (
                            <div
                                key={module.key}
                                style={{ animation: `fadeSlideUp 0.3s ease ${i * 0.07}s both` }}
                            >
                                <ModuleCard
                                    module={module}
                                    pending={mod?.pending ?? 0}
                                    isAssigned={mod?.isAssigned ?? false}
                                    myTiers={mod?.myTiers ?? []}
                                    tierBreakdown={mod?.tierBreakdown ?? {}}
                                    onClick={() => navigate(module.route)}
                                />
                            </div>
                        );
                    })
                )}
            </div>

            <style>{`
                @keyframes fadeSlideUp {
                    from { opacity: 0; transform: translateY(8px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
            `}</style>
        </div>
    );
}
