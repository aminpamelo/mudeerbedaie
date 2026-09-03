import { useState, useMemo } from 'react';
import { Users, Plus, X, Search, Loader2 } from 'lucide-react';
import { workspaceSend } from '@/workspace/lib/api';
import { cn } from '@/workspace/lib/utils';

const ROLE_STYLES = {
    owner: 'bg-amber-100 text-amber-700',
    manager: 'bg-indigo-100 text-indigo-700',
    member: 'bg-slate-100 text-slate-600',
    viewer: 'bg-slate-100 text-slate-500',
};

const ADD_ROLES = [
    { value: 'member', label: 'Member' },
    { value: 'manager', label: 'Manager' },
    { value: 'viewer', label: 'Viewer' },
];

const initial = (name) => name?.trim()?.charAt(0)?.toUpperCase() ?? '?';

export default function ProjectMembers({ project, staff = [], onChanged }) {
    const [showAdd, setShowAdd] = useState(false);
    const [role, setRole] = useState('member');
    const [search, setSearch] = useState('');
    const [busyId, setBusyId] = useState(null);
    const [error, setError] = useState('');

    const members = project.members ?? [];
    const memberIds = useMemo(() => new Set(members.map((m) => m.id)), [members]);

    const available = useMemo(() => {
        const q = search.trim().toLowerCase();
        return staff
            .filter((s) => !memberIds.has(s.user_id))
            .filter(
                (s) =>
                    !q ||
                    s.name.toLowerCase().includes(q) ||
                    (s.department ?? '').toLowerCase().includes(q)
            );
    }, [staff, memberIds, search]);

    const roleOf = (m) => m.pivot?.role ?? 'member';
    const isOwner = (m) => roleOf(m) === 'owner' || m.id === project.owner_id;

    const add = async (userId) => {
        setBusyId(userId);
        setError('');
        try {
            await workspaceSend(`/workspace/projects/${project.id}/members`, {
                method: 'POST',
                body: { user_id: userId, role },
            });
            onChanged?.();
        } catch (err) {
            setError(err.message);
        } finally {
            setBusyId(null);
        }
    };

    const remove = async (userId) => {
        setBusyId(userId);
        setError('');
        try {
            await workspaceSend(
                `/workspace/projects/${project.id}/members/${userId}`,
                { method: 'DELETE' }
            );
            onChanged?.();
        } catch (err) {
            setError(err.message);
        } finally {
            setBusyId(null);
        }
    };

    return (
        <div className="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
            <div className="flex items-center justify-between">
                <h3 className="flex items-center gap-2 text-[14px] font-bold text-slate-900">
                    <Users className="h-4 w-4 text-slate-400" /> Team
                    <span className="text-[12px] font-medium text-slate-400">
                        ({members.length})
                    </span>
                </h3>
                <button
                    onClick={() => {
                        setShowAdd(true);
                        setSearch('');
                        setError('');
                    }}
                    className="flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2 text-[12.5px] font-semibold text-slate-600 hover:bg-slate-200"
                >
                    <Plus className="h-4 w-4" /> Add member
                </button>
            </div>

            {error && !showAdd && (
                <p className="mt-3 rounded-xl bg-red-50 px-3 py-2 text-[12.5px] text-red-700">
                    {error}
                </p>
            )}

            <div className="mt-4 space-y-2">
                {members.length === 0 && (
                    <p className="text-[12.5px] text-slate-400">No members yet.</p>
                )}
                {members.map((m) => (
                    <div
                        key={m.id}
                        className="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2"
                    >
                        <div className="flex items-center gap-2.5">
                            <div className="grid h-8 w-8 place-items-center rounded-full bg-gradient-to-br from-indigo-400 to-violet-400 text-[11px] font-bold text-white">
                                {initial(m.name)}
                            </div>
                            <div>
                                <p className="text-[13px] font-semibold text-slate-800">
                                    {m.name}
                                </p>
                                {m.email && (
                                    <p className="text-[11px] text-slate-400">
                                        {m.email}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <span
                                className={cn(
                                    'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                    ROLE_STYLES[roleOf(m)] ?? ROLE_STYLES.member
                                )}
                            >
                                {roleOf(m)}
                            </span>
                            {!isOwner(m) && (
                                <button
                                    onClick={() => remove(m.id)}
                                    disabled={busyId === m.id}
                                    title="Remove member"
                                    className="grid h-7 w-7 place-items-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                                >
                                    {busyId === m.id ? (
                                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    ) : (
                                        <X className="h-3.5 w-3.5" />
                                    )}
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            {showAdd && (
                <div
                    className="fixed inset-0 z-[70] flex items-center justify-center p-4"
                    role="dialog"
                >
                    <div
                        className="absolute inset-0 bg-black/40 backdrop-blur-sm"
                        onClick={() => setShowAdd(false)}
                    />
                    <div className="relative z-10 flex max-h-[80vh] w-full max-w-md flex-col rounded-2xl bg-white shadow-xl">
                        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <h3 className="text-[16px] font-bold text-slate-900">
                                Add member
                            </h3>
                            <button
                                onClick={() => setShowAdd(false)}
                                className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <div className="space-y-3 border-b border-slate-100 px-5 py-4">
                            <div>
                                <label className="mb-1.5 block text-[12px] font-semibold uppercase tracking-wider text-slate-400">
                                    Role
                                </label>
                                <select
                                    value={role}
                                    onChange={(e) => setRole(e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-[13.5px] text-slate-900 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                >
                                    {ADD_ROLES.map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {r.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search staff…"
                                    className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-[13.5px] text-slate-900 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                />
                            </div>
                        </div>

                        {error && (
                            <p className="mx-5 mt-3 rounded-xl bg-red-50 px-3 py-2 text-[12.5px] text-red-700">
                                {error}
                            </p>
                        )}

                        <div className="ws-scroll flex-1 overflow-y-auto px-5 py-3">
                            {available.length === 0 ? (
                                <p className="py-6 text-center text-[12.5px] text-slate-400">
                                    {staff.length === 0
                                        ? 'No staff available.'
                                        : 'Everyone is already a member.'}
                                </p>
                            ) : (
                                <div className="space-y-1.5">
                                    {available.map((s) => (
                                        <button
                                            key={s.user_id}
                                            onClick={() => add(s.user_id)}
                                            disabled={busyId === s.user_id}
                                            className="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left hover:bg-slate-50 disabled:opacity-50"
                                        >
                                            <div className="flex items-center gap-2.5">
                                                <div className="grid h-8 w-8 place-items-center rounded-full bg-gradient-to-br from-slate-300 to-slate-400 text-[11px] font-bold text-white">
                                                    {initial(s.name)}
                                                </div>
                                                <div>
                                                    <p className="text-[13px] font-semibold text-slate-800">
                                                        {s.name}
                                                    </p>
                                                    {s.department && (
                                                        <p className="text-[11px] text-slate-400">
                                                            {s.department}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            {busyId === s.user_id ? (
                                                <Loader2 className="h-4 w-4 animate-spin text-indigo-500" />
                                            ) : (
                                                <Plus className="h-4 w-4 text-indigo-500" />
                                            )}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
