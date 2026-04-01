import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Timer, CalendarOff, Receipt, ShieldCheck, DoorOpen } from 'lucide-react';
import api from '../../lib/api';

function fetchApprovalSummary() {
    return api.get('/my-approvals/summary').then((r) => r.data);
}

function StatCard({ icon: Icon, label, pending, isAssigned, onClick, color }) {
    return (
        <button
            onClick={onClick}
            disabled={!isAssigned}
            className={`flex flex-col gap-3 rounded-xl border p-5 text-left transition-all ${
                isAssigned
                    ? 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-md cursor-pointer'
                    : 'border-slate-100 bg-slate-50 cursor-not-allowed opacity-60'
            }`}
        >
            <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${color}`}>
                <Icon className="h-5 w-5 text-white" />
            </div>
            <div>
                <p className="text-sm text-slate-500">{label}</p>
                {isAssigned ? (
                    <p className="text-2xl font-bold text-slate-800">
                        {pending}
                        <span className="ml-1 text-sm font-normal text-slate-400">pending</span>
                    </p>
                ) : (
                    <p className="text-sm text-slate-400 mt-1">Not assigned</p>
                )}
            </div>
        </button>
    );
}

export default function MyApprovals() {
    const navigate = useNavigate();
    const { data, isLoading } = useQuery({
        queryKey: ['my-approvals-summary'],
        queryFn: fetchApprovalSummary,
    });

    if (isLoading) {
        return (
            <div className="flex h-48 items-center justify-center">
                <div className="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600" />
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-3xl p-4 lg:p-6">
            <div className="mb-6 flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600">
                    <ShieldCheck className="h-5 w-5 text-white" />
                </div>
                <div>
                    <h1 className="text-xl font-bold text-slate-800">My Approvals</h1>
                    <p className="text-sm text-slate-500">Review and action requests from your team</p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    icon={Timer}
                    label="Overtime"
                    pending={data?.overtime?.pending ?? 0}
                    isAssigned={data?.overtime?.isAssigned ?? false}
                    onClick={() => navigate('/my/approvals/overtime')}
                    color="bg-orange-500"
                />
                <StatCard
                    icon={CalendarOff}
                    label="Leave"
                    pending={data?.leave?.pending ?? 0}
                    isAssigned={data?.leave?.isAssigned ?? false}
                    onClick={() => navigate('/my/approvals/leave')}
                    color="bg-blue-500"
                />
                <StatCard
                    icon={Receipt}
                    label="Claims"
                    pending={data?.claims?.pending ?? 0}
                    isAssigned={data?.claims?.isAssigned ?? false}
                    onClick={() => navigate('/my/approvals/claims')}
                    color="bg-green-500"
                />
                <StatCard
                    icon={DoorOpen}
                    label="Exit Permissions"
                    pending={data?.exit_permission?.pending ?? 0}
                    isAssigned={data?.exit_permission?.isAssigned ?? false}
                    onClick={() => navigate('/my/approvals/exit-permissions')}
                    color="bg-purple-500"
                />
            </div>
        </div>
    );
}
