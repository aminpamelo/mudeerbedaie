import { router } from '@inertiajs/react';
import { Trophy, Flame } from 'lucide-react';
import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import { cn } from '@/workspace/lib/utils';

const RANK_STYLES = {
    1: 'bg-gradient-to-r from-amber-400 to-amber-500 text-white shadow-lg shadow-amber-500/30',
    2: 'bg-gradient-to-r from-slate-300 to-slate-400 text-white shadow-lg shadow-slate-400/30',
    3: 'bg-gradient-to-r from-orange-400 to-orange-500 text-white shadow-lg shadow-orange-500/30',
};

export default function Leaderboard({ leaderboard = [], myBadges = [], period = 'month' }) {
    const setPeriod = (p) => router.get('/workspace/leaderboard', { period: p }, { preserveScroll: true });

    return (
        <WorkspaceLayout title="Leaderboard" subtitle="Top performers and achievements">
            <div className="mb-4 inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1">
                {['week', 'month', 'year'].map(p => (
                    <button
                        key={p}
                        onClick={() => setPeriod(p)}
                        className={cn(
                            'rounded-lg px-3 py-1.5 text-[12.5px] font-semibold capitalize transition',
                            period === p
                                ? 'bg-gradient-to-r from-indigo-500 to-violet-500 text-white shadow'
                                : 'text-slate-500 hover:text-slate-700',
                        )}
                    >
                        {p === 'week' ? 'This Week' : p === 'month' ? 'This Month' : 'This Year'}
                    </button>
                ))}
            </div>

            {/* Podium */}
            {leaderboard.length >= 3 && (
                <div className="mb-8 flex items-end justify-center gap-4">
                    {[1, 0, 2].map(idx => {
                        const entry = leaderboard[idx];
                        if (!entry) return null;
                        const heights = { 0: 'h-32', 1: 'h-24', 2: 'h-20' };
                        return (
                            <div key={idx} className="flex flex-col items-center">
                                <div
                                    className={cn(
                                        'mb-2 grid h-14 w-14 place-items-center rounded-full text-lg font-bold',
                                        RANK_STYLES[entry.rank] ?? 'bg-slate-200 text-slate-600',
                                    )}
                                >
                                    {entry.name.charAt(0).toUpperCase()}
                                </div>
                                <p className="text-[13px] font-bold text-slate-900">{entry.name}</p>
                                <p className="text-[11px] text-slate-400">{entry.total_points} pts</p>
                                <div
                                    className={cn(
                                        'mt-2 flex w-24 items-end justify-center rounded-t-xl bg-gradient-to-t from-indigo-100 to-indigo-50 pb-2',
                                        heights[idx],
                                    )}
                                >
                                    <span className="text-[20px] font-black text-indigo-600">
                                        #{entry.rank}
                                    </span>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {/* Full ranking table */}
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <table className="w-full">
                    <thead className="bg-slate-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                Rank
                            </th>
                            <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                Name
                            </th>
                            <th className="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                Tasks
                            </th>
                            <th className="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                Streak
                            </th>
                            <th className="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                Time
                            </th>
                            <th className="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                Points
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {leaderboard.map(entry => (
                            <tr key={entry.user_id} className="hover:bg-slate-50/50">
                                <td className="px-4 py-3">
                                    <span
                                        className={cn(
                                            'inline-grid h-7 w-7 place-items-center rounded-full text-[11px] font-bold',
                                            RANK_STYLES[entry.rank] ?? 'bg-slate-100 text-slate-600',
                                        )}
                                    >
                                        {entry.rank}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-[13px] font-semibold text-slate-900">
                                    {entry.name}
                                </td>
                                <td className="px-4 py-3 text-center text-[13px] tabular-nums text-slate-700">
                                    {entry.total_completed}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <span className="inline-flex items-center gap-1 text-[12px] text-amber-600">
                                        <Flame className="h-3 w-3" />
                                        {entry.max_streak}d
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-center text-[12px] tabular-nums text-slate-500">
                                    {entry.total_time_hours}h
                                </td>
                                <td className="px-4 py-3 text-right text-[14px] font-bold tabular-nums text-indigo-600">
                                    {entry.total_points}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* My badges */}
            {myBadges.length > 0 && (
                <div className="mt-8">
                    <h3 className="mb-3 text-[15px] font-bold text-slate-900">My Badges</h3>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {myBadges.map((b, i) => (
                            <div
                                key={i}
                                className="rounded-2xl border border-slate-200 bg-white p-4 text-center"
                            >
                                <div
                                    className="mx-auto grid h-12 w-12 place-items-center rounded-xl text-white"
                                    style={{ backgroundColor: b.color }}
                                >
                                    <Trophy className="h-6 w-6" />
                                </div>
                                <p className="mt-2 text-[13px] font-bold text-slate-900">{b.name}</p>
                                <p className="text-[11px] text-slate-400">{b.description}</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </WorkspaceLayout>
    );
}
